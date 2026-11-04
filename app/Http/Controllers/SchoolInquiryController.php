<?php

namespace App\Http\Controllers;

use App\Models\Suggestion;
use App\Services\TelegramNotifier;
use App\Support\ClientKey;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;

/** A school asking about SlipNote for all its classes. Lands with the suggestions. */
class SchoolInquiryController extends Controller
{
    /** People need a few seconds to fill the form; scripts don't wait. */
    private const MIN_SECONDS = 3;

    public function show()
    {
        return view('schools', ['started' => Crypt::encryptString((string) time())]);
    }

    public function store(Request $request)
    {
        $back = route('schools').'#ask';
        // A bot that fills the hidden field, or posts faster than anyone types,
        // is told it worked: telling it otherwise only teaches it to retry.
        $sent = fn () => redirect($back)->with('sent', true);

        if (filled($request->input('website'))) {
            return $sent();
        }
        try {
            $started = (int) Crypt::decryptString((string) $request->input('started'));
        } catch (DecryptException) {
            return $sent();
        }
        if (time() - $started < self::MIN_SECONDS) {
            return $sent();
        }

        $key = 'schools:'.ClientKey::of($request);
        if (RateLimiter::tooManyAttempts($key, 3) || RateLimiter::tooManyAttempts('schools:all', 30)) {
            return redirect($back)->withInput()->withErrors(['school' => 'We’ve had a lot of messages just now. Try again in an hour.']);
        }

        $data = $request->validate([
            'school' => 'required|string|min:2|max:120',
            'contact' => 'required|string|min:5|max:120',
            'name' => 'nullable|string|max:80',
            'classes' => 'nullable|integer|min:1|max:500',
            'message' => 'nullable|string|max:800',
        ], [
            'school.required' => 'Add your school’s name.',
            'contact.required' => 'Add a phone number or email so we can reply.',
            'contact.min' => 'Add a phone number or email so we can reply.',
        ]);

        $clean = fn (?string $v) => trim(strip_tags((string) $v));
        if (preg_match_all('#https?://#i', $data['message'] ?? '') > 2) {
            return redirect($back)->withInput()->withErrors(['message' => 'Leave out the web links, please.']);
        }

        $lines = array_filter([
            'School: '.$clean($data['school']),
            filled($data['name'] ?? null) ? 'From: '.$clean($data['name']) : null,
            filled($data['classes'] ?? null) ? 'Classes: '.(int) $data['classes'] : null,
            filled($data['message'] ?? null) ? "\n".$clean($data['message']) : null,
        ]);

        $suggestion = Suggestion::create([
            'message' => implode("\n", $lines),
            'reply_to' => $clean($data['contact']),
            'page' => '/schools',
        ]);

        RateLimiter::hit($key, 3600);
        RateLimiter::hit('schools:all', 86400);

        dispatch(fn () => app(TelegramNotifier::class)->notifySuggestion($suggestion))->afterResponse();

        return $sent();
    }
}
