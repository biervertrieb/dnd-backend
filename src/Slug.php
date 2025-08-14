<?php
namespace App\Util;

final class Slug
{
    public static function make(string $text): string
    {
        $text = strtolower($text);
        // replace non letters/digits with hyphens
        $text = preg_replace('~[^\p{L}\p{Nd}]+~u', '-', $text);
        // transliterate accents if intl/Normalizer is available
        if (class_exists('\Transliterator')) {
            $tr = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
            if ($tr)
                $text = $tr->transliterate($text);
        }
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        return $text ?: 'note';
    }
}
