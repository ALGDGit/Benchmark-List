<?php

namespace App\EventSubscriber;

use App\Service\Activity\ActivityLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onResponse', -10],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/lists/')
            && !str_starts_with($path, '/api/categories/')
            && !str_starts_with($path, '/api/search/')) {
            return;
        }

        $response = $event->getResponse();

        if (str_starts_with($path, '/api/categories/')) {
            preg_match('#/api/categories/([a-z0-9\-]+)/items#', $path, $m);
            $slug = $m[1] ?? null;

            $this->activityLogger->log(
                'http',
                'category_api',
                sprintf('GET %s → origin (Varnish MISS)', $path),
                [
                    'method' => $request->getMethod(),
                    'path' => $path,
                    'query' => $request->query->all(),
                    'status' => $response->getStatusCode(),
                    'x_cache' => 'MISS',
                    'x_cache_tags' => $response->headers->get('X-Cache-Tags'),
                    'category_slug' => $slug,
                ]
            );

            return;
        }

        if (str_starts_with($path, '/api/lists/')) {
            preg_match('#/api/lists/(\d)#', $path, $m);
            $slot = isset($m[1]) ? (int) $m[1] : null;

            $this->activityLogger->log(
                'http',
                'list_api',
                sprintf('GET %s → origin (Varnish MISS)', $path),
                [
                    'method' => $request->getMethod(),
                    'path' => $path,
                    'query' => $request->query->all(),
                    'status' => $response->getStatusCode(),
                    'x_cache' => 'MISS',
                    'x_cache_tags' => $response->headers->get('X-Cache-Tags'),
                    'slot' => $slot,
                ]
            );

            return;
        }

        $q = (string) $request->query->get('q', '');
        if (mb_strlen(trim($q)) < 2) {
            return;
        }

        $this->activityLogger->log(
            'http',
            'search_api',
            sprintf('Autocomplete search: "%s"', mb_substr(trim($q), 0, 40)),
            [
                'query' => $q,
                'path' => $path,
                'status' => $response->getStatusCode(),
            ]
        );
    }
}
