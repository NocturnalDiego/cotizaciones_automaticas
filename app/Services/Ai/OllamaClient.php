<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class OllamaClient
{
    public function __construct(private readonly HttpFactory $http)
    {
    }

    public function chat(string $prompt, ?string $systemPrompt = null, bool $jsonMode = false): string
    {
        $baseUrl = rtrim((string) config('services.ollama.base_url', 'http://127.0.0.1:11434'), '/');
        $model = (string) config('services.ollama.model', 'llama3:latest');
        $timeout = (int) config('services.ollama.timeout', 120);

        $payload = [
            'model' => $model,
            'stream' => false,
            'messages' => array_values(array_filter([
                $systemPrompt ? [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ] : null,
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ])),
        ];

        if ($jsonMode) {
            $payload['format'] = 'json';
        }

        try {
            $response = $this->http
                ->baseUrl($baseUrl)
                ->timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post('/api/chat', $payload)
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException('No se pudo consultar Ollama local. Verifica que el servicio este activo.', previous: $exception);
        }

        $content = data_get($response->json(), 'message.content');

        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('Ollama respondio sin contenido utilizable.');
        }

        return trim($content);
    }
}
