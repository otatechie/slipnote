<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Suggestion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotifier
{
    public function notifyUpload(Material $material): void
    {
        $this->notifyUploads(self::uploadSummary([$material]));
    }

    /**
     * What a batch message needs, as plain values: the callback that sends it
     * runs after the response, when the uploader may already have pressed Undo.
     *
     * @param  list<Material>  $materials
     */
    public static function uploadSummary(array $materials): array
    {
        if ($materials === []) {
            return [];
        }
        $course = $materials[0]->course;

        return [
            'code' => $course->code,
            'url' => route('course.show', ['workspace' => $course->workspace->slug, 'slug' => $course->slug]),
            'files' => array_map(fn (Material $m) => [
                'section' => $m->section,
                'title' => $m->title,
                'filename' => $m->original_filename,
                'by_owner' => (bool) $m->by_owner,
                'uploader_name' => $m->uploader_name,
            ], $materials),
        ];
    }

    /** One message for a whole batch. */
    public function notifyUploads(array $summary): void
    {
        $token = (string) config('noteshare.telegram_bot_token');
        $chatId = (string) config('noteshare.telegram_chat_id');

        // Feature off unless fully configured.
        if ($token === '' || $chatId === '' || empty($summary['files'])) {
            return;
        }

        try {
            $response = Http::asJson()
                ->timeout(5)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => count($summary['files']) === 1
                        ? $this->message($summary, $summary['files'][0])
                        : $this->batchMessage($summary),
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

            $this->noteResult($response->successful());
            if ($response->failed()) {
                Log::warning('Telegram upload notification failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            // Network error, DNS, timeout — never propagate to the uploader.
            $this->noteResult(false);
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

            $this->noteResult($response->successful());
            if ($response->failed()) {
                Log::warning('Telegram abuse report failed', [
                    'status' => $response->status(),
                    'material_id' => $material->id,
                    'reason' => $reason,
                ]);
            }
        } catch (\Throwable $e) {
            $this->noteResult(false);
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
            $response = Http::asJson()->timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => "🧹 {$count} empty ".($count === 1 ? 'board' : 'boards').' removed: no files and not opened for 30 days.',
            ]);
            $this->noteResult($response->successful());
        } catch (\Throwable $e) {
            $this->noteResult(false);
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

            $this->noteResult($response->successful());
            if ($response->failed()) {
                Log::warning('Telegram suggestion notification failed', ['status' => $response->status()]);
            }
        } catch (\Throwable $e) {
            $this->noteResult(false);
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

    private function message(array $summary, array $file): string
    {
        $section = $this->esc(ucfirst($file['section']));
        $code = $this->esc($summary['code']);
        $name = $this->esc($file['filename']);
        $url = $this->esc($summary['url']);

        $headline = filled($file['title']) ? $this->esc($file['title']) : $name;

        $meta = "New in <b>{$code}</b> · {$section}";

        $detail = filled($file['title']) ? "<i>{$name}</i>" : '';
        $by = $this->uploadedBy($file);
        if ($by !== '') {
            $detail = $detail === '' ? $by : "{$detail} — {$by}";
        }

        return "📄 <b>{$headline}</b>\n"
            .$meta."\n"
            .($detail !== '' ? $detail."\n" : '')
            ."<a href=\"{$url}\">Browse {$code} →</a>";
    }

    private function batchMessage(array $summary): string
    {
        $files = $summary['files'];
        $count = count($files);
        $code = $this->esc($summary['code']);
        $url = $this->esc($summary['url']);
        $sections = collect($files)->pluck('section')->unique()->map(fn ($s) => $this->esc(ucfirst($s)))->implode(', ');

        $lines = collect($files)->take(8)->map(fn ($f) => '· '.$this->esc(filled($f['title']) ? $f['title'] : $f['filename']));
        if ($count > 8) {
            $lines->push('· and '.($count - 8).' more');
        }
        $by = $this->uploadedBy($files[0]);

        return "📄 <b>{$count} files</b>\n"
            ."New in <b>{$code}</b> · {$sections}".($by !== '' ? " — {$by}" : '')."\n"
            .$lines->implode("\n")."\n"
            ."<a href=\"{$url}\">Browse {$code} →</a>";
    }

    private function uploadedBy(array $file): string
    {
        return match (true) {
            $file['by_owner'] => 'uploaded by the board owner',
            filled($file['uploader_name']) => 'uploaded by '.$this->esc($file['uploader_name']),
            default => '',
        };
    }

    /** The operator's "send a test": true when Telegram accepted it, which also clears the failure note. */
    public function notifyTest(): bool
    {
        $token = (string) config('noteshare.telegram_bot_token');
        $chatId = (string) config('noteshare.telegram_chat_id');
        if ($token === '' || $chatId === '') {
            return false;
        }

        try {
            $ok = Http::asJson()->timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => '✅ SlipNote test: alerts reach this chat.',
            ])->successful();
        } catch (\Throwable $e) {
            Log::warning('Telegram test threw', ['message' => $this->safeMessage($e)]);
            $ok = false;
        }

        $this->noteResult($ok);

        return $ok;
    }

    /** Remembered so the operator dashboard can say alerts stopped arriving. */
    private function noteResult(bool $ok): void
    {
        $ok ? Cache::forget(self::FAILED_KEY) : Cache::put(self::FAILED_KEY, now()->toIso8601String(), now()->addDays(14));
    }

    public const FAILED_KEY = 'telegram_last_failed_at';

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
