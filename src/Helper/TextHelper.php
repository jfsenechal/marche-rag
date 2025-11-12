<?php

namespace App\Helper;

class TextHelper
{
    public static function truncateContent(string $content, int $maxChars = 30000): string
    {
        // Truncate if too long (rough estimate: 1 token ≈ 4 chars, limit ~8000 tokens)
        if (strlen($content) > $maxChars) {
            $content = substr($content, 0, $maxChars);
        }

        return $content;
    }
}
