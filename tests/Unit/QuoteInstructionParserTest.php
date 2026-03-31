<?php

use App\Services\Ai\OllamaClient;
use App\Services\Ai\QuoteInstructionParser;

test('quote parser uses fallback extraction when ai output is not valid json', function () {
    $fakeClient = new class extends OllamaClient {
        public function __construct()
        {
        }

        public function chat(string $prompt, ?string $systemPrompt = null, bool $jsonMode = false): string
        {
            return 'respuesta libre sin json valido';
        }
    };

    $parser = new QuoteInstructionParser($fakeClient);

    $result = $parser->parse('Crea una cotizacion para cliente Nutec, pedido 4K097, ubicacion Tecamac, una partida de configuracion de 1272 posiciones con cantidad 1272 y precio unitario 90, contacto Contacto de Prueba, correo contacto.pruebas@example.com, telefono 5510000000.');

    expect($result['can_create'])->toBeTrue();
    expect($result['quote']['client_name'])->toBe('Nutec');
    expect($result['quote']['reference_code'])->toBe('4K097');
    expect($result['quote']['items'])->toHaveCount(1);
    expect((float) $result['quote']['items'][0]['quantity'])->toBe(1272.0);
    expect((float) $result['quote']['items'][0]['unit_price'])->toBe(90.0);
});

test('quote parser fallback marks message as incomplete when there is no client or items', function () {
    $fakeClient = new class extends OllamaClient {
        public function __construct()
        {
        }

        public function chat(string $prompt, ?string $systemPrompt = null, bool $jsonMode = false): string
        {
            return 'respuesta libre sin json valido';
        }
    };

    $parser = new QuoteInstructionParser($fakeClient);

    $result = $parser->parse('Necesito una cotizacion urgente para hoy.');

    expect($result['can_create'])->toBeFalse();
    expect($result['reason'])->toContain('No se detectaron suficientes datos');
});

test('quote parser can handle long message with many partidas when ai is unavailable', function () {
    $fakeClient = new class extends OllamaClient {
        public function __construct()
        {
        }

        public function chat(string $prompt, ?string $systemPrompt = null, bool $jsonMode = false): string
        {
            throw new RuntimeException('Sin respuesta de IA');
        }
    };

    $parser = new QuoteInstructionParser($fakeClient);

    $message = 'Crea una cotizacion para cliente Grupo Almacenes del Norte S.A. de C.V., pedido PROY-4K151, ubicacion Tultitlan, fecha 2026-03-28, terminos: Prueba de carga de texto largo. Contacto: Diego Hernandez, correo diego.hernandez@almacenesnorte.com, telefono 5512345678. '
        .'Partida 1 de bastidor selectivo con cantidad 80 y precio unitario 1,450. '
        .'Partida 2 de vigas estructurales con cantidad 320 y precio unitario 620. '
        .'Partida 3 de protectores de marco con cantidad 160 y precio unitario 210. '
        .'Partida 4 de anclaje quimico con cantidad 640 y precio unitario 48. '
        .'Partida 5 de nivelacion de estructura con cantidad 80 y precio unitario 180. '
        .'Partida 6 de parrilla metalica con cantidad 240 y precio unitario 395. '
        .'Partida 7 de topes de seguridad con cantidad 120 y precio unitario 275. '
        .'Partida 8 de pasillo tecnico con cantidad 2 y precio unitario 50 mil. '
        .'Partida 9 de corte y ajuste de perfiles con cantidad 75 y precio unitario 125. '
        .'Partida 10 de retoque anticorrosivo con cantidad 220 y precio unitario 95. '
        .'Partida 11 de senaletica operativa con cantidad 40 y precio unitario 165. '
        .'Partida 12 de maniobra de izaje con cantidad 6 y precio unitario 4,200. '
        .'Partida 13 de supervision tecnica con cantidad 5 y precio unitario cien mil. '
        .'Partida 14 de pruebas de estabilidad con cantidad 1 y precio unitario 100,000. '
        .'Partida 15 de entrega y cierre con cantidad 1 y precio unitario 6,500.';

    $result = $parser->parse($message);

    expect($result['can_create'])->toBeTrue();
    expect($result['quote']['client_name'])->toContain('Grupo Almacenes del Norte');
    expect($result['quote']['reference_code'])->toBe('PROY-4K151');
    expect($result['quote']['items'])->toHaveCount(15);
    expect((float) $result['quote']['items'][0]['unit_price'])->toBe(1450.0);
    expect((float) $result['quote']['items'][7]['unit_price'])->toBe(50000.0);
    expect((float) $result['quote']['items'][12]['unit_price'])->toBe(100000.0);
    expect((float) $result['quote']['items'][13]['unit_price'])->toBe(100000.0);
});

test('quote parser fallback extracts quantity and price from Lista numerada con C/U y lugar expresado vagamente', function () {
    $fakeClient = new class extends OllamaClient {
        public function __construct()
        {
        }

        public function chat(string $prompt, ?string $systemPrompt = null, bool $jsonMode = false): string
        {
            return 'respuesta libre sin json valido';
        }
    };

    $parser = new QuoteInstructionParser($fakeClient);

    $message = "Crea una cotización para el cliente Walmart, el lugar es Puebla con los siguientes conceptos:\n"
        ."1.- 50 frascos de mayonesa con un costo de $45 C/U\n"
        ."2.- 20 cajas de desodorante con un costo de $50 C/U";

    $result = $parser->parse($message);

    expect($result['can_create'])->toBeTrue();
    expect($result['quote']['client_name'])->toBe('Walmart');
    expect($result['quote']['location'])->toBe('Puebla');
    expect($result['quote']['items'])->toHaveCount(2);
    expect((float) $result['quote']['items'][0]['quantity'])->toBe(50.0);
    expect((float) $result['quote']['items'][0]['unit_price'])->toBe(45.0);
    expect(str_contains(strtolower($result['quote']['items'][0]['description']), 'mayonesa'))->toBeTrue();
    expect((float) $result['quote']['items'][1]['quantity'])->toBe(20.0);
    expect((float) $result['quote']['items'][1]['unit_price'])->toBe(50.0);
    expect(str_contains(strtolower($result['quote']['items'][1]['description']), 'desodorante'))->toBeTrue();
});

test('quote parser fallback acepta mensajes vagos con numero y unidad y sigue creando', function () {
    $fakeClient = new class extends OllamaClient {
        public function __construct()
        {
        }

        public function chat(string $prompt, ?string $systemPrompt = null, bool $jsonMode = false): string
        {
            return 'respuesta libre sin json valido';
        }
    };

    $parser = new QuoteInstructionParser($fakeClient);

    $message = "Por favor cotiza cliente Bodega Aurrera, lugar CDMX, incluye:\n"
        ."- 15 packs de agua con costo de 18 C/U\n"
        ."- 2 sets de toallas con un costo de $120 cada unidad";

    $result = $parser->parse($message);

    expect($result['can_create'])->toBeTrue();
    expect($result['quote']['client_name'])->toBe('Bodega Aurrera');
    expect($result['quote']['location'])->toBe('CDMX');
    expect($result['quote']['items'])->toHaveCount(2);
});
