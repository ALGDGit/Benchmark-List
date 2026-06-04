<?php

namespace App\Controller\Api;

use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Service\CacheTag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/categories')]
final class CategoryApiController extends AbstractController
{
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 100;

    #[Route('/{slug}/items', name: 'api_category_items', requirements: ['slug' => '[a-z0-9\-]+'], methods: ['GET'])]
    public function items(
        string $slug,
        Request $request,
        CategoryRepository $categoryRepository,
        ItemRepository $itemRepository,
    ): JsonResponse {
        $category = $categoryRepository->findOneBySlug($slug);
        if ($category === null) {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(self::MAX_LIMIT, max(1, $request->query->getInt('limit', self::DEFAULT_LIMIT)));

        $items = $itemRepository->findPageByCategory($category, $page, $limit);
        $total = $itemRepository->countByCategory($category);

        $response = $this->json([
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
        $response->headers->set('Cache-Control', 'public, max-age=0, s-maxage=3600');
        $response->headers->set('X-Cache-Tags', CacheTag::headerValue(CacheTag::forCategoryApi($slug, $page)));

        return $response;
    }
}
