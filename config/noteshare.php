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

];
