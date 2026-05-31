<?php

namespace App\Controller;

use App\Service\Activity\ActivityLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ActivityPanelController extends AbstractController
{
    #[Route('/panel', name: 'app_activity_panel')]
    public function panel(ActivityLogger $activityLogger): Response
    {
        return $this->render('activity/panel.html.twig', [
            'entries' => $activityLogger->getRecent(80),
            'total' => $activityLogger->count(),
        ]);
    }

    #[Route('/api/activity', name: 'api_activity', methods: ['GET'])]
    public function api(ActivityLogger $activityLogger, Request $request): JsonResponse
    {
        $limit = max(1, min(200, $request->query->getInt('limit', 50)));

        return $this->json([
            'total' => $activityLogger->count(),
            'entries' => $activityLogger->getRecent($limit),
        ]);
    }

    /**
     * Log Varnish HITs reported by the browser (cached responses never reach PHP).
     */
    #[Route('/api/activity/cache-hit', name: 'api_activity_cache_hit', methods: ['POST'])]
    public function cacheHit(Request $request, ActivityLogger $activityLogger): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['ok' => false], Response::HTTP_BAD_REQUEST);
        }

        $path = (string) ($payload['path'] ?? '');
        if (!str_starts_with($path, '/api/lists/')
            && !str_starts_with($path, '/api/categories/')) {
            return $this->json(['ok' => false], Response::HTTP_BAD_REQUEST);
        }

        $action = str_starts_with($path, '/api/lists/') ? 'list_api' : 'category_api';
        $context = [
            'path' => $path,
            'x_cache' => 'HIT',
        ];

        if (preg_match('#/api/lists/(\d)#', $path, $m)) {
            $context['slot'] = (int) $m[1];
        }
        if (preg_match('#/api/categories/([a-z0-9\-]+)/items#', $path, $m)) {
            $context['category_slug'] = $m[1];
        }

        $activityLogger->log(
            'http',
            $action,
            sprintf('GET %s → Varnish HIT', $path),
            $context,
        );

        return $this->json(['ok' => true]);
    }

    #[Route('/panel/clear', name: 'app_activity_clear', methods: ['POST'])]
    public function clear(Request $request, ActivityLogger $activityLogger): Response
    {
        if (!$this->isCsrfTokenValid('clear-activity', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_activity_panel');
        }

        $activityLogger->clear();
        $activityLogger->log('system', 'clear', 'Activity log cleared manually');

        $this->addFlash('success', 'Activity log cleared.');

        return $this->redirectToRoute('app_activity_panel');
    }
}
