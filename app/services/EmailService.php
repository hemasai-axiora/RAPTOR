<?php
/**
 * Raptor CRM Email Service
 * 
 * Enterprise email service supporting:
 * 1. Pure PHP Socket SMTP (SSL/TLS, STARTTLS, AUTH LOGIN/PLAIN) - No external composer dependencies needed.
 * 2. Native PHP mail() fallback.
 * 3. Multipart MIME message construction (Text + Responsive HTML + PDF attachments).
 * 4. High-level Payslip dispatch with vector PDF generation & branding.
 * 5. SMTP verification & diagnostic test connection tool.
 */

class EmailService {

    /**
     * Retrieve mail settings from database settings table, with env() and sensible defaults.
     */
    public static function getConfig(): array {
        $db = null;
        $dbSettings = [];
        try {
            if (class_exists('Database')) {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'mail.%'");
                while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                    $dbSettings[$row->setting_key] = $row->setting_value;
                }
            }
        } catch (Throwable $t) {
            // Database not accessible or table doesn't have mail keys yet
        }

        $getEnvOrDb = function(string $dbKey, string $envKey, $default = '') use ($dbSettings) {
            if (isset($dbSettings[$dbKey]) && $dbSettings[$dbKey] !== '') {
                return $dbSettings[$dbKey];
            }
            if (function_exists('env')) {
                return env($envKey, $default);
            }
            $val = getenv($envKey);
            return ($val !== false && $val !== '') ? $val : $default;
        };

        $host = $getEnvOrDb('mail.host', 'SMTP_HOST', '');
        $defaultDriver = !empty($host) ? 'smtp' : 'mail';

        return [
            'driver'          => $getEnvOrDb('mail.driver', 'MAIL_DRIVER', $defaultDriver),
            'host'            => $host,
            'port'            => (int)$getEnvOrDb('mail.port', 'SMTP_PORT', 587),
            'username'        => $getEnvOrDb('mail.username', 'SMTP_USER', ''),
            'password'        => $getEnvOrDb('mail.password', 'SMTP_PASS', ''),
            'encryption'      => strtolower($getEnvOrDb('mail.encryption', 'SMTP_ENCRYPTION', 'tls')), // 'tls', 'ssl', 'none'
            'from_address'    => $getEnvOrDb('mail.from_address', 'SMTP_FROM_EMAIL', 'accounts@axioraglobalsolutions.com'),
            'from_name'       => $getEnvOrDb('mail.from_name', 'SMTP_FROM_NAME', 'Axiora Global Solutions'),
            'auto_send'       => (string)$getEnvOrDb('mail.auto_send_on_generation', 'MAIL_AUTO_SEND', '1'),
            'timeout'         => 15
        ];
    }

    /**
     * Send email with optional attachments.
     * 
     * @param string $toEmail Recipient email address
     * @param string $toName Recipient name
     * @param string $subject Email subject line
     * @param string $htmlBody HTML message body
     * @param string $textBody Plaintext fallback body
     * @param array $attachments Array of ['name' => 'filename.pdf', 'content' => binaryData, 'type' => 'application/pdf']
     * @param array|null $customConfig Optional custom mail config
     * @return array ['success' => bool, 'error' => string|null, 'message_id' => string]
     */
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = '', array $attachments = [], ?array $customConfig = null): array {
        $toEmail = trim($toEmail);
        if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid recipient email address: ' . htmlspecialchars($toEmail)];
        }

        $config = $customConfig ?? self::getConfig();
        $driver = strtolower($config['driver'] ?? 'smtp');

        if ($driver === 'smtp' && !empty($config['host'])) {
            try {
                $res = self::sendViaSmtp($toEmail, $toName, $subject, $htmlBody, $textBody, $attachments, $config);
                return $res;
            } catch (Throwable $e) {
                // If SMTP fails, record exact error
                $smtpError = $e->getMessage();
                // If in dev environment or mail fallback allowed, try PHP mail() as fallback
                $fallbackRes = self::sendViaPhpMail($toEmail, $toName, $subject, $htmlBody, $textBody, $attachments, $config);
                if ($fallbackRes['success']) {
                    return $fallbackRes;
                }
                return ['success' => false, 'error' => "SMTP Error: " . $smtpError];
            }
        }

        // Driver is 'mail' or SMTP host not set
        return self::sendViaPhpMail($toEmail, $toName, $subject, $htmlBody, $textBody, $attachments, $config);
    }

    /**
     * Send email using direct Socket SMTP connection with TLS/SSL authentication.
     */
    private static function sendViaSmtp(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody, array $attachments, array $config): array {
        $host = trim($config['host']);
        $port = (int)($config['port'] ?? 587);
        $encryption = strtolower($config['encryption'] ?? 'tls');
        $username = trim($config['username'] ?? '');
        $password = $config['password'] ?? '';
        $timeout = (int)($config['timeout'] ?? 15);
        $fromEmail = trim($config['from_address'] ?: 'accounts@axioraglobalsolutions.com');
        $fromName = trim($config['from_name'] ?: 'Axiora Global Solutions');

        $transport = ($encryption === 'ssl' || $port === 465) ? 'ssl' : 'tcp';
        $socketContext = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $remoteSocket = "{$transport}://{$host}:{$port}";
        $socket = @stream_socket_client($remoteSocket, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $socketContext);

        if (!$socket) {
            throw new Exception("Could not connect to SMTP server {$host}:{$port} ({$errno} - {$errstr})");
        }

        stream_set_timeout($socket, $timeout);

        $readResponse = function() use ($socket): string {
            $response = '';
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                // If 4th char is space, response is complete (RFC 821/2821)
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return trim($response);
        };

        $sendCommand = function(string $cmd, array $expectedCodes) use ($socket, $readResponse): string {
            fwrite($socket, $cmd . "\r\n");
            $res = $readResponse();
            $code = (int)substr($res, 0, 3);
            if (!in_array($code, $expectedCodes, true)) {
                throw new Exception("SMTP Command failed: [{$cmd}] - Server response: {$res}");
            }
            return $res;
        };

        // 1. Initial Greeting (220)
        $banner = $readResponse();
        $code = (int)substr($banner, 0, 3);
        if ($code !== 220) {
            fclose($socket);
            throw new Exception("SMTP Server did not provide greeting: {$banner}");
        }

        // 2. EHLO
        $localhost = $_SERVER['SERVER_NAME'] ?? 'localhost';
        if (empty($localhost) || $localhost === 'localhost') {
            $localhost = '127.0.0.1';
        }
        $sendCommand("EHLO [{$localhost}]", [250]);

        // 3. STARTTLS if required and not already connected via SSL
        if ($encryption === 'tls' && $transport === 'tcp') {
            $sendCommand("STARTTLS", [220]);
            
            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            $cryptoOk = @stream_socket_enable_crypto($socket, true, $cryptoMethod);
            if (!$cryptoOk) {
                fclose($socket);
                throw new Exception("STARTTLS encryption handshake failed with {$host}:{$port}");
            }

            // Resend EHLO over TLS channel
            $sendCommand("EHLO [{$localhost}]", [250]);
        }

        // 4. Authenticate if username provided
        if (!empty($username)) {
            $sendCommand("AUTH LOGIN", [334]);
            $sendCommand(base64_encode($username), [334]);
            $sendCommand(base64_encode($password), [235]);
        }

        // 5. MAIL FROM
        $sendCommand("MAIL FROM:<{$fromEmail}>", [250]);

        // 6. RCPT TO
        $sendCommand("RCPT TO:<{$toEmail}>", [250, 251]);

        // 7. DATA
        $sendCommand("DATA", [354]);

        // 8. Build and Send MIME Payload
        $messageData = self::buildMimeMessage($toEmail, $toName, $fromEmail, $fromName, $subject, $htmlBody, $textBody, $attachments);
        
        // Escape lone dots on a line per RFC 2821
        $messageData = preg_replace('/^\./m', '..', $messageData);
        fwrite($socket, $messageData . "\r\n.\r\n");

        $dataRes = $readResponse();
        $code = (int)substr($dataRes, 0, 3);
        if ($code !== 250) {
            fclose($socket);
            throw new Exception("SMTP DATA send failed: {$dataRes}");
        }

        // 9. QUIT
        try {
            $sendCommand("QUIT", [221, 250]);
        } catch (Throwable $ignore) {}

        @fclose($socket);

        return [
            'success'    => true,
            'error'      => null,
            'message_id' => '<' . md5(uniqid(microtime(), true)) . '@' . $localhost . '>'
        ];
    }

    /**
     * Send email using PHP built-in mail() function.
     */
    private static function sendViaPhpMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody, array $attachments, array $config): array {
        $fromEmail = trim($config['from_address'] ?: 'accounts@axioraglobalsolutions.com');
        $fromName = trim($config['from_name'] ?: 'Axiora Global Solutions');

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $recipientHeader = !empty($toName) ? "=?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>" : $toEmail;

        $boundaryMixed = '=_mix_' . md5(uniqid(time()));
        $boundaryAlt   = '=_alt_' . md5(uniqid(time()));

        $headers = [];
        $headers[] = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>";
        $headers[] = "Reply-To: <{$fromEmail}>";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundaryMixed}\"";
        $headers[] = "X-Mailer: RaptorCRM-Mailer/2.0";

        // Body construct
        $body = "--{$boundaryMixed}\r\n";
        $body .= "Content-Type: multipart/alternative; boundary=\"{$boundaryAlt}\"\r\n\r\n";

        // Text part
        $plainText = !empty($textBody) ? $textBody : strip_tags($htmlBody);
        $body .= "--{$boundaryAlt}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($plainText)) . "\r\n";

        // HTML part
        $body .= "--{$boundaryAlt}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        $body .= "--{$boundaryAlt}--\r\n";

        // Attachments
        foreach ($attachments as $att) {
            $attName = preg_replace('/[^A-Za-z0-9._-]/', '_', $att['name'] ?? 'attachment.pdf');
            $attType = $att['type'] ?? 'application/pdf';
            $attContent = $att['content'] ?? '';

            $body .= "--{$boundaryMixed}\r\n";
            $body .= "Content-Type: {$attType}; name=\"{$attName}\"\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$attName}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($attContent)) . "\r\n";
        }

        $body .= "--{$boundaryMixed}--\r\n";

        $additionalParams = "-f" . $fromEmail;
        $sent = @mail($toEmail, $encodedSubject, $body, implode("\r\n", $headers), $additionalParams);
        if (!$sent) {
            $sent = @mail($toEmail, $encodedSubject, $body, implode("\r\n", $headers));
        }

        if ($sent) {
            return ['success' => true, 'error' => null];
        }

        // If local environment doesn't have an MTA, provide clear guidance
        return [
            'success' => false, 
            'error'   => 'PHP mail() dispatch failed. Local server has no MTA configured. Please configure real SMTP credentials in Settings > Email & SMTP Integration.'
        ];
    }

    /**
     * Build complete RFC 2822 MIME message string.
     */
    private static function buildMimeMessage(string $toEmail, string $toName, string $fromEmail, string $fromName, string $subject, string $htmlBody, string $textBody, array $attachments): string {
        $boundaryMixed = '=_mix_' . md5(uniqid((string)mt_rand(), true));
        $boundaryAlt   = '=_alt_' . md5(uniqid((string)mt_rand(), true));

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $encodedToName = !empty($toName) ? '=?UTF-8?B?' . base64_encode($toName) . '?=' : '';

        $lines = [];
        $lines[] = "Date: " . date('r');
        $lines[] = "From: {$encodedFromName} <{$fromEmail}>";
        $lines[] = !empty($encodedToName) ? "To: {$encodedToName} <{$toEmail}>" : "To: <{$toEmail}>";
        $lines[] = "Reply-To: <{$fromEmail}>";
        $lines[] = "Subject: {$encodedSubject}";
        $lines[] = "Message-ID: <" . md5(uniqid((string)mt_rand(), true)) . "@axioraglobalsolutions.com>";
        $lines[] = "MIME-Version: 1.0";
        $lines[] = "Content-Type: multipart/mixed; boundary=\"{$boundaryMixed}\"";
        $lines[] = "X-Mailer: RaptorCRM-SMTP/2.0";
        $lines[] = "";

        // Alternative Part (Text + HTML)
        $lines[] = "--{$boundaryMixed}";
        $lines[] = "Content-Type: multipart/alternative; boundary=\"{$boundaryAlt}\"";
        $lines[] = "";

        // Plaintext
        $plainText = !empty($textBody) ? $textBody : strip_tags($htmlBody);
        $lines[] = "--{$boundaryAlt}";
        $lines[] = "Content-Type: text/plain; charset=UTF-8";
        $lines[] = "Content-Transfer-Encoding: base64";
        $lines[] = "";
        $lines[] = chunk_split(base64_encode($plainText));

        // HTML
        $lines[] = "--{$boundaryAlt}";
        $lines[] = "Content-Type: text/html; charset=UTF-8";
        $lines[] = "Content-Transfer-Encoding: base64";
        $lines[] = "";
        $lines[] = chunk_split(base64_encode($htmlBody));

        $lines[] = "--{$boundaryAlt}--";
        $lines[] = "";

        // Attachments
        foreach ($attachments as $att) {
            $attName = preg_replace('/[^A-Za-z0-9._-]/', '_', $att['name'] ?? 'attachment.pdf');
            $attType = $att['type'] ?? 'application/pdf';
            $attContent = $att['content'] ?? '';

            $lines[] = "--{$boundaryMixed}";
            $lines[] = "Content-Type: {$attType}; name=\"{$attName}\"";
            $lines[] = "Content-Disposition: attachment; filename=\"{$attName}\"";
            $lines[] = "Content-Transfer-Encoding: base64";
            $lines[] = "";
            $lines[] = chunk_split(base64_encode($attContent));
        }

        $lines[] = "--{$boundaryMixed}--";

        return implode("\r\n", $lines);
    }

    /**
     * Send official branded payslip email to employee with vector PDF attached.
     * 
     * @param object $slip Payslip detail object from database (with name, email, employee_code, gross_salary, net_salary, etc.)
     * @param string|null $customSubject Optional custom subject line (supports dynamic tags {name}, {month}, etc.)
     * @param string|null $customBody Optional custom message body
     * @param string|null $customNotes Optional audit remarks note
     * @return array ['success' => bool, 'error' => string|null, 'subject' => string, 'body' => string]
     */
    public static function sendPayslip($slip, ?string $customSubject = null, ?string $customBody = null, ?string $customNotes = null): array {
        if (empty($slip->email) || !filter_var($slip->email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error'   => 'Employee has no valid registered email address',
                'subject' => '',
                'body'    => ''
            ];
        }

        // Format month and year
        $rawMonth = $slip->month_year ?? date('Y-m');
        $monthYearFormatted = date('F Y', strtotime($rawMonth . '-01'));

        $name = !empty($slip->name) ? $slip->name : 'Employee';
        $empCode = !empty($slip->employee_code) ? $slip->employee_code : '-';
        $grossPayFormatted = number_format((float)($slip->gross_salary ?? 0), 2);
        $deductionsFormatted = number_format((float)($slip->total_deductions ?? 0), 2);
        $netPayFormatted = number_format((float)($slip->net_salary ?? 0), 2);

        // Subject resolution
        if (!empty($customSubject)) {
            $subject = str_replace(
                ['{name}', '{employee_code}', '{month}', '{net_pay}', '{gross_pay}'],
                [$name, $empCode, $monthYearFormatted, $netPayFormatted, $grossPayFormatted],
                $customSubject
            );
        } else {
            $subject = "Payslip for {$name} ({$empCode}) - {$monthYearFormatted}";
        }

        // Plain text body resolution
        if (!empty($customBody)) {
            $plainText = str_replace(
                ['{name}', '{employee_code}', '{month}', '{net_pay}', '{gross_pay}', '{deductions}'],
                [$name, $empCode, $monthYearFormatted, $netPayFormatted, $grossPayFormatted, $deductionsFormatted],
                $customBody
            );
            if (!empty($customNotes)) {
                $plainText .= "\n\nNote / Remarks:\n" . $customNotes;
            }
        } else {
            $plainText = "Dear {$name},\n\n" .
                         "Please find attached your official payslip for the month of {$monthYearFormatted}.\n\n" .
                         "Payroll Summary:\n" .
                         "----------------------------------------\n" .
                         "Employee ID    : {$empCode}\n" .
                         "Pay Period     : {$monthYearFormatted}\n" .
                         "Gross Earnings : Rs. {$grossPayFormatted}\n" .
                         "Deductions     : Rs. {$deductionsFormatted}\n" .
                         "Net Take-Home  : Rs. {$netPayFormatted}\n" .
                         "----------------------------------------\n\n" .
                         (!empty($customNotes) ? "Remarks / Record Note:\n{$customNotes}\n\n" : "") .
                         "Your official detailed payslip is attached as a PDF document.\n\n" .
                         "Warm regards,\n" .
                         "Accounts & Payroll Team\n" .
                         "Axiora Global Solutions\n" .
                         "accounts@axioraglobalsolutions.com";
        }

        // HTML Email Template
        $htmlBody = self::renderPayslipHtml($name, $empCode, $monthYearFormatted, $grossPayFormatted, $deductionsFormatted, $netPayFormatted, $customNotes);

        // Generate PDF Binary
        if (!class_exists('PayslipPdfService')) {
            require_once APPROOT . '/core/PayslipPdfService.php';
        }
        $pdfBinary = PayslipPdfService::generatePdf($slip);
        $pdfFilename = "Payslip_" . preg_replace('/[^A-Za-z0-9_-]/', '_', $empCode . '_' . $rawMonth) . ".pdf";

        $attachments = [
            [
                'name'    => $pdfFilename,
                'content' => $pdfBinary,
                'type'    => 'application/pdf'
            ]
        ];

        $res = self::send($slip->email, $name, $subject, $htmlBody, $plainText, $attachments);

        return [
            'success' => $res['success'],
            'error'   => $res['error'] ?? null,
            'subject' => $subject,
            'body'    => $plainText
        ];
    }

    /**
     * Render responsive branded HTML template for salary payslip email.
     */
    private static function renderPayslipHtml(string $name, string $empCode, string $month, string $gross, string $deductions, string $net, ?string $notes): string {
        $safeName = htmlspecialchars($name);
        $safeEmpCode = htmlspecialchars($empCode);
        $safeMonth = htmlspecialchars($month);
        $safeGross = htmlspecialchars($gross);
        $safeDeductions = htmlspecialchars($deductions);
        $safeNet = htmlspecialchars($net);
        $safeNotes = !empty($notes) ? nl2br(htmlspecialchars($notes)) : '';

        $notesBlock = '';
        if (!empty($safeNotes)) {
            $notesBlock = '
            <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 18px; border-radius: 4px; margin-bottom: 24px;">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #b45309; letter-spacing: 0.5px; margin-bottom: 4px;">Record Note / Remarks</div>
                <div style="font-size: 13px; color: #78350f; line-height: 1.5;">' . $safeNotes . '</div>
            </div>';
        }

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - ' . $safeMonth . '</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0f172a; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #334155;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #0f172a; padding: 30px 10px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
                    <tr>
                        <td style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 32px 36px; text-align: left;">
                            <table width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td>
                                        <div style="color: #38bdf8; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 6px;">Axiora Global Solutions</div>
                                        <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: -0.5px;">Official Salary Slip</h1>
                                        <div style="color: #94a3b8; font-size: 13px; margin-top: 4px;">Pay Period: <strong style="color: #f1f5f9;">' . $safeMonth . '</strong></div>
                                    </td>
                                    <td align="right" valign="top">
                                        <span style="display: inline-block; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.3); color: #38bdf8; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;">PDF Attached</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 36px;">
                            <p style="margin: 0 0 16px; font-size: 15px; color: #1e293b; line-height: 1.5;">
                                Dear <strong>' . $safeName . '</strong>,
                            </p>
                            <p style="margin: 0 0 24px; font-size: 14px; color: #475569; line-height: 1.6;">
                                Your salary for the month of <strong>' . $safeMonth . '</strong> has been computed and credited. Please find the executive payout summary below and the detailed official vector PDF payslip attached to this email.
                            </p>
                            <table width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 24px; overflow: hidden;">
                                <tr>
                                    <td style="padding: 14px 18px; border-bottom: 1px solid #e2e8f0; font-size: 13px; color: #64748b;">Employee ID</td>
                                    <td align="right" style="padding: 14px 18px; border-bottom: 1px solid #e2e8f0; font-size: 13px; font-family: monospace; font-weight: 700; color: #0f172a;">' . $safeEmpCode . '</td>
                                </tr>
                                <tr>
                                    <td style="padding: 14px 18px; border-bottom: 1px solid #e2e8f0; font-size: 13px; color: #64748b;">Gross Earnings</td>
                                    <td align="right" style="padding: 14px 18px; border-bottom: 1px solid #e2e8f0; font-size: 13px; font-weight: 600; color: #0f172a;">₹' . $safeGross . '</td>
                                </tr>
                                <tr>
                                    <td style="padding: 14px 18px; border-bottom: 1px solid #e2e8f0; font-size: 13px; color: #64748b;">Total Deductions</td>
                                    <td align="right" style="padding: 14px 18px; border-bottom: 1px solid #e2e8f0; font-size: 13px; font-weight: 600; color: #dc2626;">- ₹' . $safeDeductions . '</td>
                                </tr>
                                <tr style="background-color: #ecfdf5;">
                                    <td style="padding: 16px 18px; font-size: 14px; font-weight: 700; color: #065f46;">Net Salary Disbursed</td>
                                    <td align="right" style="padding: 16px 18px; font-size: 18px; font-weight: 800; color: #059669; font-family: -apple-system, BlinkMacSystemFont, monospace;">₹' . $safeNet . '</td>
                                </tr>
                            </table>
                            ' . $notesBlock . '
                            <div style="background-color: #f0f9ff; border: 1px dashed #0284c7; border-radius: 10px; padding: 16px; text-align: center; margin-bottom: 24px;">
                                <div style="font-size: 13px; color: #0369a1; font-weight: 600;">
                                    📎 Official PDF payslip attached: <strong>Payslip_' . $safeEmpCode . '_' . $safeMonth . '.pdf</strong>
                                </div>
                                <div style="font-size: 12px; color: #0284c7; margin-top: 4px;">
                                    Contains complete component breakdown, PF, ESIC, PT, bank details, and tax summaries.
                                </div>
                            </div>
                            <p style="margin: 0; font-size: 13px; color: #64748b; line-height: 1.6;">
                                If you have any inquiries regarding your salary calculation, deductions, or allowances, please contact the Accounts and HR department.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 24px 36px; text-align: center;">
                            <div style="font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">Axiora Global Solutions</div>
                            <div style="font-size: 12px; color: #64748b;">Automated Payroll Dispatch Notification</div>
                            <div style="font-size: 11px; color: #94a3b8; margin-top: 8px;">
                                <a href="mailto:accounts@axioraglobalsolutions.com" style="color: #0284c7; text-decoration: none;">accounts@axioraglobalsolutions.com</a> &bull; Confidential &bull; Generated by Raptor CRM
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Send a diagnostic test email to verify SMTP credentials.
     */
    public static function testConnection(string $toEmail, ?array $customConfig = null): array {
        $config = $customConfig ?? self::getConfig();
        $subject = "Raptor CRM - Email Integration Test (" . date('Y-m-d H:i:s') . ")";
        $host = !empty($config['host']) ? $config['host'] : 'localhost (PHP mail)';
        $driver = strtoupper($config['driver'] ?? 'SMTP');
        $port = (string)($config['port'] ?? '587');
        $encryption = strtoupper($config['encryption'] ?? 'TLS');
        $time = date('Y-m-d H:i:s');

        $html = '<!DOCTYPE html>
<html>
<body style="font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: #0f172a; color: #fff; padding: 30px;">
    <div style="max-width: 500px; margin: 0 auto; background: #1e293b; padding: 24px; border-radius: 12px; border: 1px solid #334155;">
        <h2 style="color: #38bdf8; margin-top: 0;">✓ Email Integration Verified</h2>
        <p style="color: #cbd5e1; font-size: 14px;">Your Raptor CRM mail integration has been successfully tested and verified.</p>
        <table style="width: 100%; border-collapse: collapse; font-size: 13px; color: #94a3b8; margin-top: 16px;">
            <tr><td style="padding: 6px 0;">Mail Driver:</td><td style="color: #fff; font-weight: bold;">' . htmlspecialchars($driver) . '</td></tr>
            <tr><td style="padding: 6px 0;">SMTP Host:</td><td style="color: #fff; font-weight: bold;">' . htmlspecialchars($host) . '</td></tr>
            <tr><td style="padding: 6px 0;">SMTP Port:</td><td style="color: #fff; font-weight: bold;">' . htmlspecialchars($port) . '</td></tr>
            <tr><td style="padding: 6px 0;">Encryption:</td><td style="color: #fff; font-weight: bold;">' . htmlspecialchars($encryption) . '</td></tr>
            <tr><td style="padding: 6px 0;">Dispatched At:</td><td style="color: #fff; font-weight: bold;">' . htmlspecialchars($time) . '</td></tr>
        </table>
        <p style="font-size: 12px; color: #64748b; margin-top: 20px;">Payslips generated in Raptor CRM can now be emailed automatically to employees.</p>
    </div>
</body>
</html>';

        $text = "Raptor CRM - Email Integration Test\n\nYour mail integration has been successfully tested and verified.\nHost: {$host}\nPort: {$port}\nDriver: {$driver}\nDispatched At: {$time}";

        return self::send($toEmail, 'Admin Test', $subject, $html, $text, [], $config);
    }
}
