<?php

namespace App\Tests\Unit;

use App\Entity\ItemListPosition;
use PHPUnit\Framework\TestCase;

final class ItemListPositionTest extends TestCase
{
    public function testLabels(): void
    {
        self::assertSame('Beginning', ItemListPosition::First->label());
        self::assertSame('End', ItemListPosition::Last->label());
    }
}
