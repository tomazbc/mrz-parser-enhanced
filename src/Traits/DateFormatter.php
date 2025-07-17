<?php

namespace Tomazbc\MrzParserEnhanced\Traits;

use DateTime;

trait DateFormatter
{
    /**
     * Format date from YYMMDD
     *
     * @param string $date
     * @param string $format
     *
     * @return null|string
     */
    public function formatDate($date, $format = "Y-m-d"): ?string
    {
        if ($this->validateDateFormat($date)) {
            $year = substr($date, 0, 2);
            $month = substr($date, 2, 2);
            $day = substr($date, 4, 2);
            
            // Apply MRZ century logic (00-30 = 2000-2030, 31-99 = 1931-1999)
            // This fixes the issue where PHP's DateTime 'y' format uses wrong cutoff
            $fullYear = (int)$year <= 30 ? '20' . $year : '19' . $year;
            
            $dateTime = DateTime::createFromFormat('Y-m-d', $fullYear . '-' . $month . '-' . $day);

            return $dateTime ? $dateTime->format($format) : null;
        }

        return null;
    }

    /**
     * Validate Date Format YYMMDD
     * MM range must be 01-12
     * DD range must be 01-31
     *
     * @param string $date
     * @return bool
     */
    public function validateDateFormat(string $date): bool
    {
        $month = (int) substr($date, 2, 2);
        $date = (int) substr($date, 4, 2);

        return $month >= 1 && $month <= 12 && $date >= 1 && $date <= 31;
    }
}
