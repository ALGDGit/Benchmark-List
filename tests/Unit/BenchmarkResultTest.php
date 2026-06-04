<?php

namespace App\Tests\Unit;

use App\Service\Benchmark\BenchmarkResult;
use PHPUnit\Framework\TestCase;

final class BenchmarkResultTest extends TestCase
{
    public function testPercentilesAndRps(): void
    {
        $result = new BenchmarkResult(
            'test',
            'Test',
            'http://example.test',
            4,
            2,
            0,
            2.0,
            0,
            [10.0, 20.0, 30.0, 40.0],
        );

        self::assertSame(2.0, $result->rps());
        self::assertSame(20.0, $result->percentile(50));
        self::assertSame(40.0, $result->percentile(95));
        self::assertSame(25.0, $result->meanMs());
    }
}
