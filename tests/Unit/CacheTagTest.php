<?php

namespace App\Tests\Unit;

use App\Service\CacheTag;
use PHPUnit\Framework\TestCase;

final class CacheTagTest extends TestCase
{
    public function testCategoryTag(): void
    {
        self::assertSame('category-sopas', CacheTag::category('sopas'));
    }

    public function testCategoryPageTag(): void
    {
        self::assertSame('category-sopas-page-3', CacheTag::categoryPage('sopas', 3));
    }

    public function testLastPage(): void
    {
        self::assertSame(1, CacheTag::lastPage(0));
        self::assertSame(1, CacheTag::lastPage(10));
        self::assertSame(2, CacheTag::lastPage(11));
        self::assertSame(3, CacheTag::lastPage(25));
    }

    public function testHeaderValueUsesHashDelimiters(): void
    {
        $value = CacheTag::headerValue(['category-sopas', 'category-sopas-page-1']);
        self::assertSame('#category-sopas#category-sopas-page-1#', $value);
    }

    public function testListApiTagsIncludeCategoryAndPage(): void
    {
        $tags = CacheTag::forListApi(1, 'sopas', 2);
        self::assertContains('list-1', $tags);
        self::assertContains('list-1-page-2', $tags);
        self::assertContains('category-sopas', $tags);
        self::assertContains('category-sopas-page-2', $tags);
    }
}
