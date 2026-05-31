<?php

namespace App\Controller\Api;

use App\Service\Elasticsearch\ItemSearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/search')]
final class SearchApiController extends AbstractController
{
    #[Route('/autocomplete', name: 'api_search_autocomplete', methods: ['GET'])]
    public function autocomplete(Request $request, ItemSearchService $searchService): JsonResponse
    {
        $q = (string) $request->query->get('q', '');
        $results = $searchService->autocomplete($q);

        return $this->json([
            'query' => $q,
            'results' => $results,
        ]);
    }
}
