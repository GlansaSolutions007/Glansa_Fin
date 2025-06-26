<?php
namespace App\Helpers;

use App\Mail\CommonFormMail;
use Illuminate\Support\Facades\Mail;

class MailHelper
{
    public static function sendCommonMail(array $formData, string $to = 'admin@example.com')
    {
        Mail::to($to)->send(new CommonFormMail($formData));
    }
}
