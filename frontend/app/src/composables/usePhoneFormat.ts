/**
 * Phone number formatting utility
 * Formats phone numbers as (xxx)xxx-xxxx
 */

/**
 * Format a phone number string as (xxx)xxx-xxxx
 * @param value - Raw phone number string (can contain any characters)
 * @returns Formatted phone number string
 */
export function formatPhoneNumber(value: string | null | undefined): string {
  if (!value) return '';

  // Remove all non-digit characters
  const digits = value.replace(/\D/g, '');

  // Format as (xxx)xxx-xxxx
  if (digits.length === 0) {
    return '';
  } else if (digits.length <= 3) {
    return `(${digits}`;
  } else if (digits.length <= 6) {
    return `(${digits.slice(0, 3)})${digits.slice(3)}`;
  } else {
    return `(${digits.slice(0, 3)})${digits.slice(3, 6)}-${digits.slice(6, 10)}`;
  }
}

/**
 * Extract raw digits from a formatted phone number
 * @param value - Formatted phone number string
 * @returns Raw digits only
 */
export function unformatPhoneNumber(value: string | null | undefined): string {
  if (!value) return '';
  return value.replace(/\D/g, '');
}

/**
 * Composable for phone number formatting in forms
 * Returns reactive formatting functions for use with v-model
 */
export function usePhoneFormat() {
  return {
    formatPhoneNumber,
    unformatPhoneNumber,
  };
}
