/**
 * Client-side acronym detection for vendor names.
 *
 * This mirrors the backend AcronymDetector logic for immediate UI feedback.
 * The backend will still validate and format on submission.
 */

export interface AcronymDetectionResult {
  isLikely: boolean
  reason: string
  suggestedName: string
}

/**
 * Detect if a name is likely an acronym (e.g., HDA, TSD, 3M).
 *
 * @param name The name to analyze
 * @returns Detection result with suggestion
 */
export function detectAcronym(name: string): AcronymDetectionResult {
  const trimmed = name.trim()

  if (!trimmed) {
    return {
      isLikely: false,
      reason: 'Empty name',
      suggestedName: name,
    }
  }

  // Clean the name: remove periods, spaces, and common punctuation for analysis
  const cleaned = trimmed.replace(/[\.\s\-\_\/\\,]+/g, '')

  // Check length - acronyms are typically 2-6 characters
  const length = cleaned.length

  if (length < 2 || length > 6) {
    return {
      isLikely: false,
      reason: length < 2 ? 'Too short' : 'Too long for acronym',
      suggestedName: name,
    }
  }

  // Check if all alphanumeric (letters and/or numbers only)
  if (!/^[A-Za-z0-9]+$/.test(cleaned)) {
    return {
      isLikely: false,
      reason: 'Contains non-alphanumeric characters',
      suggestedName: name,
    }
  }

  // Check character patterns
  const hasLowercase = /[a-z]/.test(cleaned)
  const hasUppercase = /[A-Z]/.test(cleaned)
  const isAllUppercase = !hasLowercase && hasUppercase
  const isAllLowercase = hasLowercase && !hasUppercase

  // Check for period-separated format like H.D.A.
  const hasPeriodFormat = /^([A-Za-z]\.)+[A-Za-z]?$/.test(trimmed)

  // Check if name has multiple words (spaces in original)
  const hasMultipleWords = /\s/.test(trimmed)

  // If multiple words, it's not an acronym overall
  if (hasMultipleWords) {
    return {
      isLikely: false,
      reason: 'Multi-word name',
      suggestedName: name,
    }
  }

  // Patterns that indicate acronym:
  // 1. All uppercase letters only (HDA, TSD)
  if (isAllUppercase && /^[A-Z0-9]+$/.test(cleaned)) {
    return {
      isLikely: true,
      reason: 'All uppercase letters/numbers',
      suggestedName: cleaned.toUpperCase(),
    }
  }

  // 2. Period-separated format (H.D.A.)
  if (hasPeriodFormat) {
    return {
      isLikely: true,
      reason: 'Period-separated format',
      suggestedName: cleaned.toUpperCase(),
    }
  }

  // 3. All lowercase but short (2-4 chars) and all letters - likely meant to be acronym
  if (isAllLowercase && length <= 4 && /^[a-z]+$/.test(cleaned)) {
    return {
      isLikely: true,
      reason: 'Short lowercase (likely acronym)',
      suggestedName: cleaned.toUpperCase(),
    }
  }

  // 4. Letters with numbers, short length (like 3M, TSD1)
  if (/[0-9]/.test(cleaned) && /[A-Za-z]/.test(cleaned) && length <= 4) {
    return {
      isLikely: true,
      reason: 'Alphanumeric short form',
      suggestedName: cleaned.toUpperCase(),
    }
  }

  // Mixed case or doesn't match patterns - not an acronym
  return {
    isLikely: false,
    reason: 'Does not match acronym patterns',
    suggestedName: name,
  }
}

/**
 * Format a name based on whether it's an acronym.
 *
 * @param name The original name
 * @param isAcronym Whether to treat as acronym
 * @returns The formatted name
 */
export function formatVendorName(name: string, isAcronym: boolean): string {
  if (isAcronym) {
    // Remove periods and convert to uppercase
    const cleaned = name.replace(/[\.\s]+/g, '')
    return cleaned.toUpperCase()
  }

  // Non-acronym: return as-is (let user control casing)
  return name.trim()
}

/**
 * Vue composable for acronym detection.
 */
export function useAcronymDetector() {
  return {
    detectAcronym,
    formatVendorName,
  }
}

export default useAcronymDetector
