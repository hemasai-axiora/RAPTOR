<?php
/**
 * Raptor CRM - SMTP Mailer Adapter (via PHPMailer)
 * 
 * Production-grade SMTP dispatch with secure authentication, TLS/STARTTLS,
 * custom deliverability headers, and 4xx vs 5xx error classification.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class SmtpAdapter implements MailerAdapterInterface {

    private array $config;
    private bool $enabled = true;
    private string $disabledReason = '';

    public function __construct(array $config = []) {
        if (!empty($config)) {
            $this->config = [
                'host' => trim($config['host'] ?? ''),
                'port' => (int)($config['port'] ?? 587),
                'username' => $config['username'] ?? '',
                'password' => $config['password'] ?? '',
                'encryption' => $config['encryption'] ?? 'tls',
                'timeout' => (int)($config['timeout'] ?? $_ENV['SMTP_TIMEOUT'] ?? 15),
                'keepalive' => true
            ];
            if (empty($this->config['host'])) {
                $this->enabled = false;
                $this->disabledReason = 'Marketing SMTP host is empty or unconfigured.';
            }
        } else {
            $mailConfig = class_exists('EmailService') ? EmailService::getMarketingConfig() : ['enabled' => false];
            if (empty($mailConfig['enabled']) || empty($mailConfig['host'])) {
                $this->enabled = false;
                $this->disabledReason = $mailConfig['error'] ?? 'Dedicated Marketing SMTP is disabled or unconfigured in Global Settings. Delivery aborted to protect Corporate SMTP reputation.';
                $this->config = [
                    'host' => '',
                    'port' => 587,
                    'username' => '',
                    'password' => '',
                    'encryption' => 'tls',
                    'timeout' => 15,
                    'keepalive' => true
                ];
            } else {
                $this->enabled = true;
                $this->config = [
                    'host' => $mailConfig['host'],
                    'port' => (int)($mailConfig['port'] ?? 587),
                    'username' => $mailConfig['username'] ?? '',
                    'password' => $mailConfig['password'] ?? '',
                    'encryption' => $mailConfig['encryption'] ?? 'tls',
                    'timeout' => (int)($mailConfig['timeout'] ?? 15),
                    'keepalive' => true
                ];
            }
        }
    }

    public function getName(): string {
        return 'smtp';
    }

    public function send(array $message): MailerSendResult {
        if (!$this->enabled || empty($this->config['host'])) {
            return MailerSendResult::failure(
                $this->disabledReason ?: 'Dedicated Marketing SMTP is disabled or unconfigured in Global Settings. Delivery aborted to protect Corporate SMTP reputation.',
                'MARKETING_SMTP_DISABLED',
                true
            );
        }

        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $fromEmail = str_replace(["\r", "\n"], '', $message['from_email'] ?? $this->config['username'] ?? '');
            $fromName = str_replace(["\r", "\n"], '', $message['from_name'] ?? 'Axiora Global Solutions');
            $toEmail = str_replace(["\r", "\n"], '', $message['to'] ?? '');
            $toName = !empty($message['to_name']) ? str_replace(["\r", "\n"], '', $message['to_name']) : '';
            $subject = str_replace(["\r", "\n"], '', $message['subject'] ?? '');

            $customConfig = [
                'driver' => 'smtp',
                'host' => $this->config['host'] ?? '',
                'port' => (int)($this->config['port'] ?? 587),
                'username' => $this->config['username'] ?? '',
                'password' => $this->config['password'] ?? '',
                'encryption' => $this->config['encryption'] ?? 'tls',
                'from_address' => $fromEmail,
                'from_name' => $fromName,
                'timeout' => (int)($this->config['timeout'] ?? 15)
            ];

            try {
                $res = EmailService::send(
                    $toEmail,
                    $toName,
                    $subject,
                    $message['html'] ?? '',
                    $message['text'] ?? strip_tags($message['html'] ?? ''),
                    [],
                    $customConfig
                );

                if (!empty($res['success'])) {
                    return MailerSendResult::success($res['message_id'] ?? ('smtp-' . bin2hex(random_bytes(10))));
                }

                $err = $res['error'] ?? 'SMTP delivery failed';
                $isTransient = (stripos($err, 'timeout') !== false ||
                                stripos($err, 'connection refused') !== false ||
                                stripos($err, 'rate limit') !== false ||
                                stripos($err, 'temporarily deferred') !== false ||
                                stripos($err, 'try again') !== false ||
                                stripos($err, '421') !== false ||
                                stripos($err, '450') !== false ||
                                stripos($err, '451') !== false);

                return MailerSendResult::failure($err, 'SMTP_ERROR', $isTransient);
            } catch (Throwable $t) {
                return MailerSendResult::failure($t->getMessage(), 'SYSTEM_ERROR', true);
            }
        }

        $mail = new PHPMailer(true);

        try {
            // Server configuration
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->SMTPAuth = !empty($this->config['username']);
            $mail->Username = $this->config['username'];
            $mail->Password = $this->config['password'];

            $enc = strtolower(trim((string)($this->config['encryption'] ?? '')));
            if ($enc === 'ssl' || $this->config['port'] === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'tls' || $this->config['port'] === 587) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            $mail->Port = $this->config['port'];
            $mail->Timeout = $this->config['timeout'];
            $mail->CharSet = 'UTF-8';

            // Clean header injection (strip \r and \n)
            $fromEmail = str_replace(["\r", "\n"], '', $message['from_email']);
            $fromName = str_replace(["\r", "\n"], '', $message['from_name']);
            $toEmail = str_replace(["\r", "\n"], '', $message['to']);
            $toName = !empty($message['to_name']) ? str_replace(["\r", "\n"], '', $message['to_name']) : '';
            $subject = str_replace(["\r", "\n"], '', $message['subject']);

            // Sender & Recipient
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail, $toName);

            if (!empty($message['reply_to'])) {
                $replyTo = str_replace(["\r", "\n"], '', $message['reply_to']);
                $mail->addReplyTo($replyTo);
            }

            // Custom deliverability headers
            if (!empty($message['headers']) && is_array($message['headers'])) {
                foreach ($message['headers'] as $hName => $hVal) {
                    $cleanName = str_replace(["\r", "\n"], '', $hName);
                    $cleanVal = str_replace(["\r", "\n"], '', $hVal);
                    $mail->addCustomHeader($cleanName, $cleanVal);
                }
            }

            // Multipart Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message['html'];
            $mail->AltBody = $message['text'] ?? strip_tags($message['html']);

            $mail->send();
            $messageId = $mail->getLastMessageID() ?: ('smtp-' . bin2hex(random_bytes(10)));

            return MailerSendResult::success($messageId);
        } catch (PHPMailerException $e) {
            $err = $e->getMessage();
            // Classify 4xx (transient) vs 5xx (permanent)
            $isTransient = (stripos($err, 'timeout') !== false ||
                            stripos($err, 'connection refused') !== false ||
                            stripos($err, 'rate limit') !== false ||
                            stripos($err, 'temporarily deferred') !== false ||
                            stripos($err, 'try again') !== false ||
                            stripos($err, '421') !== false ||
                            stripos($err, '450') !== false ||
                            stripos($err, '451') !== false);

            return MailerSendResult::failure($err, 'SMTP_ERROR', $isTransient);
        } catch (Throwable $t) {
            return MailerSendResult::failure($t->getMessage(), 'SYSTEM_ERROR', true);
        }
    }
}

