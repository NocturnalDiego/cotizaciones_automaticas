
# Cotizaciones Automáticas

Aplicación Laravel 12 para generar, gestionar y enviar cotizaciones profesionales. Integra interacción vía Telegram y entrada por lenguaje natural (IA), generación de PDFs, control de pagos y gestión de roles/permiso con Spatie.

## Índice

- [Características](#caracter%C3%ADsticas)
- [Requisitos](#requisitos)
- [Instalación rápida](#instalación-rápida)
- [Comandos útiles](#comandos-útiles)
- [Desarrollo](#desarrollo)
- [Telegram (bot)](#telegram-bot)
- [Variables de entorno principales](#variables-de-entorno-principales)
- [Arquitectura y convenciones](#arquitectura-y-convenciones)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Pruebas](#pruebas)
- [Contribuir](#contribuir)
- [Créditos y licencia](#créditos-y-licencia)

## Características

- Creación y edición de cotizaciones (múltiples ítems por cotización).
- Integración con Telegram para entrada guiada y libre (IA).
- Soporte para generación automática de PDF.
- Registro de anticipos y pagos.
- Búsqueda, filtrado y listados con vistas responsivas.
- Roles y permisos con `spatie/laravel-permission`.
- Frontend con Blade + Tailwind + Vite.

## Requisitos

- PHP ^8.2
- Composer
- Node.js 18+
- MySQL/MariaDB (localmente se puede usar SQLite para tests)
- (Opcional) Ollama para IA local
- Cuenta de Telegram y token del bot

## Instalación rápida

1. Clona el repositorio:

```bash
git clone https://tu-repo.git cotizaciones_automaticas
cd cotizaciones_automaticas
```

2. Preparar el entorno (recomendado en una máquina nueva):

```bash
composer run setup
```

3. Alternativa manual (si prefieres paso a paso):

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

4. Inicia el entorno de desarrollo:

```bash
composer run dev
```

> `composer run dev` arranca el servidor, el listener de colas, los logs y Vite en modo desarrollo (usa `concurrently`).

## Comandos útiles

- `composer run setup` — instala dependencias y prepara la base (migrations, assets).
- `composer run dev` — modo desarrollo (server + queues + vite + procesos auxiliares).
- `npm run dev` — ejecuta Vite en modo desarrollo.
- `npm run build` — compila assets para producción.
- `composer run test` — ejecuta la suite de pruebas (Pest / PHPUnit).
- `php artisan migrate` — aplica migraciones.
- `php artisan telegram:escuchar-cotizaciones` — comando de escucha del bot (usar según despliegue/local).

## Desarrollo

- Seguir las convenciones de Blade y componentes; mantener controladores delgados y validaciones en Form Requests.
- Escribir pruebas con Pest y colocarlas en `tests/Feature` o `tests/Unit`.
- Mantener la UI en español impecable (acentos, ñ) y usar Tabler Icons para iconografía.

## Telegram (bot)

- Configura `TELEGRAM_BOT_TOKEN` en `.env`.
- Inicia el listener del bot en producción o en un worker supervisado; en desarrollo puedes usar `composer run dev` o ejecutar el comando de escucha manualmente.

## Variables de entorno principales

- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `TELEGRAM_BOT_TOKEN` — token del bot de Telegram.
- `OLLAMA_*` — configuración para IA local (host/puerto/credenciales según tu instalación).

## Arquitectura y convenciones

- Basada en Laravel 12 con Breeze para autenticación.
- Autorización con Spatie Permission (`assignRole`, `hasRole`, `can`).
- Frontend con Blade + Tailwind + Vite; seguir la estructura `resources/views/{module}/`.
- Usar componentes Blade (`x-*`) y partials para formularios reutilizables.
- Iconos: usar Tabler Icons (ver `.github/copilot-instructions.md`).

Consulta las pautas del proyecto en [.github/copilot-instructions.md](.github/copilot-instructions.md).

## Estructura del proyecto

- `app/` — lógica del dominio, modelos y servicios.
- `app/Http/Controllers` — controladores.
- `app/Services` — servicios de aplicación (AI, quotes, telegram, etc.).
- `resources/views/` — vistas Blade.
- `routes/` — rutas web y auth.
- `tests/` — pruebas (Pest).

## Pruebas

```bash
composer run test
```

Las pruebas se ejecutan en SQLite en memoria según `phpunit.xml`.

## Contribuir

- Abre un issue o PR para cambios funcionales.
- Añade pruebas para nueva funcionalidad y sigue las convenciones de codificación.
- No uses emojis en UI por favor; escribe todo en un buen español.

## Créditos y licencia

- Mantenido por NocturnalDiego.
- Basado en Laravel, Breeze, Spatie Permission y Tabler Icons.

Licencia: MIT
