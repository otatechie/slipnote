<?php

namespace App\Support;

/** The one place that decides whether this session is the operator's. */
class OperatorSession
{
    public const KEY = 'operator_fp';

    public static function fingerprint(): string
    {
        return hash('sha256', (string) config('noteshare.operator_secret'));
    }

    public static function isActive(): bool
    {
        $given = session(self::KEY);

        return filled(config('noteshare.operator_secret'))
            && is_string($given)
            && hash_equals(self::fingerprint(), $given);
    }
}
