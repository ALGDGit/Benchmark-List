<?php

namespace App\Service;

/**
 * Feature toggles for benchmark comparisons (see docs/DECISIONS.md).
 */
final class FeatureFlags
{
    public function __construct(
        private readonly bool $elasticsearchEnabled,
        private readonly bool $redisActivityEnabled,
    ) {
    }

    public function isElasticsearchEnabled(): bool
    {
        return $this->elasticsearchEnabled;
    }

    public function isRedisActivityEnabled(): bool
    {
        return $this->redisActivityEnabled;
    }
}
