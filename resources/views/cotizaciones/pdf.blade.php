<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, DejaVu Sans, sans-serif;
            color: #1f2937;
            background: #ffffff;
            font-size: 13px;
            line-height: 1.5;
        }

        .page {
            padding: 35px 42px 45px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 22px;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 12px;
        }

        .logo-box {
            width: 220px;
            min-height: 92px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }

        .logo-box img {
            max-width: 220px;
            max-height: 92px;
            object-fit: contain;
            display: block;
        }

        .company-info {
            text-align: right;
            font-weight: 700;
            font-size: 13px;
            line-height: 1.55;
        }

        .place-date {
            text-align: center;
            margin: 18px 0 24px;
            font-size: 13px;
        }

        .brand {
            font-size: 19px;
            font-weight: 700;           /* Solo negrita */
            text-align: center;
            margin: 0 0 24px 0;
        }

        .intro {
            margin: 0 0 22px;
            font-size: 13px;
            text-align: justify;
            line-height: 1.5;
        }

        .item {
            margin-bottom: 18px;
        }

        .item-description {
            margin: 0 0 7px 0;
            font-size: 13.2px;
            font-weight: 700;           /* Negrita fuerte en las descripciones (como en tu imagen) */
        }

        .line-row {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 6px;
        }

        .line-row td {
            vertical-align: bottom;
            padding-bottom: 4px;
            font-size: 13px;
        }

        .line-label {
            width: 20%;
            white-space: nowrap;
            font-weight: 400;           /* Normal */
        }

        .line-leader {
            border-bottom: 1px dotted #666666;
        }

        .line-value {
            width: 30%;
            text-align: right;
            font-weight: 700;           /* Negrita en los precios */
            white-space: nowrap;
            padding-left: 15px;
        }

        .summary-box {
            background: #f8f9fa;
            border: 1px solid #e2e5e8;
            padding: 14px 18px;
            margin: 25px 0 22px;
        }

        .summary-row {
            margin-bottom: 9px;
        }

        .summary-row:last-child {
            margin-bottom: 0;
        }

        .terms {
            margin: 22px 0 25px;
            font-size: 13px;
            line-height: 1.5;
            text-align: justify;
        }

        .signature {
            margin-top: 30px;
            font-size: 13px;
            line-height: 1.55;
        }

        .signature a {
            color: #1e40af;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="page">

        <!-- Header -->
        <div class="header">
            <div class="logo-box">
                @if ($logoPath)
                    <img src="{{ $logoPath }}" alt="Logo">
                @else
                    <span style="color:#999; font-size:11px;">LOGO</span>
                @endif
            </div>

            <div class="company-info">
                <div>{{ $appBranding?->issuer_name ?? '' }}</div>
                <div>R.F.C. {{ $appBranding?->issuer_rfc ?? '' }}</div>
                <div>{{ $appBranding?->issuer_business_name ?? '' }}</div>
            </div>
        </div>

        <p class="place-date">
            {{ trim(($quote->location ?? '').' '.$quote->issued_at->translatedFormat('j \d\e F \d\e\l Y').'.') }}
        </p>

        <div class="brand">{{ $appBranding?->quote_brand_name ?? '' }}</div>

        @php
            $projectReference = trim((string) ($quote->reference_code ?? ''));
            $clientName = trim((string) ($quote->client_name ?? ''));
            $pedidoPresentacion = $projectReference;

            if ($projectReference !== '' && $clientName !== '') {
                $pedidoPresentacion .= ' - '.$clientName;
            }
        @endphp

        <p class="intro">
            En Atención a su solicitud de cotización y de acuerdo a datos proporcionados por usted nos da mucho gusto 
            presentarle nuestra cotización del pedido {{ $pedidoPresentacion }}, la cual consiste en:
        </p>

        <!-- Items -->
        @foreach ($quote->items as $item)
            <div class="item">
                <p class="item-description">{{ $item->description }}</p>
                <table class="line-row">
                    <tr>
                        <td class="line-label">Con un costo de</td>
                        <td class="line-leader"></td>
                        <td class="line-value">${{ number_format((float) $item->line_total, 2) }}+IVA</td>
                    </tr>
                </table>
            </div>
        @endforeach

        <!-- Summary -->
        <div class="summary-box">
            <table class="line-row summary-row">
                <tr>
                    <td class="line-label" style="width:27%; font-weight:700;">Valor del proyecto</td>
                    <td class="line-leader"></td>
                    <td class="line-value">${{ number_format((float) $quote->subtotal, 2) }}+IVA</td>
                </tr>
            </table>

            @foreach ($quote->payments as $payment)
                <table class="line-row summary-row">
                    <tr>
                        <td class="line-label" style="width:27%; font-weight:400;">{{ $payment->label }}</td>
                        <td class="line-leader"></td>
                        <td class="line-value">${{ number_format((float) $payment->amount, 2) }}+IVA</td>
                    </tr>
                </table>
            @endforeach

            @if ($quote->payments->isNotEmpty())
                <table class="line-row summary-row">
                    <tr>
                        <td class="line-label" style="width:27%; font-weight:700;">Saldo pendiente</td>
                        <td class="line-leader"></td>
                        <td class="line-value">${{ number_format((float) $quote->balance_due, 2) }}+IVA</td>
                    </tr>
                </table>
            @endif
        </div>

        <p class="terms">{{ $quote->terms ?? '' }}</p>

        <!-- Contacto -->
        <div class="signature">
            <div style="font-weight:700;">{{ $quote->contact_name ?? '' }}</div>
            <div>
                @if ($quote->contact_email)
                    <a href="mailto:{{ $quote->contact_email }}">{{ $quote->contact_email }}</a>
                @else
                    
                @endif
            </div>
            <div>{{ $quote->contact_phone ?? '' }}</div>
        </div>

    </div>
</body>
</html>