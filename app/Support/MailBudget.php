<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * How much the site may mail. Boards are free and any address can be saved as
 * a board's recovery email, so without a cap per address one person could
 * flood a stranger's inbox through many boards. Complaints about that, or any
 * flood, get the sending mailbox flagged or suspended, and then no owner gets
 * their recovery link.
 */
class MailBudget
{
    /** Mails one address gets a day, across every board. */
    public const PER_ADDRESS_A_DAY = 5;

    /** When the site-wide limit was last reached, for the operator dashboard. */
    public const FULL_KEY = 'mail_budget_full_at';

    /** Spends one send on $to, or says no and leaves the budget as it was. */
    public static function take(string $to): bool
    {
        // Hashed: recovery emails are encrypted at rest, so the cache mustn't hold them in the clear.
        $address = 'mail_to:'.hash_hmac('sha256', mb_strtolower(trim($to)), (string) config('app.key'));

        if (RateLimiter::tooManyAttempts($address, self::PER_ADDRESS_A_DAY)) {
            return false;
        }
        // The limiter never refuses a key it hasn't counted yet, so a limit of 0 is checked on its own.
        $siteLimit = (int) config('noteshare.mail_daily_limit');
        if ($siteLimit < 1 || RateLimiter::tooManyAttempts('mail_site', $siteLimit)) {
            if (! Cache::has(self::FULL_KEY)) {
                Log::warning('Daily mail limit reached; mail waits until tomorrow.');
            }
            Cache::put(self::FULL_KEY, now()->toIso8601String(), now()->addDays(2));

            return false;
        }

        RateLimiter::hit($address, 86400);
        RateLimiter::hit('mail_site', 86400);

        return true;
    }
}
