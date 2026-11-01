<?php
/**
 * Payslip PDF Generator Service
 * Generates exact vector PDF files for payslips matching Axiora Global Solutions reference layout.
 */
require_once dirname(__FILE__) . '/PayslipHelper.php';

class PayslipPdfService {

    /**
     * Generate pure PDF binary for a given payslip detail object.
     */
    public static function generatePdf($detail): string {
        $rawMonth = $detail->month_year ?? date('Y-m');
        $monthYear = date('F Y', strtotime($rawMonth . '-01'));
        $payPeriod = date('F Y', strtotime($rawMonth . '-01'));
        $payrollCfg = class_exists('Payroll') ? Payroll::getPayrollConfig() : [];
        $payDateDay = (int)($payrollCfg['pay_date_day'] ?? 1);
        $payDate = date($payDateDay . ' F Y', strtotime($rawMonth . '-01 +1 month'));
        
        $empName = !empty($detail->name) ? $detail->name : 'Employee';
        $empCode = !empty($detail->employee_code) ? $detail->employee_code : '-';
        $dojVal = !empty($detail->date_of_joining) ? $detail->date_of_joining : (!empty($detail->hire_date) ? $detail->hire_date : '');
        $doj = !empty($dojVal) ? date('d M Y', strtotime($dojVal)) : '-';
        // Clean any non-ASCII characters from designation
        $rawDesig = !empty($detail->job_title) ? $detail->job_title : '-';
        $desig = str_replace(['–', '—'], '-', $rawDesig);
        
        $location = !empty($detail->work_location) ? $detail->work_location : '-';
        $dept = !empty($detail->department) ? $detail->department : '-';
        
        $paidDays = (int)($detail->present_days ?? 30);
        $lopDays = (int)($detail->absent_days ?? 0);
        $bankAc = !empty($detail->account_number) ? trim($detail->account_number) : '-';
        $ifsc = !empty($detail->ifsc_code) ? trim($detail->ifsc_code) : '-';
        $uan = !empty($detail->uan) ? trim($detail->uan) : '-';
        
        $basic = round((float)($detail->basic_salary ?? 0));
        $hra = round((float)($detail->hra ?? 0));
        $special = round((float)($detail->special_allowance ?? 0));
        $otherAll = round((float)($detail->other_allowance ?? 0));
        $bonus = round((float)($detail->bonus ?? 0));
        $otherEarn = round((float)($detail->other_earnings ?? 0));
        
        $actualBasic = round((float)($detail->actual_basic > 0 ? $detail->actual_basic : $basic));
        $actualHra = round((float)($detail->actual_hra > 0 ? $detail->actual_hra : $hra));
        $actualSpecial = round((float)($detail->actual_special_allowance > 0 ? $detail->actual_special_allowance : $special));
        $actualOther = round((float)($detail->actual_other_allowance > 0 ? $detail->actual_other_allowance : $otherAll));
        
        $grossActual = round($actualBasic + $actualHra + $actualSpecial + $actualOther);
        $gross = round((float)($detail->gross_salary ?? ($basic + $hra + $special + $otherAll + $bonus + $otherEarn)));
        
        // Compliance & statutory deductions: strictly data-driven
        $isPfApplicable = isset($detail->pf_applicable) ? ((int)$detail->pf_applicable === 1) : ((float)($detail->pf ?? 0) > 0 || (float)($detail->employer_pf ?? 0) > 0);

        $epf = $isPfApplicable ? round((float)($detail->pf ?? 0)) : 0;
        $pt = round((float)(isset($detail->professional_tax) && is_numeric($detail->professional_tax) ? $detail->professional_tax : (($gross >= 15000) ? 200 : (($gross >= 10000) ? 150 : 0))));
        $otherDeductions = round((float)($detail->other_deductions ?? 0));
        $deductions = round((float)($detail->total_deductions ?? ($epf + $pt + $otherDeductions)));
        
        $employerPf = $isPfApplicable ? round((float)(isset($detail->employer_pf) && is_numeric($detail->employer_pf) ? $detail->employer_pf : $epf)) : 0;
        $gratuity = round((float)(isset($detail->gratuity) && (float)$detail->gratuity > 0 ? $detail->gratuity : round($actualBasic * 0.0481, 0, PHP_ROUND_HALF_DOWN)));
        $totalBenefits = round($employerPf + $gratuity);
        
        $ctc = round((float)($detail->ctc > 0 ? $detail->ctc : ($gross + $totalBenefits)));
        $netPay = round((float)($detail->net_salary ?? ($gross - $deductions)));
        $inWords = PayslipHelper::numberToWords($netPay);

        // Helper to format currency numbers without thousands commas (matching sample PDF)
        $fmt = function($num, $decimals = 2) {
            return number_format($num, $decimals, '.', '');
        };

        // Colors extracted from reference PDF
        // Stroke: #1c3052
        $sR = 0.110; $sG = 0.188; $sB = 0.322;
        // Light blue fill: #e3ebff
        $fR = 0.890; $fG = 0.922; $fB = 1.000;

        // Build PDF using clean PostScript / PDF-1.4 primitives
        $objects = [];
        
        $addObj = function($content) use (&$objects) {
            $objects[] = $content;
            return count($objects);
        };

        // 1: Catalog
        $catId = $addObj("<< /Type /Catalog /Pages 2 0 R >>");

        // 2: Pages
        $pagesId = $addObj("<< /Type /Pages /Kids [3 0 R] /Count 1 >>");

        // Font Objects: Standard Type 1 PostScript fonts (Times-Roman, Times-Bold, Times-Italic matching Word DOCX reference)
        $fontTimesRegularId = $addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman /Encoding /WinAnsiEncoding >>");
        $fontTimesBoldId = $addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold /Encoding /WinAnsiEncoding >>");
        $fontTimesItalicId = $addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Times-Italic /Encoding /WinAnsiEncoding >>");
        $fontRegularId = $fontTimesRegularId;
        $fontBoldId = $fontTimesBoldId;
        $fontObliqueId = $fontTimesItalicId;

        // Embed Logo Image as Image XObject (JPEG)
        $logoObjId = null;
        $logoCandidates = [
            (defined('APPROOT') ? dirname(APPROOT) . '/public/payslip_logo.jpg' : ''),
            dirname(__DIR__, 2) . '/public/payslip_logo.jpg',
            '/var/www/html/public/payslip_logo.jpg',
            __DIR__ . '/../../public/payslip_logo.jpg'
        ];
        foreach ($logoCandidates as $cand) {
            if (!empty($cand) && file_exists($cand)) {
                $logoData = file_get_contents($cand);
                $logoLen = strlen($logoData);
                $logoObjId = $addObj("<< /Type /XObject /Subtype /Image /Width 216 /Height 216 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$logoLen} >>\nstream\n" . $logoData . "\nendstream");
                break;
            }
        }

        // Embed Zoho Payroll Footer Logo as Image XObject (JPEG)
        $zohoLogoObjId = null;
        $zohoCandidates = [
            (defined('APPROOT') ? dirname(APPROOT) . '/public/zoho_payroll_logo.jpg' : ''),
            dirname(__DIR__, 2) . '/public/zoho_payroll_logo.jpg',
            '/var/www/html/public/zoho_payroll_logo.jpg',
            __DIR__ . '/../../public/zoho_payroll_logo.jpg'
        ];
        foreach ($zohoCandidates as $zcand) {
            if (!empty($zcand) && file_exists($zcand)) {
                $zData = file_get_contents($zcand);
                $zLen = strlen($zData);
                $zohoLogoObjId = $addObj("<< /Type /XObject /Subtype /Image /Width 199 /Height 65 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$zLen} >>\nstream\n" . $zData . "\nendstream");
                break;
            }
        }

        // Content Stream
        $stream = "";
        
        // Helper to draw text
        $drawText = function($x, $y, $text, $font = 'F1', $size = 10, $r = 0.08, $g = 0.1, $b = 0.15) use (&$stream) {
            $safeText = strtr($text, ['(' => '\(', ')' => '\)', '\\' => '\\\\', "\r" => '', "\n" => ' ']);
            $stream .= sprintf("BT /%s %.2f Tf %.3f %.3f %.3f rg %.2f %.2f Td (%s) Tj ET\n", 
                $font, $size, $r, $g, $b, $x, $y, $safeText);
        };

        // Helper to draw right-aligned text (Times-Roman character width approx ~4.6 for size 10)
        $drawTextRight = function($x, $y, $text, $font = 'F1', $size = 10, $charWidthApprox = 4.6, $r = 0.08, $g = 0.1, $b = 0.15) use (&$stream, $drawText) {
            $width = strlen($text) * $charWidthApprox * ($size / 10);
            $drawText($x - $width, $y, $text, $font, $size, $r, $g, $b);
        };

        // Helper to draw centered text (Times-Roman character width approx ~4.6 for size 10)
        $drawTextCenter = function($centerX, $y, $text, $font = 'F1', $size = 10, $charWidthApprox = 4.6, $r = 0.08, $g = 0.1, $b = 0.15) use (&$stream, $drawText) {
            $width = strlen($text) * $charWidthApprox * ($size / 10);
            $drawText($centerX - ($width / 2), $y, $text, $font, $size, $r, $g, $b);
        };

        // Helper to draw exact vector Indian Rupee symbol glyph matching reference Times New Roman font
        $drawRupee = function($x, $y, $size = 8.5, $r = 0.08, $g = 0.1, $b = 0.15) use (&$stream) {
            $stream .= sprintf("q %.3f %.3f %.3f rg\n", $r, $g, $b);
            $stream .= sprintf("%.2f %.2f m\n", $x + ($size * 0.4619), $y + ($size * 0.5117));
            $stream .= sprintf("%.2f %.2f l\n", $x + ($size * 0.4326), $y + ($size * 0.4648));
            $stream .= sprintf("%.2f %.2f l\n", $x + ($size * 0.3726), $y + ($size * 0.4648));
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $x + ($size * 0.3716), $y + ($size * 0.4423), $x + ($size * 0.3652), $y + ($size * 0.4209), $x + ($size * 0.3535), $y + ($size * 0.4008));
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $x + ($size * 0.3418), $y + ($size * 0.3806), $x + ($size * 0.3264), $y + ($size * 0.3627), $x + ($size * 0.3074), $y + ($size * 0.3470));
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $x + ($size * 0.2883), $y + ($size * 0.3313), $x + ($size * 0.2665), $y + ($size * 0.3181), $x + ($size * 0.2419), $y + ($size * 0.3075));
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $x + ($size * 0.2174), $y + ($size * 0.2968), $x + ($size * 0.1917), $y + ($size * 0.2897), $x + ($size * 0.1650), $y + ($size * 0.2861));
            $stream .= sprintf("%.2f %.2f l\n", $x + ($size * 0.4458), $y + ($size * 0.0000));
            $stream .= sprintf("%.2f %.2f l\n", $x + ($size * 0.3384), $y + ($size * 0.0000));
            $stream .= sprintf("%.2f %.2f l\n", $x + ($size * 0.0640), $y + ($size * 0.2783));
            $stream .= sprintf("%.2f %.2f l\n", $x + ($size * 0.0640), $y + ($size * 0.3252));
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $x + ($size * 0.0802), $y + ($size * 0.3252), $x + ($size * 0.0968), $y + ($size * 0.3261), $x + ($size * 0.1135), $y + ($size * 0.3279));
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $x + ($size * 0.1303), $y + ($size * 0.3297), $x + ($size * 0.1466), $y + ($size * 0.3326), $x + ($size * 0.1624), $y + ($size * 0.3367));
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $x + ($size * 0.1781), $y + ($size * 0.3408), $x + ($size * 0.1930), $y + ($size * 0.3463), $x + ($size * 0.2068), $y + ($size * 0.3534));
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $x + ($size * 0.2206), $y + ($size * 0.3604), $x + ($size * 0.2327), $y + ($size * 0.3690), $x + ($size * 0.2432), $y + ($size * 0.3793));
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $x + ($size * 0.2536), $y + ($size * 0.3896), $x + ($size * 0.2618), $y + ($size * 0.4018), $x + ($size * 0.2678), $y + ($size * 0.4158));
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $x + ($size * 0.2738), $y + ($size * 0.4299), $x + ($size * 0.2769), $y + ($size * 0.4462), $x + ($size * 0.2769), $y + ($size * 0.4648));
            $stream .= sprintf("%.2f %.2f l\n", $x + ($size * 0.0493), $y + ($size * 0.4648));
            $stream .= sprintf("%.2f %.2f l\n", $x + ($size * 0.0786), $y + ($size * 0.5117));
            $stream .= sprintf("%.2f %.2f l\n", $x + ($size * 0.2710), $y + ($size * 0.5117));
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $x + ($size * 0.2664), $y + ($size * 0.5283), $x + ($size * 0.2593), $y + ($size * 0.5431), $x + ($size * 0.2495), $y + ($size * 0.5559));
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $x + ($size * 0.2397), $y + ($size * 0.5688), $x + ($size * 0.2282), $y + ($size * 0.5796), $x + ($size * 0.2148), $y + ($size * 0.5884));
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $x + ($size * 0.2015), $y + ($size * 0.5972), $x + ($size * 0.1866), $y + ($size * 0.6038), $x + ($size * 0.1702), $y + ($size * 0.6084));
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $x + ($size * 0.1537), $y + ($size * 0.6130), $x + ($size * 0.1366), $y + ($size * 0.6152), $x + ($size * 0.1187), $y + ($size * 0.6152));
            $stream .= sprintf("%.2f %.2f l\n", $x + ($size * 0.0493), $y + ($size * 0.6152));
            $stream .= sprintf("%.2f %.2f l\n", $x + ($size * 0.0786), $y + ($size * 0.6621));
            $stream .= sprintf("%.2f %.2f l\n", $x + ($size * 0.4619), $y + ($size * 0.6621));
            $stream .= sprintf("%.2f %.2f l\n", $x + ($size * 0.4326), $y + ($size * 0.6152));
            $stream .= sprintf("%.2f %.2f l\n", $x + ($size * 0.2900), $y + ($size * 0.6152));
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $x + ($size * 0.3105), $y + ($size * 0.6019), $x + ($size * 0.3273), $y + ($size * 0.5864), $x + ($size * 0.3403), $y + ($size * 0.5688));
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $x + ($size * 0.3534), $y + ($size * 0.5513), $x + ($size * 0.3626), $y + ($size * 0.5322), $x + ($size * 0.3682), $y + ($size * 0.5117));
            $stream .= sprintf("%.2f %.2f l\n", $x + ($size * 0.4619), $y + ($size * 0.5117));
            $stream .= "h f\nQ\n";
        };

        // Helper to draw monetary amount with Rupee glyph and Times New Roman numbers
        $drawAmount = function($x, $y, $numStr, $font = 'FT1', $size = 8.5, $align = 'left', $r = 0.0, $g = 0.0, $b = 0.0) use ($drawRupee, $drawText) {
            if ($font === 'F1') $font = 'FT1';
            if ($font === 'F2') $font = 'FT2';
            $isBold = ($font === 'FT2' || $font === 'F2');
            $charW = $isBold ? 5.0 : 4.5;
            $textW = strlen($numStr) * $charW * ($size / 10);
            $rupeeW = $size * 0.46;
            $gap = 1.0;
            $totalW = $rupeeW + $gap + $textW;

            if ($align === 'right') {
                $rx = $x - $totalW;
                $tx = $rx + $rupeeW + $gap;
            } elseif ($align === 'center') {
                $rx = $x - ($totalW / 2);
                $tx = $rx + $rupeeW + $gap;
            } else {
                $rx = $x;
                $tx = $rx + $rupeeW + $gap;
            }

            $drawRupee($rx, $y, $size, $r, $g, $b);
            $drawText($tx, $y, $numStr, $font, $size, $r, $g, $b);
        };

        // Helper to draw vector rectangle with rounded corners
        $drawRoundedRect = function($x, $y, $w, $h, $radius = 8, $strokeR = 0.11, $strokeG = 0.188, $strokeB = 0.322, $lineWidth = 1, $fillR = null, $fillG = null, $fillB = null) use (&$stream) {
            $r = min($radius, $w / 2, $h / 2);
            $k = $r * 0.5522847498;

            $stream .= "q\n";
            if ($fillR !== null) {
                $stream .= sprintf("%.3f %.3f %.3f rg\n", $fillR, $fillG, $fillB);
            }
            if ($lineWidth > 0 && $strokeR !== null) {
                $stream .= sprintf("%.2f w %.3f %.3f %.3f RG\n", $lineWidth, $strokeR, $strokeG, $strokeB);
            }

            // Path starting bottom-left at (x + r, y)
            $stream .= sprintf("%.2f %.2f m\n", $x + $r, $y);
            // Bottom edge & bottom-right corner
            $stream .= sprintf("%.2f %.2f l\n", $x + $w - $r, $y);
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", 
                $x + $w - $r + $k, $y, $x + $w, $y + $r - $k, $x + $w, $y + $r);
            // Right edge & top-right corner
            $stream .= sprintf("%.2f %.2f l\n", $x + $w, $y + $h - $r);
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", 
                $x + $w, $y + $h - $r + $k, $x + $w - $r + $k, $y + $h, $x + $w - $r, $y + $h);
            // Top edge & top-left corner
            $stream .= sprintf("%.2f %.2f l\n", $x + $r, $y + $h);
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", 
                $x + $r - $k, $y + $h, $x, $y + $h - $r + $k, $x, $y + $h - $r);
            // Left edge & bottom-left corner
            $stream .= sprintf("%.2f %.2f l\n", $x, $y + $r);
            $stream .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", 
                $x, $y + $r - $k, $x + $r - $k, $y, $x + $r, $y);
            $stream .= "h\n";

            if ($fillR !== null && $lineWidth > 0 && $strokeR !== null) {
                $stream .= "B\n";
            } elseif ($fillR !== null) {
                $stream .= "f\n";
            } elseif ($lineWidth > 0 && $strokeR !== null) {
                $stream .= "S\n";
            }
            $stream .= "Q\n";
        };

        // Helper to draw rounded rectangle with vertical gradient fill and stroked border
        $drawGradientRoundedRect = function($x, $y, $w, $h, $radius = 8, $topR = 0.651, $topG = 0.757, $topB = 1.0, $botR = 0.890, $botG = 0.922, $botB = 1.0, $strokeR = 0.110, $strokeG = 0.188, $strokeB = 0.322, $lineWidth = 1.0, $transitionMid = 0.52) use (&$stream) {
            $r = min($radius, $w / 2, $h / 2);
            $k = $r * 0.5522847498;

            // Build path string
            $path = "";
            $path .= sprintf("%.2f %.2f m\n", $x + $r, $y);
            $path .= sprintf("%.2f %.2f l\n", $x + $w - $r, $y);
            $path .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", 
                $x + $w - $r + $k, $y, $x + $w, $y + $r - $k, $x + $w, $y + $r);
            $path .= sprintf("%.2f %.2f l\n", $x + $w, $y + $h - $r);
            $path .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", 
                $x + $w, $y + $h - $r + $k, $x + $w - $r + $k, $y + $h, $x + $w - $r, $y + $h);
            $path .= sprintf("%.2f %.2f l\n", $x + $r, $y + $h);
            $path .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", 
                $x + $r - $k, $y + $h, $x, $y + $h - $r + $k, $x, $y + $h - $r);
            $path .= sprintf("%.2f %.2f l\n", $x, $y + $r);
            $path .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", 
                $x, $y + $r - $k, $x + $r - $k, $y, $x + $r, $y);
            $path .= "h\n";

            // Clip to rounded rectangle and paint gradient strips
            $stream .= "q\n";
            $stream .= $path;
            $stream .= "W n\n";

            // Top of card ($y + $h) has $topColor, bottom of card ($y) has $botColor.
            $strips = 32;
            for ($i = 0; $i < $strips; $i++) {
                $t = $i / ($strips - 1); // 0.0 at top, 1.0 at bottom
                if ($transitionMid > 0 && $t < $transitionMid) {
                    $ratio = $t / $transitionMid;
                    $cr = $topR + ($botR - $topR) * $ratio;
                    $cg = $topG + ($botG - $topG) * $ratio;
                    $cb = $topB + ($botB - $topB) * $ratio;
                } else {
                    $cr = $botR;
                    $cg = $botG;
                    $cb = $botB;
                }

                $stripY = $y + ($h * ($strips - 1 - $i) / $strips);
                $stripH = ($h / $strips) + 0.6; // Small overlap to avoid raster gap
                $stream .= sprintf("%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f\n", 
                    $cr, $cg, $cb, $x - 1, $stripY, $w + 2, $stripH);
            }
            $stream .= "Q\n";

            // Stroke the border outline
            if ($lineWidth > 0 && $strokeR !== null) {
                $stream .= "q\n";
                $stream .= sprintf("%.2f w %.3f %.3f %.3f RG\n", $lineWidth, $strokeR, $strokeG, $strokeB);
                $stream .= $path;
                $stream .= "S\n";
                $stream .= "Q\n";
            }
        };

        // Page setup: A4 is 595.28 x 841.89 points
        $pageW = 595.28;
        $pageH = 841.89;
        $left = 36;
        $right = $pageW - 36;
        $boxW = $right - $left; // 523.28 pt

        // ==========================================
        // 1. Company Header
        // ==========================================
        $topY = $pageH - 42;
        
        // Exact logo image from reference PDF
        if ($logoObjId !== null) {
            $stream .= sprintf("q 48 0 0 48 %.2f %.2f cm /ImLogo Do Q\n", $left, $topY - 48);
        } else {
            $drawRoundedRect($left, $topY - 45, 48, 48, 6, null, null, null, 0, $fR, $fG, $fB);
        }

        // Company Name & Address (Matching DOCX sz:30 / 15pt bold, #333333; sz:20 / 10pt regular, #666666)
        $drawText($left + 58, $topY - 13, "Axiora Global Solutions", 'F2', 15, 0.2, 0.2, 0.2);
        $drawText($left + 58, $topY - 26, "3rd Floor, Pranava Business Park, beside Harsha Toyota showroom", 'F1', 9.5, 0.4, 0.4, 0.4);
        $drawText($left + 58, $topY - 38, "Kothaguda, Kondapur, Hyderabad, Telangana 500084 India.", 'F1', 9.5, 0.4, 0.4, 0.4);

        // Header Right: Payslip for Month (DOCX sz:18 / 9pt regular, #666666; Month sz:22 / 11pt bold, #000000)
        $drawTextRight($right, $topY - 16, "Payslip For the Month", 'F1', 9, 4.6, 0.4, 0.4, 0.4);
        $drawTextRight($right, $topY - 32, $monthYear, 'F2', 11, 5.2, 0.0, 0.0, 0.0);

        // ==========================================
        // 2. Employee Summary Box
        // ==========================================
        $sumBoxY = $topY - 200;
        $sumBoxH = 146;
        $drawRoundedRect($left, $sumBoxY, $boxW, $sumBoxH, 8, $sR, $sG, $sB, 1.0);

        $drawText($left + 16, $sumBoxY + $sumBoxH - 18, "EMPLOYEE SUMMARY", 'F2', 10.5, 0.0, 0.0, 0.0);

        // Left Key-Value Details (DOCX sz:22 / 11pt, #000000)
        $kvLeftX = $left + 16;
        $kvColonX = $left + 104;
        $kvValX = $left + 112;
        $kvStartY = $sumBoxY + $sumBoxH - 33;
        $kvLineH = 13.8;

        $fields = [
            ['Employee Name', $empName],
            ['Employee ID', $empCode],
            ['Pay Period', $payPeriod],
            ['Pay Date', $payDate],
            ['Date of Joining', $doj],
            ['Designation', $desig],
            ['Location', $location],
            ['Department', $dept]
        ];

        foreach ($fields as $idx => $f) {
            $currY = $kvStartY - ($idx * $kvLineH);
            $drawText($kvLeftX, $currY, $f[0], 'F1', 9, 0.0, 0.0, 0.0);
            $drawText($kvColonX, $currY, ":", 'F1', 9, 0.0, 0.0, 0.0);
            $drawText($kvValX, $currY, $f[1], 'F2', 9, 0.0, 0.0, 0.0);
        }

        // Right side: Net Pay Card with Paid Days / LOP Days
        $netCardW = 188;
        $netCardH = 72;
        $netCardX = $right - $netCardW - 14;
        $netCardY = $sumBoxY + $sumBoxH - $netCardH - 12;
        $drawGradientRoundedRect($netCardX, $netCardY, $netCardW, $netCardH, 8, 0.651, 0.757, 1.0, 0.890, 0.922, 1.0, $sR, $sG, $sB, 1.0, 0.52);

        // Large Net Pay amount centered without decimals or commas matching sample (DOCX sz:39 / 19.5pt)
        $drawAmount($netCardX + ($netCardW / 2), $netCardY + 49, $fmt($netPay, 0), 'FT1', 19.5, 'center', 0.0, 0.0, 0.0);
        $drawTextCenter($netCardX + ($netCardW / 2), $netCardY + 36, "Total Net Pay", 'F1', 9, 4.6, 0.0, 0.0, 0.0);

        // Paid Days & LOP Days stacked vertically with aligned colons inside card
        $drawText($netCardX + 32, $netCardY + 20, "Paid Days", 'F1', 8.5, 0.0, 0.0, 0.0);
        $drawText($netCardX + 80, $netCardY + 20, ":", 'F1', 8.5, 0.0, 0.0, 0.0);
        $drawText($netCardX + 110, $netCardY + 20, (string)$paidDays, 'F2', 8.5, 0.0, 0.0, 0.0);

        $drawText($netCardX + 32, $netCardY + 8, "LOP Days", 'F1', 8.5, 0.0, 0.0, 0.0);
        $drawText($netCardX + 80, $netCardY + 8, ":", 'F1', 8.5, 0.0, 0.0, 0.0);
        $drawText($netCardX + 110, $netCardY + 8, (string)$lopDays, 'F2', 8.5, 0.0, 0.0, 0.0);

        // Bank details below net pay card
        $bankY = $netCardY - 14;
        $drawText($netCardX, $bankY, "Bank A/C Number", 'F1', 8.5, 0.0, 0.0, 0.0);
        $drawText($netCardX + 80, $bankY, ":", 'F1', 8.5, 0.0, 0.0, 0.0);
        $drawText($netCardX + 88, $bankY, $bankAc, 'F2', 8.5, 0.0, 0.0, 0.0);

        $drawText($netCardX, $bankY - 13.5, "IFSC Code", 'F1', 8.5, 0.0, 0.0, 0.0);
        $drawText($netCardX + 80, $bankY - 13.5, ":", 'F1', 8.5, 0.0, 0.0, 0.0);
        $drawText($netCardX + 88, $bankY - 13.5, $ifsc, 'F2', 8.5, 0.0, 0.0, 0.0);

        $drawText($netCardX, $bankY - 27, "UAN Number", 'F1', 8.5, 0.0, 0.0, 0.0);
        $drawText($netCardX + 80, $bankY - 27, ":", 'F1', 8.5, 0.0, 0.0, 0.0);
        $drawText($netCardX + 88, $bankY - 27, $uan, 'F2', 8.5, 0.0, 0.0, 0.0);

        // ==========================================
        // 3. Earnings & Deductions Box
        // ==========================================
        $earnRows = [
            ['Basic', $fmt($actualBasic), $fmt($basic)],
            ['House Rent Allowance', $fmt($actualHra), $fmt($hra)],
            ['Special Allowance', $fmt($actualSpecial), $fmt($special)],
        ];
        if ($otherAll > 0 || $actualOther > 0) {
            $earnRows[] = ['Other Allowance', $fmt($actualOther), $fmt($otherAll)];
        }
        if ($bonus > 0) {
            $earnRows[] = ['Bonus / Incentives', $fmt($bonus), $fmt($bonus)];
        }
        if ($otherEarn > 0) {
            $earnRows[] = ['Arrears / Additional', $fmt($otherEarn), $fmt($otherEarn)];
        }

        $dedRows = [
            ['EPF Contribution', $fmt($epf)],
            ['Professional Tax', $fmt($pt)]
        ];
        if ($otherDeductions > 0) {
            $dedRows[] = ['Other Deductions', $fmt($otherDeductions)];
        }

        $maxRows = max(count($earnRows), count($dedRows));
        $earnRowH = 15;
        $earnBoxH = 50 + ($maxRows * $earnRowH);
        $earnBoxY = $sumBoxY - $earnBoxH - 12;

        // Outer rounded card (no vertical divider line, matching sample layout)
        $drawRoundedRect($left, $earnBoxY, $boxW, $earnBoxH, 8, $sR, $sG, $sB, 1.0);

        // Columns layout
        $colEarnName = $left + 16;
        $colEarnActual = $left + 195;
        $colEarnAmount = $left + 268;

        $midDeductX = $left + 295;
        $colDedName = $midDeductX + 16;
        $colDedAmount = $right - 16;

        $earnHeadY = $earnBoxY + $earnBoxH - 18;

        // Headers (DOCX sz:22 / 11pt bold, #333333)
        $drawText($colEarnName, $earnHeadY, "EARNINGS", 'F2', 9.5, 0.2, 0.2, 0.2);
        $drawTextRight($colEarnActual, $earnHeadY, "ACTUAL", 'F2', 9.5, 5.0, 0.2, 0.2, 0.2);
        $drawTextRight($colEarnAmount, $earnHeadY, "AMOUNT", 'F2', 9.5, 5.0, 0.2, 0.2, 0.2);

        $drawText($colDedName, $earnHeadY, "DEDUCTIONS", 'F2', 9.5, 0.2, 0.2, 0.2);
        $drawTextRight($colDedAmount, $earnHeadY, "AMOUNT", 'F2', 9.5, 5.0, 0.2, 0.2, 0.2);

        // Rows (DOCX sz:22 / 11pt, #000000)
        for ($i = 0; $i < $maxRows; $i++) {
            $rY = $earnHeadY - 17 - ($i * $earnRowH);
            if (isset($earnRows[$i])) {
                $drawText($colEarnName, $rY, $earnRows[$i][0], 'F1', 9, 0.0, 0.0, 0.0);
                $drawAmount($colEarnActual, $rY, $earnRows[$i][1], 'F1', 9, 'right', 0.0, 0.0, 0.0);
                $drawAmount($colEarnAmount, $rY, $earnRows[$i][2], 'F1', 9, 'right', 0.0, 0.0, 0.0);
            }
            if (isset($dedRows[$i])) {
                $drawText($colDedName, $rY, $dedRows[$i][0], 'F1', 9, 0.0, 0.0, 0.0);
                $drawAmount($colDedAmount, $rY, $dedRows[$i][1], 'F1', 9, 'right', 0.0, 0.0, 0.0);
            }
        }

        // Totals Row (DOCX sz:22 / 11pt bold, #000000)
        $totEarnY = $earnBoxY + 12;
        $drawText($colEarnName, $totEarnY, "Gross Earnings", 'F2', 9.5, 0.0, 0.0, 0.0);
        $drawAmount($colEarnActual, $totEarnY, $fmt($grossActual), 'F2', 9.5, 'right', 0.0, 0.0, 0.0);
        $drawAmount($colEarnAmount, $totEarnY, $fmt($gross), 'F2', 9.5, 'right', 0.0, 0.0, 0.0);

        $drawText($colDedName, $totEarnY, "Total Deductions", 'F2', 9.5, 0.0, 0.0, 0.0);
        $drawAmount($colDedAmount, $totEarnY, $fmt($deductions), 'F2', 9.5, 'right', 0.0, 0.0, 0.0);

        // ==========================================
        // 4. Employee Benefits & CTC Box
        // ==========================================
        $benBoxH = 72;
        $benBoxY = $earnBoxY - $benBoxH - 12;
        $drawRoundedRect($left, $benBoxY, $boxW, $benBoxH, 8, $sR, $sG, $sB, 1.0);

        $benHeadY = $benBoxY + $benBoxH - 16;
        $drawText($colEarnName, $benHeadY, "Employee Benefits", 'F2', 9.5, 0.2, 0.2, 0.2);
        $drawTextRight($colEarnAmount, $benHeadY, "AMOUNT", 'F2', 9.5, 5.0, 0.2, 0.2, 0.2);

        $benRows = [
            ['Employer EPF Contribution', $fmt($employerPf)],
            ['Gratuity', $fmt($gratuity)],
        ];

        foreach ($benRows as $i => $b) {
            $bY = $benHeadY - 14 - ($i * 13.5);
            $drawText($colEarnName, $bY, $b[0], 'F1', 9, 0.0, 0.0, 0.0);
            $drawAmount($colEarnAmount, $bY, $b[1], 'F1', 9, 'right', 0.0, 0.0, 0.0);
        }

        $totBenY = $benBoxY + 10;
        $drawText($colEarnName, $totBenY, "Gross Earnings", 'F2', 9.5, 0.0, 0.0, 0.0);
        $drawAmount($colEarnAmount, $totBenY, $fmt($totalBenefits), 'F2', 9.5, 'right', 0.0, 0.0, 0.0);

        // Right side: Effective Monthly CTC card without decimals or commas (DOCX sz:39 / 19.5pt)
        $ctcCardW = 168;
        $ctcCardH = 54;
        $ctcCardX = $right - $ctcCardW - 14;
        $ctcCardY = $benBoxY + (($benBoxH - $ctcCardH) / 2);
        $drawGradientRoundedRect($ctcCardX, $ctcCardY, $ctcCardW, $ctcCardH, 8, 0.651, 0.757, 1.0, 0.890, 0.922, 1.0, $sR, $sG, $sB, 1.0, 0.52);

        $drawAmount($ctcCardX + ($ctcCardW / 2), $ctcCardY + 31, $fmt($ctc, 0), 'FT1', 19.5, 'center', 0.0, 0.0, 0.0);
        $drawTextCenter($ctcCardX + ($ctcCardW / 2), $ctcCardY + 16, "Effective Monthly CTC", 'F1', 9, 4.6, 0.0, 0.0, 0.0);

        // ==========================================
        // 5. TOTAL NET PAYABLE Banner
        // ==========================================
        $payBarH = 40;
        $payBarY = $benBoxY - $payBarH - 12;

        // Outer rounded card
        $drawRoundedRect($left, $payBarY, $boxW, $payBarH, 8, $sR, $sG, $sB, 1.0, 1, 1, 1);

        // Left pane text (DOCX sz:22 / 11pt bold, #000000)
        $drawText($left + 16, $payBarY + 23, "TOTAL NET PAYABLE", 'F2', 10.5, 0.0, 0.0, 0.0);
        $drawText($left + 16, $payBarY + 10, "Gross Earnings - Total Deductions", 'F1', 8.5, 0.2, 0.2, 0.2);

        // Right side inner rounded card matching sample layout
        $payCardW = 240;
        $payCardH = 32;
        $payCardX = $right - $payCardW - 4;
        $payCardY = $payBarY + 4;
        $drawGradientRoundedRect($payCardX, $payCardY, $payCardW, $payCardH, 6, 0.651, 0.757, 1.0, 0.875, 0.910, 1.0, $sR, $sG, $sB, 0.9, 1.0);

        // Right pane amount with decimals (.00) matching sample (DOCX sz:28 / 14pt bold)
        $drawAmount($payCardX + ($payCardW / 2), $payCardY + 10, $fmt($netPay), 'F2', 14, 'center', 0.0, 0.0, 0.0);

        // Amount In Words below banner (centered, DOCX sz:18 / 9pt)
        $wordsY = $payBarY - 14;
        $wordsText = "Amount In Words: Indian Rupee " . $inWords . " Only";
        $drawTextCenter($pageW / 2, $wordsY, $wordsText, 'F1', 8.5, 4.4, 0.1, 0.1, 0.1);

        // ==========================================
        // 6. Footer (Exact matching Zoho Payroll reference, DOCX sz:18 / 9pt)
        // ==========================================
        $drawTextCenter($pageW / 2, 82, "-- This is a system-generated document. --", 'F3', 8.5, 4.4, 0.4, 0.4, 0.4);

        // Powered by Zoho Payroll
        $footerY = 22;
        $drawText(112, $footerY + 4, "Powered by", 'F1', 8.5, 0.45, 0.5, 0.55);
        if ($zohoLogoObjId !== null) {
            $stream .= sprintf("q 65.25 0 0 21.20 %.2f %.2f cm /ImZoho Do Q\n", 168.0, $footerY - 2.5);
        }
        $drawText(242, $footerY + 4, "Simplify payroll and compliance. Visit", 'F1', 8.2, 0.45, 0.5, 0.55);
        $drawText(384, $footerY + 4, "zoho.com/payroll", 'F1', 8.2, 0.15, 0.38, 0.92);

        // 3: Page Object
        $streamLen = strlen($stream);
        $contentObjId = $addObj("<< /Length {$streamLen} >>\nstream\n" . $stream . "\nendstream");

        $xobjParts = [];
        if ($logoObjId !== null) $xobjParts[] = "/ImLogo {$logoObjId} 0 R";
        if ($zohoLogoObjId !== null) $xobjParts[] = "/ImZoho {$zohoLogoObjId} 0 R";
        $xobjEntry = !empty($xobjParts) ? "/XObject << " . implode(' ', $xobjParts) . " >>" : "";
        $pageObj = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageW} {$pageH}] /Contents {$contentObjId} 0 R /Resources << /Font << /F1 {$fontTimesRegularId} 0 R /F2 {$fontTimesBoldId} 0 R /F3 {$fontTimesItalicId} 0 R /FT1 {$fontTimesRegularId} 0 R /FT2 {$fontTimesBoldId} 0 R >> {$xobjEntry} >> >>";
        $objects[2] = $pageObj; // replace placeholder page object

        // Assemble Final PDF
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $obj) {
            $offsets[$i + 1] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $obj . "\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF\n";

        return $pdf;
    }
}
