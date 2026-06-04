<?php

namespace App\Service\Benchmark;

final class BenchmarkReportWriter
{
    /**
     * @param list<BenchmarkResult> $results
     */
    public function writeJson(string $path, array $results, array $meta = []): void
    {
        $payload = [
            'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'meta' => $meta,
            'scenarios' => array_map(static fn (BenchmarkResult $r): array => $r->toArray(), $results),
        ];

        $this->ensureDir(dirname($path));
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }

    /**
     * @param list<BenchmarkResult> $results
     */
    public function writeMarkdown(string $path, array $results, array $meta = []): void
    {
        $lines = [
            '# Benchmark results',
            '',
            sprintf('Generated: **%s**', (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)),
            '',
        ];

        if ($meta !== []) {
            $lines[] = '## Environment';
            $lines[] = '';
            foreach ($meta as $key => $value) {
                $lines[] = sprintf('- **%s:** %s', $key, is_scalar($value) ? (string) $value : json_encode($value));
            }
            $lines[] = '';
        }

        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = '| Scenario | RPS | p50 (ms) | p95 (ms) | p99 (ms) | Errors |';
        $lines[] = '|----------|-----|----------|----------|----------|--------|';

        foreach ($results as $result) {
            $data = $result->toArray();
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s | %d |',
                $data['name'],
                $data['rps'],
                $data['latency_ms']['p50'],
                $data['latency_ms']['p95'],
                $data['latency_ms']['p99'],
                $data['errors'],
            );
        }

        $lines[] = '';
        $lines[] = '## Details';
        $lines[] = '';

        foreach ($results as $result) {
            $data = $result->toArray();
            $lines[] = sprintf('### %s', $data['name']);
            $lines[] = '';
            $lines[] = sprintf('- URL: `%s`', $data['url']);
            $lines[] = sprintf('- Requests: %d (concurrency %d, warmup %d)', $data['requests'], $data['concurrency'], $data['warmup']);
            $lines[] = sprintf('- Duration: %ss', $data['duration_sec']);
            $lines[] = sprintf('- Mean latency: %sms', $data['latency_ms']['mean']);
            $lines[] = '';
        }

        $this->ensureDir(dirname($path));
        file_put_contents($path, implode("\n", $lines)."\n");
    }

    private function ensureDir(string $dir): void
    {
        if ($dir !== '' && !is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}
