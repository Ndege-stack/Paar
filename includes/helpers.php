<?php
/**
 * =====================================================================
 * PAAR — includes/helpers.php
 * ---------------------------------------------------------------------
 * Pure utility functions with no session/auth dependencies.
 * Safe to require from CLI scripts (cron.php) as well as web pages.
 * =====================================================================
 */

if (!function_exists('slots_for_medication')) {
    /**
     * Return the scheduled medication slots for a given medication on a
     * given date. Each slot has:
     *   idx     - slot index (0..n)
     *   label   - human label (Morning / Afternoon / Evening / Weekly dose)
     *   time    - HH:MM the reminder is queued for
     *   h_from  - inclusive hour-of-day where this slot's window begins
     *   h_to    - exclusive hour-of-day where this slot's window ends
     *
     * Returns [] when $date falls outside [start_date..end_date], or when
     * frequency=weekly and $date is not on the start_date's weekday.
     */
    function slots_for_medication(
        string $freq,
        string $startDate,
        string $endDate,
        string $date
    ): array {
        if ($date < $startDate || $date > $endDate) return [];

        switch ($freq) {
            case 'once_daily':
                return [
                    ['idx'=>0,'label'=>'Daily dose','time'=>'08:00','h_from'=>0,'h_to'=>24],
                ];
            case 'twice_daily':
                return [
                    ['idx'=>0,'label'=>'Morning','time'=>'08:00','h_from'=>0, 'h_to'=>14],
                    ['idx'=>1,'label'=>'Evening','time'=>'20:00','h_from'=>14,'h_to'=>24],
                ];
            case 'three_times_daily':
                return [
                    ['idx'=>0,'label'=>'Morning',   'time'=>'08:00','h_from'=>0, 'h_to'=>12],
                    ['idx'=>1,'label'=>'Afternoon', 'time'=>'14:00','h_from'=>12,'h_to'=>18],
                    ['idx'=>2,'label'=>'Evening',   'time'=>'20:00','h_from'=>18,'h_to'=>24],
                ];
            case 'weekly':
                if ((int) date('w', strtotime($startDate)) !== (int) date('w', strtotime($date))) {
                    return [];
                }
                return [
                    ['idx'=>0,'label'=>'Weekly dose','time'=>'08:00','h_from'=>0,'h_to'=>24],
                ];
        }
        return [];
    }
}
