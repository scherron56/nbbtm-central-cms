<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

// Load .env variables securely
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

/**
 * Sends an email using Brevo SMTP.
 */
function sendEmail($toEmail, $toName, $subject, $htmlContent) {
    $mail = new PHPMailer(true);

    try {
        // Brevo Server Settings
        $mail->isSMTP();
        $mail->Host       = $_ENV['BREVO_SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['BREVO_SMTP_USER'];
        $mail->Password   = $_ENV['BREVO_SMTP_KEY'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)$_ENV['BREVO_SMTP_PORT'];

        // Sender & Recipient Setup
        $mail->setFrom($_ENV['FROM_EMAIL'], $_ENV['FROM_NAME']);
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlContent;
        $mail->AltBody = strip_tags($htmlContent); // Fallback plain text for old mail clients

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}