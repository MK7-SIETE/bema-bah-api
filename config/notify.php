<?php

// Alerts sent to the site owner when a customer sends a rental request.
// Every value is optional: a channel with no value is simply skipped.
//   NOTIFY_EMAIL              where the email alert goes (needs working mail settings)
//   NOTIFY_TELEGRAM_TOKEN     the bot token from @BotFather (a SECRET: never commit it or paste it in chats)
//   NOTIFY_TELEGRAM_CHAT_ID   your Telegram chat id
return [
    'email'            => env('NOTIFY_EMAIL'),
    'telegram_token'   => env('NOTIFY_TELEGRAM_TOKEN'),
    'telegram_chat_id' => env('NOTIFY_TELEGRAM_CHAT_ID'),
];
