<?php
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

// Load variables from .env (safe to call again if send_email.php already did)
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

if (!function_exists('nbbtmMailerEnv')) {
    function nbbtmMailerEnv($key, $default = '') {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return (is_string($value) && $value !== '') ? $value : $default;
    }
}

/**
 * Send an SMS via the Brevo Transactional SMS API.
 *
 * @param string      $toPhone    Destination phone number (E.164 format recommended, e.g. +15551234567)
 * @param string      $message    Plain-text SMS body (no HTML)
 * @param string|null $senderName Optional sender ID (alphanumeric, max 11 chars, or a Brevo-verified number).
 *                                Defaults to .env BREVO_SMS_SENDER.
 * @return array ['success' => bool, 'error' => string|null]
 */
function sendSms($toPhone, $message, $senderName = null) {
    $apiKey = nbbtmMailerEnv('BREVO_API_KEY');
    $sender = $senderName ?: nbbtmMailerEnv('BREVO_SMS_SENDER', 'NBBTM');

    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'Brevo API key (BREVO_API_KEY) is missing from environment configuration.'];
    }

    // Normalize the destination number to E.164 format (digits with a leading +)
    $cleanPhone = preg_replace('/[^0-9+]/', '', (string) $toPhone);
    if (empty($cleanPhone)) {
        return ['success' => false, 'error' => 'Invalid or missing phone number.'];
    }
    if (strpos($cleanPhone, '+') !== 0) {
        // Assume a US/Canada number when no country code is present
        $digitsOnly = ltrim($cleanPhone, '+');
        if (strlen($digitsOnly) === 10) {
            $cleanPhone = '+1' . $digitsOnly;
        } elseif (strlen($digitsOnly) === 11 && $digitsOnly[0] === '1') {
            $cleanPhone = '+' . $digitsOnly;
        } else {
            $cleanPhone = '+' . $digitsOnly;
        }
    }

    $payload = [
        'sender'    => $sender,
        'recipient' => $cleanPhone,
        'content'   => $message,
        'type'      => 'transactional',
    ];

    $ch = curl_init('https://api.brevo.com/v3/transactionalSMS/sms');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . $apiKey,
        ],
        CURLOPT_TIMEOUT        => 20,
    ]);

    $response  = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'error' => $curlError];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true];
    }

    $decoded  = json_decode($response, true);
    $errorMsg = $decoded['message'] ?? "Brevo SMS API returned HTTP status {$httpCode}.";
    return ['success' => false, 'error' => $errorMsg];
}
