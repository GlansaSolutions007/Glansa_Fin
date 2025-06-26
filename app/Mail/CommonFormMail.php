<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CommonFormMail extends Mailable
{
    public $formData;
    public $emailType;

    public function __construct(array $formData, string $emailType = 'form')
    {
        $this->formData = $formData;
        $this->emailType = $emailType;
    }

    public function build()
    {
        switch ($this->emailType) {
            case 'forgot_password':
                $subject = 'Reset Your Password';
                $content = $this->buildForgotPasswordHtml();
                break;

            case 'user_created':
                $subject = 'Your Account Has Been Created';
                $content = $this->buildUserCreatedHtml();
                break;

            case 'contact_form':
                $subject = $this->formData['subject'] ?? 'New Contact Form Submission';
                $content = $this->buildHtmlContent();
                break;

            default:
                $subject = $this->formData['subject'] ?? 'New Submission';
                $content = $this->buildHtmlContent();
        }

        return $this->subject($subject)->html($content);
    }

    private function buildHtmlContent()
{
    $html = '
    <html>
    <head>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                padding: 20px;
            }
            .email-container {
                background-color: #ffffff;
                padding: 20px;
                border-radius: 8px;
                max-width: 600px;
                margin: auto;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            .email-container h2 {
                color: #333333;
                border-bottom: 1px solid #ddd;
                padding-bottom: 10px;
            }
            .email-container ul {
                list-style-type: none;
                padding: 0;
            }
            .email-container li {
                margin: 10px 0;
                line-height: 1.6;
            }
            .email-container strong {
                color: #555;
                min-width: 120px;
                display: inline-block;
            }
            .footer {
                font-size: 12px;
                color: #888;
                margin-top: 20px;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <h2>Contact Form Submission</h2>
            <ul>';

    foreach ($this->formData as $key => $value) {
        $html .= '<li><strong>' . ucfirst(e($key)) . ':</strong> ' . nl2br(e($value)) . '</li>';
    }

    $html .= '
            </ul>
            <div class="footer">
                You received this message because someone submitted the contact form on <a href="https://glansadesigns.com">glansadesigns.com</a>.
            </div>
        </div>
    </body>
    </html>';

    return $html;
}


    private function buildForgotPasswordHtml()
    {
        $resetLink = $this->formData['reset_link'] ?? '#';
        return "
            <h2>Password Reset Request</h2>
            <p>Hi {$this->formData['name']},</p>
            <p>Click the button below to reset your password:</p>
            <p><a href=\"{$resetLink}\" style=\"display:inline-block;padding:10px 20px;background:#007bff;color:#fff;text-decoration:none;border-radius:5px;\">Reset Password</a></p>
            <p>If you did not request a password reset, please ignore this email.</p>
        ";
    }
    private function buildUserCreatedHtml()
    {
        $loginUrl = rtrim(env('MAIN_WEBSITE'), '/') . '/login';
        return '
    <html>
    <head>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                padding: 20px;
            }
            .email-container {
                background-color: #ffffff;
                padding: 20px;
                border-radius: 8px;
                max-width: 600px;
                margin: auto;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            .email-container h2 {
                color: #333333;
                margin-bottom: 20px;
            }
            .email-container p {
                line-height: 1.6;
                color: #555;
            }
            .credentials {
                margin-top: 15px;
                padding: 15px;
                background-color: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 5px;
            }
            .credentials strong {
                display: inline-block;
                min-width: 120px;
            }
            .login-button {
                display: inline-block;
                margin-top: 20px;
                padding: 10px 20px;
                background-color: #007bff;
                color: #fff !important;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
            }
            .footer {
                font-size: 12px;
                color: #888;
                margin-top: 30px;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <h2>Welcome to Our Platform</h2>
            <p>Hi ' . e($this->formData['name']) . ',</p>
            <p>Your account has been created. Please find your login details below:</p>
            <div class="credentials">
                <p><strong>Username:</strong> ' . e($this->formData['username']) . '</p>
                <p><strong>Password:</strong> ' . e($this->formData['password']) . '</p>
            </div>
            <p>You can log in using the button below:</p>
            <a href="' . $loginUrl . '" class="login-button" target="_blank">Login</a>
            <div class="footer">
                © ' . date('Y') . ' glansadesigns.com. All rights reserved.
            </div>
        </div>
    </body>
    </html>';
    }
}