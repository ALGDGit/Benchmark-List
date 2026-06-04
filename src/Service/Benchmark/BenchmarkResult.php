<?php

namespace App\Service\Benchmark;

final class BenchmarkResult
{
    /**
     * @param list<float> $latenciesMs
     */
    public function __construct(
        public readonly string $scenarioId,
        public readonly string $name,
        public readonly string $url,
        public readonly int $requests,
        public readonly int $concurrency,
        public readonly int $warmup,
        public readonly float $durationSec,
        public readonly int $errors,
        public readonly array $latenciesMs,
    ) {
    }

    public function rps(): float
    {
        if ($this->durationSec <= 0) {
            return 0.0;
        }

        return ($this->requests - $this->errors) / $this->durationSec;
    }

    public function percentile(float $p): float
    {
        if ($this->latenciesMs === []) {
            return 0.0;
        }

        $index = (int) ceil(($p / 100) * count($this->latenciesMs)) - 1;
        $index = max(0, min($index, count($this->latenciesMs) - 1));

        return $this->latenciesMs[$index];
    }

    public function meanMs(): float
    {
        if ($this->latenciesMs === []) {
            return 0.0;
        }

        return array_sum($this->latenciesMs) / count($this->latenciesMs);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scenario_id' => $this->scenarioId,
            'name' => $this->name,
            'url' => $this->url,
            'requests' => $this->requests,
            'concurrency' => $this->concurrency,
            'warmup' => $this->warmup,
            'duration_sec' => round($this->durationSec, 3),
            'errors' => $this->errors,
            'rps' => round($this->rps(), 1),
            'latency_ms' => [
                'mean' => round($this->meanMs(), 2),
                'p50' => round($this->percentile(50), 2),
                'p95' => round($this->percentile(95), 2),
                'p99' => round($this->percentile(99), 2),
                'max' => round($this->latenciesMs !== [] ? max($this->latenciesMs) : 0, 2),
            ],
        ];
    }
}
