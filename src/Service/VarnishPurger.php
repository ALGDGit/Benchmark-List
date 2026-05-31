<?php

namespace App\Service;

use App\Entity\HomepageSlot;
use App\Repository\CategoryRepository;
use App\Repository\HomepageSlotRepository;
use App\Service\Activity\ActivityLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Selective Varnish cache purge by homepage list (slot 1–10) and category.
 */
final class VarnishPurger
{
    /** @var int[] */
    private const ALL_SLOTS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $varnishUrl,
        private readonly HomepageSlotRepository $slotRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function purgeAll(): void
    {
        $slotResults = [];
        foreach (self::ALL_SLOTS as $slotNumber) {
            $slotResults[$slotNumber] = $this->sendBan(['X-Ban-Slot' => (string) $slotNumber]);
        }

        $categorySlugs = [];
        $categoryResults = [];
        foreach ($this->categoryRepository->findAllOrdered() as $category) {
            $slug = $category->getSlug();
            if ($slug === '') {
                continue;
            }
            $categorySlugs[] = $slug;
            $categoryResults[$slug] = $this->sendBan(['X-Ban-Category' => $slug]);
        }

        $this->activityLogger->log(
            'varnish',
            'purge_all',
            sprintf(
                'BAN all Varnish cache (%d list slots, %d categories)',
                \count(self::ALL_SLOTS),
                \count($categorySlugs)
            ),
            [
                'command' => 'BAN',
                'type' => 'purge_all',
                'slots' => self::ALL_SLOTS,
                'category_slugs' => $categorySlugs,
                'slot_ban_results' => $slotResults,
                'category_ban_results' => $categoryResults,
            ]
        );
    }

    public function purgeListSlot(int $slotNumber): void
    {
        if ($slotNumber < 1 || $slotNumber > HomepageSlot::COUNT) {
            return;
        }

        $this->banSlots([$slotNumber]);
    }

    public function purgeSlotsForCategory(int $categoryId): void
    {
        $this->purgeSlotsForCategories($categoryId);
    }

    public function purgeCategoryBySlug(string $slug): void
    {
        if ($slug !== '') {
            $this->banCategorySlug($slug);
        }
    }

    public function purgeSlotsForCategories(int ...$categoryIds): void
    {
        $slotsToPurge = [];

        foreach ($categoryIds as $categoryId) {
            foreach ($this->slotRepository->findSlotNumbersByCategoryId($categoryId) as $slotNumber) {
                $slotsToPurge[$slotNumber] = true;
            }
        }

        $this->banSlots(array_map('intval', array_keys($slotsToPurge)));

        foreach (array_unique($categoryIds) as $categoryId) {
            $category = $this->categoryRepository->find($categoryId);
            if ($category !== null) {
                $this->banCategorySlug($category->getSlug());
            }
        }
    }

    /**
     * @param int[] $slotNumbers
     */
    private function banSlots(array $slotNumbers): void
    {
        if ($slotNumbers === []) {
            return;
        }

        sort($slotNumbers);
        $results = [];

        foreach ($slotNumbers as $slotNumber) {
            $results[$slotNumber] = $this->sendBan(['X-Ban-Slot' => (string) $slotNumber]);
        }

        $unaffected = array_values(array_diff(self::ALL_SLOTS, $slotNumbers));

        $this->activityLogger->log(
            'varnish',
            'ban',
            sprintf(
                'BAN Varnish on list(s) %s — list(s) %s still cached',
                implode(', ', $slotNumbers),
                $unaffected === [] ? 'ninguna' : implode(', ', $unaffected)
            ),
            [
                'command' => 'BAN',
                'type' => 'homepage_slot',
                'slots_invalidated' => $slotNumbers,
                'slots_still_cached' => $unaffected,
                'ban_results' => $results,
            ]
        );
    }

    private function banCategorySlug(string $slug): void
    {
        $result = $this->sendBan(['X-Ban-Category' => $slug]);

        $this->activityLogger->log(
            'varnish',
            'ban_category',
            sprintf('BAN Varnish on category /api/categories/%s', $slug),
            [
                'command' => 'BAN',
                'type' => 'category',
                'category_slug' => $slug,
                'ban_result' => $result,
            ]
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function sendBan(array $headers): string
    {
        try {
            $this->httpClient->request('BAN', rtrim($this->varnishUrl, '/').'/', [
                'headers' => array_merge(['Host' => 'localhost'], $headers),
                'timeout' => 2,
            ]);

            return 'ok';
        } catch (\Throwable $e) {
            return 'error: '.$e->getMessage();
        }
    }
}
