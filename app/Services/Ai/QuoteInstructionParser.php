<?php

namespace App\Services\Ai;

use RuntimeException;
use Throwable;

class QuoteInstructionParser
{
    public function __construct(private readonly OllamaClient $ollamaClient)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function parse(string $message): array
    {
        $systemPrompt = <<<'PROMPT'
Eres un asistente para capturar cotizaciones en Mexico.
Tu tarea es convertir instrucciones del usuario a JSON estricto.
No agregues texto fuera del JSON.
No uses markdown.
No calcules IVA.

Responde con este esquema exacto:
{
  "can_create": true,
  "reason": "",
  "quote": {
    "reference_code": "",
    "client_name": "",
    "client_rfc": "",
    "location": "",
    "issued_at": "YYYY-MM-DD",
    "terms": "",
    "contact_name": "",
    "contact_email": "",
    "contact_phone": "",
    "items": [
      {
        "description": "",
        "quantity": 1,
        "unit_price": 100
      }
    ]
  }
}

Reglas:
- Si faltan datos criticos para crear cotizacion (cliente o items), responde can_create=false y explica reason.
- Si hay un solo item, items debe incluir un arreglo con un objeto.
- quantity y unit_price deben ser numericos.
PROMPT;

        try {
            $raw = $this->ollamaClient->chat(
                prompt: $message,
                systemPrompt: $systemPrompt,
                jsonMode: true
            );
        } catch (Throwable $exception) {
            return $this->fallbackFromText($message);
        }

        try {
            $decoded = $this->decodeJson($raw);

            return $this->normalize($decoded);
        } catch (RuntimeException) {
            return $this->fallbackFromText($message);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $raw): array
    {
        $candidate = trim($raw);

        if (str_starts_with($candidate, '```')) {
            $candidate = preg_replace('/^```(?:json)?\s*/', '', $candidate) ?? $candidate;
            $candidate = preg_replace('/\s*```$/', '', $candidate) ?? $candidate;
            $candidate = trim($candidate);
        }

        $decoded = json_decode($candidate, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $candidate, $matches) === 1) {
            $decoded = json_decode($matches[0], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new RuntimeException('La respuesta de la IA no tiene JSON valido.');
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    private function normalize(array $decoded): array
    {
        $canCreate = (bool) ($decoded['can_create'] ?? false);
        $reason = trim((string) ($decoded['reason'] ?? ''));
        $quote = is_array($decoded['quote'] ?? null) ? $decoded['quote'] : [];

        $items = [];

        foreach (($quote['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $description = trim((string) ($item['description'] ?? ''));
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);

            if ($description === '' || $quantity <= 0 || $unitPrice < 0) {
                continue;
            }

            $items[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ];
        }

        $normalized = [
            'can_create' => $canCreate,
            'reason' => $reason,
            'quote' => [
                'reference_code' => trim((string) ($quote['reference_code'] ?? '')),
                'client_name' => trim((string) ($quote['client_name'] ?? '')),
                'client_rfc' => trim((string) ($quote['client_rfc'] ?? '')),
                'location' => trim((string) ($quote['location'] ?? '')),
                'issued_at' => trim((string) ($quote['issued_at'] ?? '')),
                'terms' => trim((string) ($quote['terms'] ?? '')),
                'contact_name' => trim((string) ($quote['contact_name'] ?? '')),
                'contact_email' => trim((string) ($quote['contact_email'] ?? '')),
                'contact_phone' => trim((string) ($quote['contact_phone'] ?? '')),
                'items' => $items,
            ],
        ];

        if ($normalized['quote']['client_name'] === '' || $normalized['quote']['items'] === []) {
            $normalized['can_create'] = false;

            if ($normalized['reason'] === '') {
                $normalized['reason'] = 'Faltan datos minimos para crear la cotizacion.';
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackFromText(string $message): array
    {
        $text = trim($message);

        $clientName = $this->extractPattern($text, '/cliente\s+([^,;\.\n]+)/iu');
        $referenceCode = $this->extractPattern($text, '/(?:pedido|proyecto|referencia|folio)\s*[:#]?\s*([A-Z0-9\-]+)/iu');
        $location = $this->extractPattern($text, '/ubicaci[oó]n\s+([^,;\.\n]+)/iu');
        $contactName = $this->extractPattern($text, '/contacto\s*:?\s*([^,;\.\n]+)/iu');
        $contactEmail = $this->extractPattern($text, '/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i');
        $contactPhone = $this->extractPattern($text, '/(\+?\d[\d\s\-]{7,}\d)/');
        $terms = $this->extractPattern($text, '/t[eé]rminos?\s*:?\s*(.+?)(?:\s+contacto\s*:|$)/iu');
        $issuedAt = $this->extractDate($text);

        $items = $this->extractItems($text);

        $canCreate = $clientName !== '' && $items !== [];

        return [
            'can_create' => $canCreate,
            'reason' => $canCreate ? '' : 'No se detectaron suficientes datos para crear la cotización. Incluye cliente y al menos un concepto con cantidad y precio unitario.',
            'quote' => [
                'reference_code' => $referenceCode,
                'client_name' => $clientName,
                'client_rfc' => '',
                'location' => $location,
                'issued_at' => $issuedAt,
                'terms' => $terms,
                'contact_name' => $contactName,
                'contact_email' => $contactEmail,
                'contact_phone' => preg_replace('/\D+/', '', $contactPhone) ?? '',
                'items' => $items,
            ],
        ];
    }

    /**
     * @return array<int, array<string, float|string>>
     */
    private function extractItems(string $text): array
    {
        $items = [];

        // 1. Buscar listas numeradas tipo: 1.- descripcion ... costo ...
        if (preg_match_all('/\n?\s*(\d+)[\.-]\s*(.+?)(?:\s+con\s+un\s+costo\s+de|\s+costo\s+de|\s+con\s+costo\s+de|\s+con\s+un\s+precio\s+de|\s+precio\s+de|\s+con\s+precio\s+de|\s+con\s+un\s+valor\s+de|\s+valor\s+de|\s+por\s+un\s+monto\s+de|\s+monto\s+de)?\s*([\d.,]+\s*(mil|miles|mill[oó]n(?:es)?)?)/iu', $text, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $description = trim((string) ($match[2] ?? ''));
                $unitPriceRaw = trim((string) ($match[3] ?? ''));
                $unitPrice = $this->parseAmountExpression($unitPriceRaw);
                if ($description === '' || $unitPrice <= 0) {
                    continue;
                }
                $items[] = [
                    'description' => $description,
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                ];
            }
        }

        // 2. Si no encontró nada, buscar patrones antiguos (partida ...)
        if ($items === [] && preg_match_all('/partida\s+\d+\s+(.*?)(?=(?:\bpartida\s+\d+\b)|$)/isu', $text, $segments, PREG_SET_ORDER) > 0) {
            foreach ($segments as $segment) {
                $body = trim((string) ($segment[1] ?? ''));
                if ($body === '') {
                    continue;
                }
                $description = $this->extractPattern($body, '/(?:de\s+)?(.+?)(?:\s+con\s+cantidad|\s+cantidad)/iu');
                $quantityRaw = $this->extractPattern($body, '/cantidad\s+([\d.,]+)/iu');
                $unitPriceRaw = $this->extractPattern($body, '/precio\s+unitario\s+(.+)$/iu');
                $quantity = $this->parseFlexibleNumber($quantityRaw);
                $unitPrice = $this->parseAmountExpression($unitPriceRaw);
                if ($description === '' || $quantity <= 0 || $unitPrice < 0) {
                    continue;
                }
                $items[] = [
                    'description' => $description,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ];
            }
        }

        // 3. Si sigue vacío, buscar frases tipo: "concepto ... costo ..."
        if ($items === [] && preg_match_all('/concepto\s*:?\s*(.+?)\s+(?:costo|precio|monto)\s*:?\s*([\d.,]+\s*(mil|miles|mill[oó]n(?:es)?)?)/iu', $text, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $description = trim((string) ($match[1] ?? ''));
                $unitPriceRaw = trim((string) ($match[2] ?? ''));
                $unitPrice = $this->parseAmountExpression($unitPriceRaw);
                if ($description === '' || $unitPrice <= 0) {
                    continue;
                }
                $items[] = [
                    'description' => $description,
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                ];
            }
        }

        return $items;
    }

    private function parseAmountExpression(string $value): float
    {
        $normalized = mb_strtolower(trim($value));

        if ($normalized === '') {
            return 0.0;
        }

        $multiplier = 1.0;

        if (preg_match('/\bmil(?:es)?\b/u', $normalized) === 1) {
            $multiplier = 1000.0;
            $normalized = preg_replace('/\bmil(?:es)?\b/u', '', $normalized) ?? $normalized;
        }

        if (preg_match('/\bmill[oó]n(?:es)?\b/u', $normalized) === 1) {
            $multiplier = 1000000.0;
            $normalized = preg_replace('/\bmill[oó]n(?:es)?\b/u', '', $normalized) ?? $normalized;
        }

        $number = $this->parseFlexibleNumber($normalized);

        if ($number > 0) {
            return $number * $multiplier;
        }

        $wordsValue = $this->parseSpanishNumberWords($normalized);

        if ($wordsValue > 0) {
            return $wordsValue * $multiplier;
        }

        return 0.0;
    }

    private function parseFlexibleNumber(string $value): float
    {
        $clean = preg_replace('/[^\d,\.\-]/', '', trim($value)) ?? '0';

        if ($clean === '' || $clean === '-' || $clean === '.' || $clean === ',') {
            return 0.0;
        }

        $commaCount = substr_count($clean, ',');
        $dotCount = substr_count($clean, '.');

        if ($commaCount > 0 && $dotCount > 0) {
            $lastComma = strrpos($clean, ',');
            $lastDot = strrpos($clean, '.');

            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }

            return (float) $clean;
        }

        if ($commaCount > 0) {
            if ($commaCount > 1) {
                return (float) str_replace(',', '', $clean);
            }

            [$left, $right] = array_pad(explode(',', $clean, 2), 2, '');

            if (strlen($right) === 3 && strlen($left) >= 1) {
                return (float) ($left.$right);
            }

            return (float) ($left.'.'.$right);
        }

        if ($dotCount > 0) {
            if ($dotCount > 1) {
                return (float) str_replace('.', '', $clean);
            }

            [$left, $right] = array_pad(explode('.', $clean, 2), 2, '');

            if (strlen($right) === 3 && strlen($left) >= 1) {
                return (float) ($left.$right);
            }

            return (float) $clean;
        }

        return (float) $clean;
    }

    private function parseSpanishNumberWords(string $text): float
    {
        $normalized = mb_strtolower(trim($text));

        if ($normalized === '') {
            return 0.0;
        }

        $normalized = strtr($normalized, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $normalized = preg_replace('/[^a-z\s]/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if ($normalized === '') {
            return 0.0;
        }

        $units = [
            'cero' => 0,
            'un' => 1,
            'uno' => 1,
            'una' => 1,
            'dos' => 2,
            'tres' => 3,
            'cuatro' => 4,
            'cinco' => 5,
            'seis' => 6,
            'siete' => 7,
            'ocho' => 8,
            'nueve' => 9,
            'diez' => 10,
            'once' => 11,
            'doce' => 12,
            'trece' => 13,
            'catorce' => 14,
            'quince' => 15,
            'dieciseis' => 16,
            'diecisiete' => 17,
            'dieciocho' => 18,
            'diecinueve' => 19,
            'veinte' => 20,
            'veintiuno' => 21,
            'veintidos' => 22,
            'veintitres' => 23,
            'veinticuatro' => 24,
            'veinticinco' => 25,
            'veintiseis' => 26,
            'veintisiete' => 27,
            'veintiocho' => 28,
            'veintinueve' => 29,
        ];

        $tens = [
            'treinta' => 30,
            'cuarenta' => 40,
            'cincuenta' => 50,
            'sesenta' => 60,
            'setenta' => 70,
            'ochenta' => 80,
            'noventa' => 90,
        ];

        $hundreds = [
            'cien' => 100,
            'ciento' => 100,
            'doscientos' => 200,
            'trescientos' => 300,
            'cuatrocientos' => 400,
            'quinientos' => 500,
            'seiscientos' => 600,
            'setecientos' => 700,
            'ochocientos' => 800,
            'novecientos' => 900,
        ];

        $scales = [
            'mil' => 1000,
            'miles' => 1000,
            'millon' => 1000000,
            'millones' => 1000000,
        ];

        $tokens = explode(' ', $normalized);
        $current = 0;
        $total = 0;
        $matched = false;

        foreach ($tokens as $token) {
            if ($token === '' || $token === 'y') {
                continue;
            }

            if (isset($units[$token])) {
                $current += $units[$token];
                $matched = true;

                continue;
            }

            if (isset($tens[$token])) {
                $current += $tens[$token];
                $matched = true;

                continue;
            }

            if (isset($hundreds[$token])) {
                $current += $hundreds[$token];
                $matched = true;

                continue;
            }

            if (isset($scales[$token])) {
                $scale = $scales[$token];
                $base = $current === 0 ? 1 : $current;
                $total += $base * $scale;
                $current = 0;
                $matched = true;
            }
        }

        if (!$matched) {
            return 0.0;
        }

        return (float) ($total + $current);
    }

    private function extractDate(string $text): string
    {
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/\b(\d{2}\/\d{2}\/\d{4})\b/', $text, $matches) === 1) {
            $timestamp = strtotime(str_replace('/', '-', $matches[1]));

            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        }

        return now()->toDateString();
    }

    private function extractPattern(string $text, string $pattern): string
    {
        if (preg_match($pattern, $text, $matches) !== 1) {
            return '';
        }

        return trim((string) ($matches[1] ?? $matches[0] ?? ''));
    }
}
