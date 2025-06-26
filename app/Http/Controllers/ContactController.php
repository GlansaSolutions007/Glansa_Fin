<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\CommonFormMail;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function sendMail(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'message' => 'required|string',
            'subject' => 'nullable|string',
            // any other fields can be optional
        ]);

        $data = $request->all(); // from ReactJS

        $email = 'nagaraju1.glansa@gmail.com';
        Mail::to($email)->send(new CommonFormMail($data, 'contact_form'));

        return response()->json(['success' => true, 'message' => 'Message sent successfully']);
    }
}
