/**
 * Date utilities composable for consistent Eastern Time handling
 *
 * All times in the application are stored and displayed in Eastern Time (America/New_York)
 * This ensures consistency regardless of user's browser timezone
 */

const TIMEZONE = 'America/New_York'

/**
 * Format a date string for display
 * @param dateString - ISO date string or date portion (YYYY-MM-DD)
 * @param options - Intl.DateTimeFormatOptions
 */
export function formatDate(
  dateString: string | null | undefined,
  options: Intl.DateTimeFormatOptions = {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }
): string {
  if (!dateString) return ''

  try {
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', {
      ...options,
      timeZone: TIMEZONE,
    })
  } catch {
    return dateString
  }
}

/**
 * Format a short date (MM/DD/YYYY)
 */
export function formatShortDate(dateString: string | null | undefined): string {
  return formatDate(dateString, {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  })
}

/**
 * Format a time string for display (12-hour format with AM/PM)
 * @param timeString - Time string in various formats (HH:mm, HH:mm:ss, or full datetime)
 */
export function formatTime(timeString: string | null | undefined): string {
  if (!timeString) return ''

  try {
    // Handle full datetime strings
    if (timeString.includes('T') || timeString.includes(' ')) {
      const date = new Date(timeString)
      return date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
        timeZone: TIMEZONE,
      })
    }

    // Handle time-only strings (HH:mm or HH:mm:ss)
    const [hours, minutes] = timeString.split(':')
    const hour = parseInt(hours, 10)
    const ampm = hour >= 12 ? 'PM' : 'AM'
    const displayHour = hour % 12 || 12
    return `${displayHour}:${minutes} ${ampm}`
  } catch {
    return timeString
  }
}

/**
 * Format a datetime for display
 */
export function formatDateTime(
  dateString: string | null | undefined,
  options: Intl.DateTimeFormatOptions = {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  }
): string {
  if (!dateString) return ''

  try {
    const date = new Date(dateString)
    return date.toLocaleString('en-US', {
      ...options,
      timeZone: TIMEZONE,
    })
  } catch {
    return dateString
  }
}

/**
 * Get today's date in YYYY-MM-DD format (Eastern Time)
 */
export function getTodayString(): string {
  const now = new Date()
  // Get the date parts in Eastern Time
  const formatter = new Intl.DateTimeFormat('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    timeZone: TIMEZONE,
  })
  return formatter.format(now)
}

/**
 * Get current time in HH:mm format (Eastern Time)
 */
export function getCurrentTimeString(): string {
  const now = new Date()
  const formatter = new Intl.DateTimeFormat('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: TIMEZONE,
  })
  return formatter.format(now)
}

/**
 * Check if a date is today (Eastern Time)
 */
export function isToday(dateString: string | null | undefined): boolean {
  if (!dateString) return false
  return formatShortDate(dateString) === formatShortDate(new Date().toISOString())
}

/**
 * Format relative time (e.g., "2 hours ago", "in 3 days")
 */
export function formatRelativeTime(dateString: string | null | undefined): string {
  if (!dateString) return ''

  try {
    const date = new Date(dateString)
    const now = new Date()
    const diffMs = date.getTime() - now.getTime()
    const diffMins = Math.round(diffMs / 60000)
    const diffHours = Math.round(diffMs / 3600000)
    const diffDays = Math.round(diffMs / 86400000)

    if (Math.abs(diffMins) < 60) {
      if (diffMins === 0) return 'now'
      return diffMins > 0 ? `in ${diffMins} min` : `${Math.abs(diffMins)} min ago`
    }

    if (Math.abs(diffHours) < 24) {
      return diffHours > 0 ? `in ${diffHours} hours` : `${Math.abs(diffHours)} hours ago`
    }

    return diffDays > 0 ? `in ${diffDays} days` : `${Math.abs(diffDays)} days ago`
  } catch {
    return dateString
  }
}

/**
 * Composable hook for date utilities
 */
export function useDateUtils() {
  return {
    TIMEZONE,
    formatDate,
    formatShortDate,
    formatTime,
    formatDateTime,
    getTodayString,
    getCurrentTimeString,
    isToday,
    formatRelativeTime,
  }
}

export default useDateUtils
