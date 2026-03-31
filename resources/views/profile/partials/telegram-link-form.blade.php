<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            Vinculación con Telegram
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Genera un código temporal y envíalo al bot con el comando /vincular CODIGO para autorizar este chat.
        </p>
    </header>

    @if (session('telegram_link_code'))
        <div class="mt-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
            <p class="font-semibold">Código temporal: {{ session('telegram_link_code') }}</p>
            <p class="mt-1">Válido hasta las {{ session('telegram_link_code_expires_at') }}.</p>
        </div>
    @endif

    <div class="mt-4 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
        @if ($user->telegram_chat_id)
            <p class="font-semibold text-emerald-700">Tu cuenta ya está vinculada a un chat de Telegram.</p>
            <p class="mt-1 text-slate-600">ID de chat vinculado: {{ $user->telegram_chat_id }}</p>
            <p class="mt-1 text-slate-600">Si deseas cambiar de chat, genera un nuevo código y vincula desde el nuevo chat.</p>
        @else
            <p class="font-semibold text-amber-700">Tu cuenta aún no está vinculada a Telegram.</p>
            <p class="mt-1 text-slate-600">Sin vinculación, ese chat no podrá ejecutar acciones en el bot.</p>
        @endif
    </div>

    <form method="post" action="{{ route('profile.telegram.generate-code') }}" class="mt-6">
        @csrf

        <x-primary-button>
            Generar código de vinculación
        </x-primary-button>

        @if (session('status') === 'telegram-link-code-generated')
            <p
                x-data="{ show: true }"
                x-show="show"
                x-transition
                x-init="setTimeout(() => show = false, 2500)"
                class="mt-3 text-sm text-gray-600"
            >Código generado correctamente.</p>
        @endif
    </form>
</section>
