<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Suggestion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
                'message' => $this->safeMessage($e),
            ]);
        }
    }

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
                'message' => $this->safeMessage($e),
                'material_id' => $material->id,
            ]);
        }
    }

    /** The daily cleanup removed empty, unopened boards. */
    public function notifyAbandonedRemoved(int $count): void
    {
        $token = (string) config('noteshare.telegram_bot_token');
        $chatId = (string) config('noteshare.telegram_chat_id');
        if ($token === '' || $chatId === '') {
            return;
        }

        try {
            Http::asJson()->timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => "🧹 {$count} empty ".($count === 1 ? 'board' : 'boards').' removed: no files and not opened for 30 days.',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram cleanup notification threw', ['message' => $this->safeMessage($e)]);
        }
    }

    public function notifySuggestion(Suggestion $suggestion): void
    {
        $token = (string) config('noteshare.telegram_bot_token');
        $chatId = (string) config('noteshare.telegram_chat_id');

        if ($token === '' || $chatId === '') {
            return;
        }

        $lines = ['💡 <b>Suggestion</b>', $this->esc($suggestion->message)];
        if (filled($suggestion->reply_to)) {
            $lines[] = 'Reply to: '.$this->esc($suggestion->reply_to);
        }
        if (filled($suggestion->page)) {
            $lines[] = 'From: '.$this->esc($suggestion->page);
        }

        try {
            $response = Http::asJson()
                ->timeout(5)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => implode("\n", $lines),
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

            if ($response->failed()) {
                Log::warning('Telegram suggestion notification failed', ['status' => $response->status()]);
            }
        } catch (\Throwable $e) {
            Log::warning('Telegram suggestion notification threw', ['message' => $this->safeMessage($e)]);
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
        $workspace = $course->workspace;
        $section = $this->esc(ucfirst($material->section));
        $code = $this->esc($course->code);
        $file = $this->esc($material->original_filename);
        $url = $this->esc(route('course.show', [
            'workspace' => $workspace->slug,
            'slug' => $course->slug,
        ]));

        $headline = filled($material->title)
            ? $this->esc($material->title)
            : $file;

        $meta = "New in <b>{$code}</b> · {$section}";

        $detail = filled($material->title) ? "<i>{$file}</i>" : '';
        $by = match (true) {
            (bool) $material->by_owner => 'uploaded by the board owner',
            filled($material->uploader_name) => 'uploaded by '.$this->esc($material->uploader_name),
            default => '',
        };
        if ($by !== '') {
            $detail = $detail === '' ? $by : "{$detail} — {$by}";
        }

        return "📄 <b>{$headline}</b>\n"
            .$meta."\n"
            .($detail !== '' ? $detail."\n" : '')
            ."<a href=\"{$url}\">Browse {$code} →</a>";
    }

    private function esc(string $value): string
    {
        return str_replace(
            ['&', '<', '>'],
            ['&amp;', '&lt;', '&gt;'],
            $value,
        );
    }

    private function safeMessage(\Throwable $e): string
    {
        $token = (string) config('noteshare.telegram_bot_token');

        return $token !== '' ? str_replace($token, '[token]', $e->getMessage()) : $e->getMessage();
    }
}
