<?php

namespace App\Services;

use App\Models\ClosedDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BusinessCalendarService
{
    /**
     * Check if a specific date is closed for business
     */
    public function isDateClosed(Carbon $date): bool
    {
        return ClosedDate::isDateClosed($date);
    }

    /**
     * Get the closed date record for a specific date (if exists)
     */
    public function getClosedDate(Carbon $date): ?ClosedDate
    {
        return ClosedDate::getForDate($date);
    }

    /**
     * Get all closed dates within a date range
     */
    public function getClosedDatesForRange(Carbon $start, Carbon $end): Collection
    {
        return ClosedDate::inRange($start, $end)->orderBy('date')->get();
    }

    /**
     * Get all closed dates for a specific year
     */
    public function getClosedDatesForYear(int $year): Collection
    {
        return ClosedDate::forYear($year)->orderBy('date')->get();
    }

    /**
     * Get upcoming closed dates
     */
    public function getUpcomingClosedDates(int $limit = 10): Collection
    {
        return ClosedDate::upcoming()->limit($limit)->get();
    }

    /**
     * Check if a date is a business day (not closed and not weekend)
     * Note: This checks closed dates only. Day-of-week logic is handled by RouteSchedule.
     */
    public function isBusinessDay(Carbon $date): bool
    {
        // Check if it's a closed date
        if ($this->isDateClosed($date)) {
            return false;
        }

        return true;
    }

    /**
     * Get the next business day after a given date
     */
    public function getNextBusinessDay(Carbon $date): Carbon
    {
        $nextDay = $date->copy()->addDay();

        while (!$this->isBusinessDay($nextDay)) {
            $nextDay->addDay();
        }

        return $nextDay;
    }
}
