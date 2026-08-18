<?php

/**
 * Returns a styled HTML Welcome Email for new users
 */
function getWelcomeEmailTemplate($userName) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f4f7; color: #51545e; margin: 0; padding: 20px; }
            .content { background-color: #ffffff; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .header { text-align: center; border-bottom: 1px solid #eaeaec; padding-bottom: 20px; }
            .button { display: inline-block; background-color: #22bc66; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 20px; }
            .footer { margin-top: 20px; font-size: 12px; color: #a8aaaf; text-align: center; }
        </style>
    </head>
    <body>
        <div class="content">
            <div class="header">
                <h2>Welcome aboard, ' . htmlspecialchars($userName) . '! 🎉</h2>
            </div>
            <p>Thank you for creating an account with us. We are excited to have you on board!</p>
            <p>You can now log in and start exploring your dashboard.</p>
            <p style="text-align: center;">
                <a href="https://yourdomain.com/login" class="button">Log In to Your Account</a>
            </p>
            <p>If you have any questions, reply directly to this email—our support team is happy to help.</p>
            <div class="footer">
                &copy; ' . date("Y") . ' Your Company Name. All rights reserved.
            </div>
        </div>
    </body>
    </html>';
}