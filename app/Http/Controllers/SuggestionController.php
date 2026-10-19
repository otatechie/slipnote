<?php

namespace App\Http\Controllers;

use App\Models\Suggestion;
use App\Services\TelegramNotifier;
use App\Support\ClientKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class SuggestionController extends Controller
{
    public function store(Request $request)
    {
        // Hidden field real visitors never see or fill. A bot that fills every
        // input is told "thanks" and nothing is stored.
        if (filled($request->input('website'))) {
            return back();
        }

        $rlKey = 'suggestion:'.ClientKey::of($request);
        if (RateLimiter::tooManyAttempts($rlKey, 5)) {
            return back()->withErrors(['message' => 'You\'ve sent a few already. Try again in an hour.']);
        }

        $data = $request->validate([
            'message' => 'required|string|min:5|max:1000',
            'reply_to' => 'nullable|string|max:120',
            'page' => 'nullable|string|max:160',
        ], [
            'message.required' => 'Write a line or two first.',
            'message.min' => 'Write a line or two first.',
        ]);

        $suggestion = Suggestion::create([
            'message' => strip_tags($data['message']),
            'reply_to' => filled($data['reply_to'] ?? null) ? strip_tags($data['reply_to']) : null,
            // Path only: a query string on a board page can carry ?owner=.
            'page' => filled($data['page'] ?? null) ? strtok($data['page'], '?#') : null,
        ]);

        RateLimiter::hit($rlKey, 3600);

        dispatch(fn () => app(TelegramNotifier::class)->notifySuggestion($suggestion))->afterResponse();

        return back();
    }
}
