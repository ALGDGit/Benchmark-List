<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\HomepageSlot;
use App\Entity\Item;
use App\Entity\ItemListPosition;
use App\Repository\CategoryRepository;
use App\Repository\HomepageSlotRepository;
use App\Repository\ItemRepository;
use App\Service\Activity\ActivityLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Varnish invalidation by cache tags only (X-Cache-Tags on cached API objects).
 */
final class VarnishPurger
{
    /** @var int[] */
    private const ALL_LIST_SLOTS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $varnishUrl,
        private readonly CategoryRepository $categoryRepository,
        private readonly HomepageSlotRepository $slotRepository,
        private readonly ItemRepository $itemRepository,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function purgeAll(): void
    {
        $result = $this->sendBan(['X-Ban-All-Tags' => '1']);

        $this->activityLogger->log(
            'varnish',
            'purge_all',
            'BAN all Varnish objects with cache tags',
            [
                'command' => 'BAN',
                'type' => 'purge_all_tags',
                'ban_result' => $result,
            ]
        );
    }

    public function purgeListSlot(int $slotNumber): void
    {
        if ($slotNumber < 1 || $slotNumber > HomepageSlot::COUNT) {
            return;
        }

        $this->purgeTags(CacheTag::list($slotNumber));
    }

    public function purgeCategorySlug(string $slug): void
    {
        if ($slug !== '') {
            $this->purgeTags(CacheTag::category($slug));
        }
    }

    public function purgeCategoryIds(int ...$categoryIds): void
    {
        $tags = [];
        foreach (array_unique($categoryIds) as $categoryId) {
            $category = $this->categoryRepository->find($categoryId);
            if ($category !== null && $category->getSlug() !== '') {
                $tags[] = CacheTag::category($category->getSlug());
            }
        }

        $this->purgeTags(...$tags);
    }

    /**
     * After saving an item: full category/list purge when at the beginning, last page only when at the end.
     *
     * @param iterable<Category> $categories
     */
    public function purgeAfterItemChange(Item $item, iterable $categories): void
    {
        $tags = [];

        foreach ($categories as $category) {
            $slug = $category->getSlug();
            if ($slug === '') {
                continue;
            }

            if ($item->getListPosition() === ItemListPosition::First) {
                $tags[] = CacheTag::category($slug);
                foreach ($this->slotRepository->findSlotNumbersByCategoryId((int) $category->getId()) as $slotNumber) {
                    $tags[] = CacheTag::list($slotNumber);
                }
            } else {
                $total = $this->itemRepository->countByCategory($category);
                $limit = CacheTag::API_PAGE_LIMIT;
                $lastPage = CacheTag::lastPage($total, $limit);
                $previousLastPage = CacheTag::lastPage(max(0, $total - 1), $limit);

                $tags[] = CacheTag::categoryPage($slug, $lastPage);
                if ($previousLastPage !== $lastPage) {
                    $tags[] = CacheTag::categoryPage($slug, $previousLastPage);
                }
                foreach ($this->slotRepository->findSlotNumbersByCategoryId((int) $category->getId()) as $slotNumber) {
                    $tags[] = CacheTag::listPage($slotNumber, $lastPage);
                    if ($previousLastPage !== $lastPage) {
                        $tags[] = CacheTag::listPage($slotNumber, $previousLastPage);
                    }
                }
            }
        }

        $this->purgeTags(...array_values(array_unique($tags)));
    }

    /**
     * @return array<string, string> tag => ban result
     */
    public function purgeTags(string ...$tags): array
    {
        $tags = array_values(array_unique(array_filter($tags, static fn (string $t): bool => $t !== '')));
        if ($tags === []) {
            return [];
        }

        sort($tags);
        $results = [];
        foreach ($tags as $tag) {
            $results[$tag] = $this->sendBan(['X-Ban-Tag' => $tag]);
        }

        $this->activityLogger->log(
            'varnish',
            'ban_tags',
            sprintf('BAN Varnish cache tag(s): %s', implode(', ', $tags)),
            [
                'command' => 'BAN',
                'type' => 'cache_tag',
                'tags' => $tags,
                'ban_results' => $results,
            ]
        );

        return $results;
    }

    /**
     * @param array<string, string> $headers
     */
    private function sendBan(array $headers): string
    {
        try {
            $response = $this->httpClient->request('BAN', rtrim($this->varnishUrl, '/').'/', [
                'headers' => array_merge(['Host' => 'localhost'], $headers),
                'timeout' => 5,
            ]);
            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return 'ok';
            }

            return 'http_'.$status;
        } catch (\Throwable $e) {
            return 'error: '.$e->getMessage();
        }
    }
}
