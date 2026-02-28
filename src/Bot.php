<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/CommandRouter.php';

class Bot
{
    public function handleMessage(string $message, string $whatsappId, string $username, ?array $repliedTo = null): string
    {
        Database::migrate();
        return CommandRouter::handle($message, $whatsappId, $username, $repliedTo);
    }
}
