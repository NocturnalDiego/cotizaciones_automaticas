<?php

use App\Services\Ai\OllamaClient;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramQuoteBotService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ai:probar {mensaje* : Mensaje a enviar a Ollama}', function (OllamaClient $ollamaClient) {
    $mensaje = trim(implode(' ', (array) $this->argument('mensaje')));

    if ($mensaje === '') {
        $this->error('Debes indicar un mensaje.');

        return 1;
    }

    $this->info('Consultando Ollama local...');

    try {
        $respuesta = $ollamaClient->chat(
            prompt: $mensaje,
            systemPrompt: (string) config('services.ollama.system_prompt')
        );
    } catch (\Throwable $exception) {
        $this->error('No fue posible obtener respuesta de Ollama.');
        $this->line($exception->getMessage());

        return 1;
    }

    $this->newLine();
    $this->line('Respuesta de la IA:');
    $this->line($respuesta);

    return 0;
})->purpose('Probar IA local de Ollama en este proyecto.');

Artisan::command('telegram:escuchar-cotizaciones {--once : Ejecuta un ciclo unico de polling}', function (
    TelegramClient $telegramClient,
    TelegramQuoteBotService $botService
) {
    $timeout = (int) config('services.telegram.polling_timeout', 25);
    $offset = (int) Cache::get('telegram.cotizaciones.offset', 0);

    $this->info('Bot Telegram de cotizaciones activo.');
    $this->line('Offset inicial: '.$offset);

    do {
        try {
            $updates = $telegramClient->getUpdates($offset + 1, $timeout);
        } catch (\Throwable $exception) {
            $this->error('No fue posible consultar updates de Telegram.');
            $this->line($exception->getMessage());

            return 1;
        }

        foreach ($updates as $update) {
            $updateId = (int) data_get($update, 'update_id', 0);

            if ($updateId > $offset) {
                $offset = $updateId;
                Cache::forever('telegram.cotizaciones.offset', $offset);
            }

            try {
                $botService->processUpdate($update);
            } catch (\Throwable $exception) {
                $this->error('Error al procesar update '.$updateId.': '.$exception->getMessage());
            }
        }
    } while (!$this->option('once'));

    return 0;
})->purpose('Escuchar Telegram por polling y operar cotizaciones con flujo guiado y acciones del bot.');

Artisan::command('telegram:reiniciar-offset {offset=0 : Nuevo offset para polling}', function () {
    $offset = (int) $this->argument('offset');
    Cache::forever('telegram.cotizaciones.offset', max(0, $offset));
    $this->info('Offset actualizado a '.max(0, $offset).'.');

    return 0;
})->purpose('Reiniciar offset del bot de Telegram para controlar updates.');
