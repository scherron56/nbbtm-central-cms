<?php
require_once __DIR__ . '/send_email.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['user_file'])) {
    $toEmail  = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $file     = $_FILES['user_file'];

    if ($toEmail && $file['error'] === UPLOAD_ERR_OK) {
        $attachment = [
            [
                'path' => $file['tmp_name'], // Path to temporary uploaded file
                'name' => $file['name']      // Original filename
            ]
        ];

        $result = sendEmail(
            $toEmail,
            'User',
            'Your Requested File',
            '<p>Here is the file you requested.</p>',
            $attachment
        );

        if ($result['success']) {
            echo "Email with attachment sent successfully!";
        } else {
            echo "Failed: " . $result['error'];
        }
    } else {
        echo "Upload failed or invalid email address.";
    }
}