<?php

namespace App\Service;

/**
 * Varnish cache tags (space-separated on cached API responses).
 */
final class CacheTag
{
    public const API_PAGE_LIMIT = 10;

    public static function category(string $slug): string
    {
        return 'category-'.$slug;
    }

    public static function categoryPage(string $slug, int $page): string
    {
        return 'category-'.$slug.'-page-'.$page;
    }

    public static function list(int $slot): string
    {
        return 'list-'.$slot;
    }

    public static function listPage(int $slot, int $page): string
    {
        return 'list-'.$slot.'-page-'.$page;
    }

    /**
     * @return list<string>
     */
    public static function forCategoryApi(string $slug, int $page): array
    {
        return [
            self::category($slug),
            self::categoryPage($slug, $page),
        ];
    }

    /**
     * @return list<string>
     */
    public static function forListApi(int $slot, string $categorySlug, int $page): array
    {
        return [
            self::list($slot),
            self::listPage($slot, $page),
            self::category($categorySlug),
            self::categoryPage($categorySlug, $page),
        ];
    }

    public static function lastPage(int $totalItems, int $limit = self::API_PAGE_LIMIT): int
    {
        return max(1, (int) ceil($totalItems / max(1, $limit)));
    }

    /** Delimiter for X-Cache-Tags (must not be a Varnish regex metacharacter). */
    public const TAG_DELIMITER = '#';

    /**
     * Delimited value for X-Cache-Tags (safe for exact Varnish BAN matching).
     *
     * @param list<string> $tags
     */
    public static function headerValue(array $tags): string
    {
        $tags = array_values(array_filter($tags, static fn (string $t): bool => $t !== ''));

        return self::TAG_DELIMITER.implode(self::TAG_DELIMITER, $tags).self::TAG_DELIMITER;
    }
}
