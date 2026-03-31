<x-guest-layout>
    <div class="auth-brand mb-6">
        <p class="auth-eyebrow">Panel de cotizaciones</p>
        <h1 class="auth-title">Iniciar sesion</h1>
        <p class="auth-subtitle">Accede para crear, editar y compartir cotizaciones con un flujo rapido y ordenado.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="auth-form space-y-5">
        @csrf

        <div class="form-reveal">
            <label for="email" class="auth-label">Correo electrónico</label>
            <div class="auth-input-wrap mt-2">
                <span class="auth-input-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16v16H4z" />
                        <path d="m22 7-10 7L2 7" />
                    </svg>
                </span>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    class="auth-input"
                    placeholder="tu@empresa.com"
                >
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="form-reveal">
            <label for="password" class="auth-label">Contraseña</label>
            <div class="auth-input-wrap mt-2">
                <span class="auth-input-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M8 11V7a4 4 0 1 1 8 0v4" />
                        <path d="M5 11h14v10H5z" />
                        <path d="M12 15v2" />
                    </svg>
                </span>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="auth-input"
                    placeholder="Tu contraseña"
                >
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="form-reveal flex items-center justify-between gap-4 text-sm">
            <label for="remember_me" class="inline-flex items-center gap-2 text-slate-600">
                <input id="remember_me" type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                <span>Recordarme</span>
            </label>

            @if (Route::has('password.request'))
                <a class="auth-link" href="{{ route('password.request') }}">
                    Olvide mi contraseña
                </a>
            @endif
        </div>

        <button type="submit" class="auth-button form-reveal">
            Entrar
        </button>
    </form>
</x-guest-layout>
