<?php

namespace App\Controller\Api;

use App\Entity\HomepageSlot;
use App\Repository\HomepageSlotRepository;
use App\Repository\ItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/lists')]
final class ListApiController extends AbstractController
{
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 100;

    #[Route('/{slot}', name: 'api_list', requirements: ['slot' => '[1-9]|10'], methods: ['GET'])]
    public function list(
        int $slot,
        Request $request,
        HomepageSlotRepository $slotRepository,
        ItemRepository $itemRepository,
    ): JsonResponse {
        $homepageSlot = $slotRepository->findBySlotNumber($slot);
        if ($homepageSlot === null || $homepageSlot->getCategory() === null) {
            return $this->json(['error' => 'List not configured'], Response::HTTP_NOT_FOUND);
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(self::MAX_LIMIT, max(1, $request->query->getInt('limit', self::DEFAULT_LIMIT)));
        $category = $homepageSlot->getCategory();

        $items = $itemRepository->findPageByCategory($category, $page, $limit);
        $total = $itemRepository->countByCategory($category);

        $response = $this->json([
            'slot' => $slot,
            'category' => [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
            ],
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => (int) ceil($total / $limit),
            'items' => $items,
        ]);

        $response->setPublic();
        // max-age=0: browser must not cache (so X-Cache reflects Varnish each time)
        // s-maxage=3600: Varnish caches 1 hour (see docker/varnish/default.vcl)
        $response->headers->set('Cache-Control', 'public, max-age=0, s-maxage=3600');
        $response->headers->set('X-List-Slot', (string) $slot);
        $response->headers->set('X-Cache-Tag', 'list-'.$slot);

        return $response;
    }
}
