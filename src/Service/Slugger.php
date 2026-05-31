<?php

namespace App\Service;

final class Slugger
{
    public function slug(string $text): string
    {
        if (\function_exists('transliterator_transliterate')) {
            $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text) ?: $text;
        } else {
            $text = strtolower($text);
        }
        $text = preg_replace('/[^a-z0-9]+/', '-', strtolower($text)) ?? '';
        $text = trim($text, '-');

        return $text !== '' ? $text : 'item';
    }
}
