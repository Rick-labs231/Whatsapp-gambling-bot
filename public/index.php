<?php

require_once __DIR__ . '/../src/Bot.php';

$bot = new Bot();

/*
  TEMP SIMULATION
  Later WhatsApp will replace this
*/

$message     = $_GET['message'] ?? '.register';
$whatsappId  = $_GET['from'] ?? 'user_123';
$username    = $_GET['name'] ?? 'Rick';

$response = $bot->handleMessage($message, $whatsappId, $username);

echo $response;
