<?php

// Owner secrets and upload passphrases are per board (workspaces table), not here.

return [

    // Upload notifications. Set both to enable; a Telegram outage never blocks an upload.
    'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'telegram_chat_id' => env('TELEGRAM_CHAT_ID'),

    // Unlocks /operator (remove any reported file). No reset: change it and redeploy.
    'operator_secret' => env('OPERATOR_SECRET'),

    // Public contact for reports (Terms, /lecturers). Empty omits it.
    'contact_email' => env('CONTACT_EMAIL'),

    // Who runs this deployment (footer, /lecturers); empty hides it, as
    // SlipNote is self-hostable. The takedown time is promised publicly:
    // only promise what you keep.
    'operator_name' => env('OPERATOR_NAME'),
    'takedown_turnaround' => env('TAKEDOWN_TURNAROUND', '3 working days'),

    // Per-board byte and file-count caps, and the host free-space floor.
    'workspace_storage_bytes' => (int) env('WORKSPACE_STORAGE_BYTES', 500 * 1024 * 1024),
    'min_free_disk_bytes' => (int) env('MIN_FREE_DISK_BYTES', 1024 * 1024 * 1024),
    'workspace_max_files' => (int) env('WORKSPACE_MAX_FILES', 2000),

    // Proxies whose X-Forwarded-* are believed (IPs/CIDRs). Never '*': every
    // client could then pick its own IP and bypass the per-IP rate limits.
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', ''))
    ))),

    // A header the trusted proxy in front sets to the visitor's own address
    // (CF-Connecting-IP behind Cloudflare). Read only on requests that come
    // straight from a TRUSTED_PROXIES address; empty reads no header.
    'client_ip_header' => trim((string) env('CLIENT_IP_HEADER', '')),

    // Ambassador sign-up form (footer link), and the monthly reward per used
    // board, paid by hand. Defaults: a 2 GB MTN bundle, Ghana 2026.
    'ambassador_form_url' => env('AMBASSADOR_FORM_URL'),
    'ambassador_reward' => (int) env('AMBASSADOR_REWARD', 12),
    'ambassador_currency' => env('AMBASSADOR_CURRENCY', 'GHS'),

    // "Tell us how" prompt: WhatsApp (digits only) and/or email. Both empty hides it.
    'feedback_whatsapp' => preg_replace('/\D+/', '', (string) env('FEEDBACK_WHATSAPP', '')),
    'feedback_email' => env('FEEDBACK_EMAIL', env('CONTACT_EMAIL')),

    // Shown atop Privacy and Terms; bump when the wording materially changes.
    'legal_updated' => '2026-10-19',

];
