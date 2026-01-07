<?php

namespace App\Services;

/**
 * Detects whether a vendor name is likely an acronym (e.g., HDA, TSD, 3M).
 *
 * Acronyms should be stored in ALL CAPS for consistency.
 * Non-acronyms should be stored in their original case (Title Case).
 */
class AcronymDetector
{
    /**
     * Analyze a name and determine if it's likely an acronym.
     *
     * @param string $name The name to analyze
     * @return array{isLikely: bool, reason: string, suggestedName: string}
     */
    public function detect(string $name): array
    {
        $name = trim($name);

        if (empty($name)) {
            return [
                'isLikely' => false,
                'reason' => 'Empty name',
                'suggestedName' => $name,
            ];
        }

        // Clean the name: remove periods, spaces, and common punctuation for analysis
        $cleaned = preg_replace('/[\.\s\-\_\/\\\\,]+/', '', $name);

        // Check length - acronyms are typically 2-6 characters
        $length = strlen($cleaned);

        if ($length < 2 || $length > 6) {
            return [
                'isLikely' => false,
                'reason' => $length < 2 ? 'Too short' : 'Too long for acronym',
                'suggestedName' => $name,
            ];
        }

        // Check if all alphanumeric (letters and/or numbers only)
        if (!preg_match('/^[A-Za-z0-9]+$/', $cleaned)) {
            return [
                'isLikely' => false,
                'reason' => 'Contains non-alphanumeric characters',
                'suggestedName' => $name,
            ];
        }

        // Check if it contains any lowercase letters in the cleaned form
        $hasLowercase = preg_match('/[a-z]/', $cleaned);
        $hasUppercase = preg_match('/[A-Z]/', $cleaned);
        $isAllUppercase = !$hasLowercase && $hasUppercase;
        $isAllLowercase = $hasLowercase && !$hasUppercase;

        // Check for period-separated format like H.D.A.
        $hasPeriodFormat = preg_match('/^([A-Za-z]\.)+[A-Za-z]?$/', $name);

        // Check if name has multiple words (spaces in original)
        $hasMultipleWords = preg_match('/\s/', trim($name));

        // If multiple words, check if first "word" looks like an acronym
        if ($hasMultipleWords) {
            $words = preg_split('/\s+/', trim($name));
            $firstWord = $words[0];
            $firstWordCleaned = preg_replace('/[\.\-\_]+/', '', $firstWord);

            // If first word is short uppercase and followed by normal words, not an acronym overall
            // e.g., "HD Supply" - first word HD looks like acronym but whole thing is not
            if (count($words) > 1) {
                return [
                    'isLikely' => false,
                    'reason' => 'Multi-word name',
                    'suggestedName' => $name,
                ];
            }
        }

        // Patterns that indicate acronym:
        // 1. All uppercase letters only (HDA, TSD)
        // 2. Period-separated format (H.D.A.)
        // 3. Letters with numbers (TSD1, 3M)
        // 4. All lowercase but short (user typed "hda" meaning "HDA")

        // All uppercase letters only
        if ($isAllUppercase && preg_match('/^[A-Z0-9]+$/', $cleaned)) {
            return [
                'isLikely' => true,
                'reason' => 'All uppercase letters/numbers',
                'suggestedName' => strtoupper($cleaned),
            ];
        }

        // Period-separated format
        if ($hasPeriodFormat) {
            return [
                'isLikely' => true,
                'reason' => 'Period-separated format',
                'suggestedName' => strtoupper($cleaned),
            ];
        }

        // All lowercase but short (2-4 chars) and all letters - likely meant to be acronym
        if ($isAllLowercase && $length <= 4 && preg_match('/^[a-z]+$/', $cleaned)) {
            return [
                'isLikely' => true,
                'reason' => 'Short lowercase (likely acronym)',
                'suggestedName' => strtoupper($cleaned),
            ];
        }

        // Letters with numbers, short length (like 3M, TSD1)
        if (preg_match('/[0-9]/', $cleaned) && preg_match('/[A-Za-z]/', $cleaned) && $length <= 4) {
            return [
                'isLikely' => true,
                'reason' => 'Alphanumeric short form',
                'suggestedName' => strtoupper($cleaned),
            ];
        }

        // Mixed case or doesn't match patterns - not an acronym
        return [
            'isLikely' => false,
            'reason' => 'Does not match acronym patterns',
            'suggestedName' => $name,
        ];
    }

    /**
     * Format a name based on whether it's an acronym.
     *
     * @param string $name The original name
     * @param bool $isAcronym Whether to treat as acronym
     * @return string The formatted name
     */
    public function formatName(string $name, bool $isAcronym): string
    {
        if ($isAcronym) {
            // Remove periods and convert to uppercase
            $cleaned = preg_replace('/[\.\s]+/', '', $name);
            return strtoupper($cleaned);
        }

        // Non-acronym: return as-is (let user control casing)
        return trim($name);
    }

    /**
     * Check if an existing name appears to be stored as an acronym.
     * Useful for determining is_acronym flag for existing vendors.
     *
     * @param string $name The stored name
     * @return bool
     */
    public function looksLikeStoredAcronym(string $name): bool
    {
        $name = trim($name);
        $length = strlen($name);

        // Short (2-6 chars), all uppercase, no spaces
        if ($length >= 2 && $length <= 6 && !preg_match('/\s/', $name) && preg_match('/^[A-Z0-9]+$/', $name)) {
            return true;
        }

        return false;
    }
}
