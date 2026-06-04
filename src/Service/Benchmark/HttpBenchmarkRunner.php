<?php

namespace App\Service\Benchmark;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpBenchmarkRunner
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function run(
        string $scenarioId,
        string $name,
        string $url,
        int $requests,
        int $concurrency,
        int $warmup = 0,
    ): BenchmarkResult {
        $requests = max(1, $requests);
        $concurrency = max(1, min($concurrency, $requests));

        for ($i = 0; $i < max(0, $warmup); ++$i) {
            try {
                $this->httpClient->request('GET', $url, ['timeout' => 30])->getContent();
            } catch (\Throwable) {
            }
        }

        $latencies = [];
        $errors = 0;
        $started = hrtime(true);
        $remaining = $requests;

        while ($remaining > 0) {
            $batchSize = min($concurrency, $remaining);
            $responses = [];
            $starts = [];

            for ($i = 0; $i < $batchSize; ++$i) {
                $starts[] = hrtime(true);
                try {
                    $responses[] = $this->httpClient->request('GET', $url, ['timeout' => 30]);
                } catch (\Throwable) {
                    ++$errors;
                    $responses[] = null;
                }
            }

            foreach ($responses as $idx => $response) {
                if ($response === null) {
                    continue;
                }
                try {
                    $response->getStatusCode();
                    $response->getContent();
                    $latencies[] = (hrtime(true) - $starts[$idx]) / 1_000_000;
                } catch (\Throwable) {
                    ++$errors;
                }
            }

            $remaining -= $batchSize;
        }

        sort($latencies);

        return new BenchmarkResult(
            $scenarioId,
            $name,
            $url,
            $requests,
            $concurrency,
            $warmup,
            (hrtime(true) - $started) / 1_000_000_000,
            $errors,
            $latencies,
        );
    }
}
