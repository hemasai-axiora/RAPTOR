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

    public function __construct(array $config = []) {
        $mailConfig = class_exists('EmailService') ? EmailService::getConfig() : [];

        $host = !empty($config['host']) ? $config['host'] : (!empty($mailConfig['host']) ? $mailConfig['host'] : ($_ENV['SMTP_HOST'] ?? '127.0.0.1'));
        $port = !empty($config['port']) ? (int)$config['port'] : (!empty($mailConfig['port']) ? (int)$mailConfig['port'] : (int)($_ENV['SMTP_PORT'] ?? 587));
        $username = isset($config['username']) ? $config['username'] : ($mailConfig['username'] ?? ($_ENV['SMTP_USER'] ?? ''));
        $password = isset($config['password']) ? $config['password'] : ($mailConfig['password'] ?? ($_ENV['SMTP_PASS'] ?? ''));
        $encryption = isset($config['encryption']) ? $config['encryption'] : ($mailConfig['encryption'] ?? ($_ENV['SMTP_ENCRYPTION'] ?? 'tls'));

        $this->config = [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'encryption' => $encryption,
            'timeout' => (int)($config['timeout'] ?? $_ENV['SMTP_TIMEOUT'] ?? 15),
            'keepalive' => true
        ];
    }

    public function getName(): string {
        return 'smtp';
    }

    public function send(array $message): MailerSendResult {
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

