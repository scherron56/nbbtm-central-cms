<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

// Load variables from .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

/**
 * Send an email via Brevo SMTP with dynamic recipients, senders, attachments, and copies.
 *
 * @param string      $toEmail
 * @param string      $toName
 * @param string      $subject
 * @param string      $htmlContent
 * @param array       $attachments        Array of file paths or [['path' => '...', 'name' => '...']]
 * @param string|null $customSenderEmail  Optional sender address (defaults to .env FROM_EMAIL)
 * @param string|null $customSenderName   Optional sender display name (defaults to .env FROM_NAME)
 * @param array       $ccEmails           Optional array of CC email addresses
 * @return array ['success' => bool, 'error' => string|null]
 */
function sendEmail($toEmail, $toName, $subject, $htmlContent, $attachments = [], $customSenderEmail = null, $customSenderName = null, $ccEmails = []) {
    $mail = new PHPMailer(true);

    try {
        // Brevo SMTP Server Configuration
        $mail->isSMTP();
        $mail->Host       = $_ENV['BREVO_SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['BREVO_SMTP_USER'];
        $mail->Password   = $_ENV['BREVO_SMTP_KEY'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)$_ENV['BREVO_SMTP_PORT'];

        // Sender Configuration (Dynamic with fallback to .env)
        $senderEmail = $customSenderEmail ?: $_ENV['FROM_EMAIL'];
        $senderName  = $customSenderName  ?: $_ENV['FROM_NAME'];
        $mail->setFrom($senderEmail, $senderName);

        // Primary Recipient Configuration
        $mail->addAddress($toEmail, $toName);

        // 1. Global Automatic BCC from .env
        if (!empty($_ENV['GLOBAL_BCC_EMAIL'])) {
            $mail->addBCC($_ENV['GLOBAL_BCC_EMAIL']);
        }

        // 2. Dynamic CCs (e.g., from admin_mailer.php)
        if (!empty($ccEmails) && is_array($ccEmails)) {
            foreach ($ccEmails as $cc) {
                if (filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($cc);
                }
            }
        }

        // Process File Attachments
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (is_array($attachment) && isset($attachment['path'])) {
                    $customName = $attachment['name'] ?? '';
                    $mail->addAttachment($attachment['path'], $customName);
                } elseif (is_string($attachment) && file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }
        }

        // Message Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlContent;
        $mail->AltBody = strip_tags($htmlContent);

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}