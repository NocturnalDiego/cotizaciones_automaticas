<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class TelegramClient
{
    public function __construct(private readonly HttpFactory $http)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUpdates(int $offset, int $timeout = 25): array
    {
        $payload = [
            'offset' => $offset,
            'timeout' => max(1, min($timeout, 50)),
            'allowed_updates' => ['message', 'callback_query'],
        ];

        $response = $this->postJson('getUpdates', $payload);
        $updates = $response['result'] ?? [];

        return is_array($updates) ? $updates : [];
    }

    public function sendMessage(int|string $chatId, string $text): void
    {
        $this->postJson('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $replyMarkup
     */
    public function sendMessageWithMarkup(int|string $chatId, string $text, ?array $replyMarkup = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $this->postJson('sendMessage', $payload);
    }

    public function sendDocument(int|string $chatId, string $filePath, ?string $caption = null): void
    {
        if (!is_file($filePath)) {
            throw new RuntimeException('No existe el archivo PDF para enviar a Telegram.');
        }

        $payload = [
            'chat_id' => $chatId,
        ];

        if ($caption !== null && trim($caption) !== '') {
            $payload['caption'] = $caption;
        }

        $documentContents = file_get_contents($filePath);

        if ($documentContents === false) {
            throw new RuntimeException('No se pudo leer el archivo PDF para Telegram.');
        }

        try {
            $response = $this->http
                ->timeout(120)
                ->acceptJson()
                ->attach('document', $documentContents, basename($filePath))
                ->post($this->buildUrl('sendDocument'), $payload)
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException('Fallo al enviar PDF a Telegram.', previous: $exception);
        }

        $json = $response->json();

        if (!is_array($json) || ($json['ok'] ?? false) !== true) {
            throw new RuntimeException('Telegram rechazo el envio del documento PDF.');
        }
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): void
    {
        $payload = [
            'callback_query_id' => $callbackQueryId,
        ];

        if ($text !== null && trim($text) !== '') {
            $payload['text'] = $text;
        }

        $this->postJson('answerCallbackQuery', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postJson(string $method, array $payload): array
    {
        try {
            $response = $this->http
                ->timeout(90)
                ->acceptJson()
                ->asJson()
                ->post($this->buildUrl($method), $payload)
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException('Fallo al comunicar con Telegram.', previous: $exception);
        }

        $json = $response->json();

        if (!is_array($json) || ($json['ok'] ?? false) !== true) {
            throw new RuntimeException('Telegram respondio con error para el metodo '.$method.'.');
        }

        return $json;
    }

    private function buildUrl(string $method): string
    {
        $token = (string) config('services.telegram.bot_token', '');

        if ($token === '') {
            throw new RuntimeException('Configura TELEGRAM_BOT_TOKEN en el archivo .env.');
        }

        $baseUrl = rtrim((string) config('services.telegram.base_url', 'https://api.telegram.org'), '/');

        return $baseUrl.'/bot'.$token.'/'.$method;
    }
}
