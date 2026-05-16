<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function show()
    {
        return view('contact', [
            'submitted' => session('contact_submitted', false),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc,dns', 'max:191'],
            // Phone is optional; lenient pattern (digits, spaces, dashes,
            // parens, leading +). Not parsing to E.164 — we just want
            // something we can call back / reach the user on.
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 +()\-]{6,40}$/'],
            'category' => ['required', 'in:bug,feedback,other'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ], [
            'phone.regex' => 'Phone may include digits, spaces, dashes, parentheses, and a leading +.',
            'message.min' => 'Please describe the issue in at least 10 characters so we can help.',
        ]);

        ContactMessage::create([
            'user_id' => $request->user()?->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'category' => $data['category'],
            'message' => $data['message'],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'status' => 'new',
        ]);

        return redirect()->route('contact.show')
            ->with('contact_submitted', true)
            ->with('toast', "Thanks — your message is in. We'll get back to you at {$data['email']}.");
    }
}
