<?php

namespace App\Services;

use App\Models\Material;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Posts a one-line "new upload" notice to a Telegram channel.
 *
 * Outbound only — no webhook, no polling, nothing to receive. Disabled
 * (a silent no-op) unless BOTH the bot token and chat id are configured.
 * Every failure is swallowed and logged: notifying is best-effort and must
 * never break or slow an upload.
 */
class TelegramNotifier
{
    public function notifyUpload(Material $material): void
    {
        $token = (string) config('noteshare.telegram_bot_token');
        $chatId = (string) config('noteshare.telegram_chat_id');

        // Feature off unless fully configured.
        if ($token === '' || $chatId === '') {
            return;
        }

        try {
            $response = Http::asJson()
                ->timeout(5)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $this->message($material),
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

            if ($response->failed()) {
                Log::warning('Telegram upload notification failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            // Network error, DNS, timeout — never propagate to the uploader.
            Log::warning('Telegram upload notification threw', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Posts an abuse report to the operator's Telegram channel. Unlike
     * notifyUpload, this falls back to a logged warning when Telegram is not
     * configured — an abuse report must never vanish silently.
     */
    public function notifyReport(Material $material, ?string $reason): void
    {
        $token = (string) config('noteshare.telegram_bot_token');
        $chatId = (string) config('noteshare.telegram_chat_id');

        if ($token === '' || $chatId === '') {
            Log::warning('Abuse report (Telegram not configured)', [
                'material_id' => $material->id,
                'file' => $material->original_filename,
                'reason' => $reason,
            ]);

            return;
        }

        try {
            $response = Http::asJson()
                ->timeout(5)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $this->reportMessage($material, $reason),
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

            if ($response->failed()) {
                Log::warning('Telegram abuse report failed', [
                    'status' => $response->status(),
                    'material_id' => $material->id,
                    'reason' => $reason,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Telegram abuse report threw', [
                'message' => $e->getMessage(),
                'material_id' => $material->id,
            ]);
        }
    }

    private function reportMessage(Material $material, ?string $reason): string
    {
        $course = $material->course;
        $workspace = $course->workspace;
        $file = $this->esc($material->original_filename);
        $code = $this->esc($course->code);
        $boardUrl = $this->esc(route('course.show', [
            'workspace' => $workspace->slug,
            'slug' => $course->slug,
        ]));
        // The operator dashboard is where reports are reviewed and acted on.
        $dashboardUrl = $this->esc(route('operator.dashboard'));

        $lines = [
            '⚠️ <b>File reported</b>',
            "<b>{$code}</b> · <i>{$file}</i>",
        ];
        if (filled($reason)) {
            $lines[] = 'Reason: '.$this->esc($reason);
        }
        $lines[] = "<a href=\"{$boardUrl}\">View board</a>";
        $lines[] = "<a href=\"{$dashboardUrl}\">Review in operator dashboard</a>";

        return implode("\n", $lines);
    }

    private function message(Material $material): string
    {
        $course = $material->course;
        // Resolve the workspace explicitly from the material — this runs
        // after the response / in queue context, where no tenant is set.
        $workspace = $course->workspace;
        $section = $this->esc(ucfirst($material->section));
        $code = $this->esc($course->code);
        $file = $this->esc($material->original_filename);
        $url = $this->esc(route('course.show', [
            'workspace' => $workspace->slug,
            'slug' => $course->slug,
        ]));

        // Headline = what was uploaded (highest scent): the uploader's
        // "what is this?" title if given, otherwise the filename.
        $headline = filled($material->title)
            ? $this->esc($material->title)
            : $file;

        // Metadata line: where it landed. No timestamp — Telegram already
        // stamps every message, and "just now" goes stale when read later.
        $meta = "New in <b>{$code}</b> · {$section}";

        // Secondary line: the filename (when a title pushed it out of the
        // headline) and who uploaded it — useful but not the read decision.
        $detail = filled($material->title) ? "<i>{$file}</i>" : '';
        if (filled($material->uploader_name)) {
            $by = 'uploaded by '.$this->esc($material->uploader_name);
            $detail = $detail === '' ? $by : "{$detail} — {$by}";
        }

        return "📄 <b>{$headline}</b>\n"
            .$meta."\n"
            .($detail !== '' ? $detail."\n" : '')
            ."<a href=\"{$url}\">Browse {$code} →</a>";
    }

    /**
     * Escape user-controlled text for Telegram's HTML parse mode.
     *
     * Telegram only recognises &lt; &gt; &amp; — NOT Blade's e()/htmlspecialchars
     * output (which also encodes quotes to &#039; / &quot;, entities Telegram
     * does not decode). Uploaders fully control these strings (anonymous,
     * no accounts; original_filename is attacker-chosen), so a wrong escaper
     * is both a rendering bug and a way to malform markup and make Telegram
     * reject the call — silently suppressing the notification.
     *
     * @see https://core.telegram.org/bots/api#html-style
     */
    private function esc(string $value): string
    {
        return str_replace(
            ['&', '<', '>'],
            ['&amp;', '&lt;', '&gt;'],
            $value,
        );
    }
}
