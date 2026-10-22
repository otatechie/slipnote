<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramTest extends Command
{
    protected $signature = 'noteshare:telegram-test';

    protected $description = 'Send a test message to the configured Telegram channel';

    public function handle(): int
    {
        $token = (string) config('noteshare.telegram_bot_token');
        $chatId = (string) config('noteshare.telegram_chat_id');

        if ($token === '' || $chatId === '') {
            $this->error('Telegram is not configured.');
            $this->line('Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID in .env, then `php artisan config:clear`.');

            return self::FAILURE;
        }

        $this->line("Posting a test message to <comment>{$chatId}</comment>…");

        try {
            $response = Http::asJson()
                ->timeout(5)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => '✅ Notebird is connected. New uploads will post here.',
                ]);
        } catch (\Throwable $e) {
            $this->error('Request failed: '.$this->redact($e->getMessage()));

            return self::FAILURE;
        }

        if ($response->successful()) {
            $this->info('Sent. Check the channel — you should see the test message.');

            return self::SUCCESS;
        }

        $this->error("Telegram rejected the request (HTTP {$response->status()}).");
        $this->line($this->redact($response->body()));
        $this->newLine();
        $this->line('Common causes: wrong token, bot is not an admin of the channel,');
        $this->line('or the chat id is wrong (use @channelname or the -100… numeric id).');

        return self::FAILURE;
    }

    private function redact(string $text): string
    {
        return preg_replace('#\d{6,}:[A-Za-z0-9_-]{30,}#', '[REDACTED-TOKEN]', $text);
    }
}
