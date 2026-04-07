<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

Route::post('/telegram-webhook', function (Request $request) {
    $data = $request->all();
    
    // 1. Logueamos para ver qué llega (mirá storage/logs/laravel.log)
    Log::info('Telegram Data:', $data);

    $chatId = $data['message']['chat']['id'] ?? null;
    $text = $data['message']['text'] ?? '';

    if ($chatId && $text) {
        // 2. Respondemos algo simple usando el cliente HTTP de Laravel
        Http::post("https://api.telegram.org/bot".env('TELEGRAM_TOKEN')."/sendMessage", [
            'chat_id' => $chatId,
            'text' => "Recibí tu mensaje: " . $text,
        ]);
    }

    return response()->json(['status' => 'success'], 200);
});