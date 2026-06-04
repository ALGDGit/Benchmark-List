<?php

namespace App\Controller\Api;

use App\Service\Elasticsearch\ItemSearchService;
use App\Service\FeatureFlags;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/search')]
final class SearchApiController extends AbstractController
{
    #[Route('/autocomplete', name: 'api_search_autocomplete', methods: ['GET'])]
    public function autocomplete(
        Request $request,
        ItemSearchService $searchService,
        FeatureFlags $features,
    ): JsonResponse {
        $q = (string) $request->query->get('q', '');

        if (!$features->isElasticsearchEnabled()) {
            return $this->json([
                'query' => $q,
                'results' => [],
                'disabled' => true,
                'message' => 'Elasticsearch disabled (FEATURE_ELASTICSEARCH=0)',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $results = $searchService->autocomplete($q);

        return $this->json([
            'query' => $q,
            'results' => $results,
        ]);
    }
}
