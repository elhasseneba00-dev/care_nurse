<?php

namespace App\Services;

class ChatModeration
{
    /**
     * Returns [bool $flagged, string $maskedText, array $matches]
     */
    public static function detectAndMask(string $text): array
    {
        $original = $text;
        $matches = [];

        // Phone numbers: sequences of 8-15 digits (allow spaces, +, -, parentheses)
        $phoneRegex = '/(\+?\s*(?:\d[\s\-\(\)]?){8,15}\d)/u';

        // WhatsApp keywords / links
        $whatsRegex = '/\b(whatsapp|واتساب|wa\.me|api\.whatsapp\.com)\b/iu';

        // Email
        $emailRegex = '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu';

        // Mask phones
        if (preg_match_all($phoneRegex, $text, $m)) {
            $matches['phones'] = $m[0];
            $text = preg_replace($phoneRegex, ' [***contact masqué***]', $text);
        }

        // Mask emails
        if (preg_match_all($emailRegex, $text, $m)) {
            $matches['emails'] = $m[0];
            $text = preg_replace($emailRegex, '[***contact masqué***]', $text);
        }

        // Flag WhatsApp mentions (mask the keyword to reduce hints)
        if (preg_match_all($whatsRegex, $text, $m)) {
            $matches['whatsapp'] = $m[0];
            $text = preg_replace($whatsRegex, '[***contact masqué***]', $text);
        }

        $flagged = $text !== $original;

        return [$flagged, $text, $matches];
    }
}
