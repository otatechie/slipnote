<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Suggestion extends Model
{
    protected $fillable = ['message', 'reply_to', 'page'];

    /**
     * A /schools form message split back into its fields (see
     * SchoolInquiryController), or null for an ordinary suggestion.
     */
    public function schoolEnquiry(): ?array
    {
        if ($this->page !== '/schools' || ! str_starts_with($this->message, 'School: ')) {
            return null;
        }

        // The fields, a blank line, then what they wrote.
        [$head, $body] = array_pad(explode("\n\n", $this->message, 2), 2, '');
        $fields = [];
        foreach (explode("\n", $head) as $line) {
            [$key, $value] = array_pad(explode(': ', $line, 2), 2, '');
            $fields[$key] = trim($value);
        }

        return [
            'school' => $fields['School'] ?? '',
            'from' => ($fields['From'] ?? '') ?: null,
            'classes' => isset($fields['Classes']) ? (int) $fields['Classes'] : null,
            'message' => trim($body),
        ];
    }
}
