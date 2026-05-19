<?php

return [

    // NOTE: owner secret and upload passphrase are now PER-WORKSPACE
    // (columns on the workspaces table, set at creation), not global env
    // values — see App\Models\Workspace.

    /*
    | Telegram new-upload notifications. When BOTH are set, every successful
    | upload posts a message (course, section, file, link) to the channel.
    | Leave either empty to disable — uploads are unaffected either way, and
    | a Telegram outage never blocks or slows an upload.
    |
    |   TELEGRAM_BOT_TOKEN  — from @BotFather
    |   TELEGRAM_CHAT_ID    — channel/group the bot posts to (e.g. @mychannel
    |                         or a numeric -100... id; the bot must be a member)
    */
    'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'telegram_chat_id' => env('TELEGRAM_CHAT_ID'),

    /*
    | Storage limits. The per-workspace cap is the primary control; the
    | global disk-free check is a safety net so the host machine itself
    | never runs out of room.
    |
    |   WORKSPACE_STORAGE_BYTES  — soft cap per workspace (default 500 MB)
    |   MIN_FREE_DISK_BYTES      — refuse uploads when host free space drops
    |                              below this (default 1 GB)
    */
    'workspace_storage_bytes' => (int) env('WORKSPACE_STORAGE_BYTES', 500 * 1024 * 1024),
    'min_free_disk_bytes' => (int) env('MIN_FREE_DISK_BYTES', 1024 * 1024 * 1024),

    // Date shown at the top of the Privacy and Terms pages. Bump when the
    // wording materially changes.
    'legal_updated' => '2026-05-19',

];
