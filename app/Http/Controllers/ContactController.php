<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'company' => ['nullable', 'string', 'max:120'],
            'service' => ['required', 'string', 'max:80'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
        ], [
            'name.required' => 'أدخل اسمك.',
            'email.required' => 'أدخل بريدك الإلكتروني.',
            'email.email' => 'البريد الإلكتروني غير صحيح.',
            'message.required' => 'اكتب لنا نبذة عمّا تحتاجه.',
            'message.min' => 'اكتب لنا تفاصيل أكثر قليلاً.',
        ]);

        ContactMessage::create($data + ['ip' => $request->ip()]);

        return back()->with('sent', true);
    }
}
