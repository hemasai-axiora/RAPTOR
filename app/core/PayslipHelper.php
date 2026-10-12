<?php
/**
 * Helper class for Payslip formatting, Indian currency conversions, and calculations.
 */
class PayslipHelper {

    /**
     * Convert numeric amount into Indian Currency Words (e.g., "Twenty-Five Thousand Four Hundred").
     */
    public static function numberToWords(float $amount): string {
        $amount = round($amount, 2);
        $rupees = (int)floor($amount);
        $paise = (int)round(($amount - $rupees) * 100);

        $words = [
            0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
            5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
            10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
            14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen',
            18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
            40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
            80 => 'Eighty', 90 => 'Ninety'
        ];

        if ($rupees === 0) {
            $rupeesWords = 'Zero';
        } else {
            $crores = (int)floor($rupees / 10000000);
            $rupees %= 10000000;
            $lakhs = (int)floor($rupees / 100000);
            $rupees %= 100000;
            $thousands = (int)floor($rupees / 1000);
            $rupees %= 1000;
            $hundreds = (int)floor($rupees / 100);
            $remainder = $rupees % 100;

            $parts = [];

            if ($crores > 0) {
                $parts[] = self::twoDigitToWords($crores, $words) . ' Crore';
            }
            if ($lakhs > 0) {
                $parts[] = self::twoDigitToWords($lakhs, $words) . ' Lakh';
            }
            if ($thousands > 0) {
                $parts[] = self::twoDigitToWords($thousands, $words) . ' Thousand';
            }
            if ($hundreds > 0) {
                $parts[] = self::twoDigitToWords($hundreds, $words) . ' Hundred';
            }
            if ($remainder > 0) {
                $parts[] = self::twoDigitToWords($remainder, $words);
            }

            $rupeesWords = implode(' ', array_filter($parts));
        }

        $result = $rupeesWords;
        if ($paise > 0) {
            $result .= ' and ' . self::twoDigitToWords($paise, $words) . ' Paise';
        }

        return trim($result);
    }

    private static function twoDigitToWords(int $num, array $words): string {
        if ($num < 20) {
            return $words[$num] ?? '';
        }
        $tens = (int)floor($num / 10) * 10;
        $units = $num % 10;
        return trim(($words[$tens] ?? '') . ' ' . ($words[$units] ?? ''));
    }

    /**
     * Format a month string (YYYY-MM) into Human Month Year (e.g., "October 2026").
     */
    public static function formatMonthYear(string $monthYear): string {
        if (preg_match('/^(\d{4})-(\d{2})$/', $monthYear, $m)) {
            $ts = mktime(0, 0, 0, (int)$m[2], 1, (int)$m[1]);
            return date('F Y', $ts);
        }
        return $monthYear;
    }

    /**
     * Calculate pay period start and end dates for a month (e.g., "01/10/2026 - 31/10/2026").
     */
    public static function getPayPeriod(string $monthYear): string {
        if (preg_match('/^(\d{4})-(\d{2})$/', $monthYear, $m)) {
            $year = (int)$m[1];
            $month = (int)$m[2];
            $daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
            return sprintf("01/%02d/%04d - %02d/%02d/%04d", $month, $year, $daysInMonth, $month, $year);
        }
        return $monthYear;
    }

    /**
     * Calculate standard pay release date (last day of the month or 1st of next month).
     */
    public static function getPayDate(string $monthYear): string {
        if (preg_match('/^(\d{4})-(\d{2})$/', $monthYear, $m)) {
            $year = (int)$m[1];
            $month = (int)$m[2];
            $daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
            return sprintf("%02d/%02d/%04d", $daysInMonth, $month, $year);
        }
        return date('d/m/Y');
    }
}
