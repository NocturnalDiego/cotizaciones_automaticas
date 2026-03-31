<nav x-data="{ open: false }" class="app-top-nav">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="app-brand-link">
                        <x-application-logo class="block h-8 w-auto fill-current text-sky-700" />
                    </a>
                    <span class="hidden sm:inline-block ms-3 text-sm font-semibold tracking-wide text-slate-700">
                        Cotizaciones Automáticas
                    </span>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-5 sm:-my-px sm:ms-8 sm:flex sm:items-center">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Panel') }}
                    </x-nav-link>
                    @can('cotizaciones.ver')
                        <x-nav-link :href="route('cotizaciones.index')" :active="request()->routeIs('cotizaciones.*')">
                            {{ __('Cotizaciones') }}
                        </x-nav-link>
                    @endcan
                    @can('marca.gestionar')
                        <x-nav-link :href="route('branding.edit')" :active="request()->routeIs('branding.*')">
                            {{ __('Marca') }}
                        </x-nav-link>
                    @endcan
                    @can('usuarios.gestionar')
                        <x-nav-link :href="route('usuarios.index')" :active="request()->routeIs('usuarios.*')">
                            {{ __('Usuarios') }}
                        </x-nav-link>
                    @endcan
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <details class="relative app-user-menu">
                    <summary class="app-user-trigger list-none cursor-pointer [&::-webkit-details-marker]:hidden">
                        <span>{{ Auth::user()->name }}</span>
                        <span class="ms-1">
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </summary>

                    <div class="app-user-menu-panel absolute right-0 z-50 mt-2 w-56">
                        <a href="{{ route('profile.edit') }}" class="app-user-menu-item">
                            Perfil
                        </a>
                        @can('marca.gestionar')
                            <a href="{{ route('branding.edit') }}" class="app-user-menu-item">
                                Identidad de marca
                            </a>
                        @endcan
                        @can('usuarios.gestionar')
                            <a href="{{ route('usuarios.index') }}" class="app-user-menu-item">
                                Gestión de usuarios
                            </a>
                        @endcan

                        <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-200/90">
                            @csrf
                            <button type="submit" class="app-user-menu-item w-full text-left border-0 bg-transparent">
                                Cerrar sesión
                            </button>
                        </form>
                    </div>
                </details>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-slate-400 hover:text-sky-700 hover:bg-sky-50 focus:outline-none focus:bg-sky-50 focus:text-sky-700 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden bg-white/95 border-t border-slate-200">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Panel') }}
            </x-responsive-nav-link>
            @can('cotizaciones.ver')
                <x-responsive-nav-link :href="route('cotizaciones.index')" :active="request()->routeIs('cotizaciones.*')">
                    {{ __('Cotizaciones') }}
                </x-responsive-nav-link>
            @endcan
            @can('marca.gestionar')
                <x-responsive-nav-link :href="route('branding.edit')" :active="request()->routeIs('branding.*')">
                    {{ __('Marca') }}
                </x-responsive-nav-link>
            @endcan
            @can('usuarios.gestionar')
                <x-responsive-nav-link :href="route('usuarios.index')" :active="request()->routeIs('usuarios.*')">
                    {{ __('Usuarios') }}
                </x-responsive-nav-link>
            @endcan
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-slate-200">
            <div class="px-4">
                <div class="font-semibold text-base text-slate-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-slate-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Perfil') }}
                </x-responsive-nav-link>

                @can('marca.gestionar')
                    <x-responsive-nav-link :href="route('branding.edit')">
                        {{ __('Identidad de marca') }}
                    </x-responsive-nav-link>
                @endcan

                @can('usuarios.gestionar')
                    <x-responsive-nav-link :href="route('usuarios.index')">
                        {{ __('Gestión de usuarios') }}
                    </x-responsive-nav-link>
                @endcan

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Cerrar sesión') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
