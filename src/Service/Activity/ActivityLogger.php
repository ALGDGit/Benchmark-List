<?php

namespace App\Service\Activity;

use Predis\Client as PredisClient;

/**
 * Benchmark activity log (Redis + file fallback in var/).
 */
final class ActivityLogger
{
    private const REDIS_KEY = 'benchmark:activity';
    private const MAX_ENTRIES = 200;

    private ?PredisClient $redis = null;

    public function __construct(
        private readonly string $redisUrl,
        private readonly string $projectDir,
    ) {
    }

    public function log(string $source, string $action, string $message, array $context = []): void
    {
        $entry = [
            'id' => bin2hex(random_bytes(8)),
            'at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'source' => $source,
            'action' => $action,
            'message' => $message,
            'context' => $context,
        ];

        $encoded = json_encode($entry, JSON_THROW_ON_ERROR);

        if ($this->pushToRedis($encoded)) {
            return;
        }

        $this->pushToFile($entry);
    }

    /**
     * @return list<array{id: string, at: string, source: string, action: string, message: string, context: array}>
     */
    public function getRecent(int $limit = 80): array
    {
        $limit = max(1, min($limit, self::MAX_ENTRIES));

        $fromRedis = $this->getFromRedis($limit);
        if ($fromRedis !== []) {
            return $fromRedis;
        }

        return array_slice($this->getFromFile(), 0, $limit);
    }

    public function clear(): void
    {
        try {
            $this->client()->del([self::REDIS_KEY]);
        } catch (\Throwable) {
        }

        $path = $this->filePath();
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function count(): int
    {
        try {
            return (int) $this->client()->llen(self::REDIS_KEY);
        } catch (\Throwable) {
            return count($this->getFromFile());
        }
    }

    private function pushToRedis(string $encoded): bool
    {
        try {
            $client = $this->client();
            $client->lpush(self::REDIS_KEY, [$encoded]);
            $client->ltrim(self::REDIS_KEY, 0, self::MAX_ENTRIES - 1);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function getFromRedis(int $limit): array
    {
        try {
            $raw = $this->client()->lrange(self::REDIS_KEY, 0, $limit - 1);
            if ($raw === []) {
                return [];
            }

            return array_values(array_filter(array_map(
                static function (mixed $line): ?array {
                    if (!is_string($line)) {
                        return null;
                    }
                    $decoded = json_decode($line, true);

                    return is_array($decoded) ? $decoded : null;
                },
                $raw
            )));
        } catch (\Throwable) {
            return [];
        }
    }

    private function pushToFile(array $entry): void
    {
        $entries = $this->getFromFile();
        array_unshift($entries, $entry);
        $entries = array_slice($entries, 0, self::MAX_ENTRIES);

        $dir = dirname($this->filePath());
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $this->filePath(),
            json_encode($entries, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
        );
    }

    /**
     * @return list<array>
     */
    private function getFromFile(): array
    {
        $path = $this->filePath();
        if (!is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    private function filePath(): string
    {
        return $this->projectDir.'/var/activity_log.json';
    }

    private function client(): PredisClient
    {
        if ($this->redis === null) {
            $this->redis = new PredisClient($this->redisUrl);
        }

        return $this->redis;
    }
}
