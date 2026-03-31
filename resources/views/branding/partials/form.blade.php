<section x-data="{ fileName: '' }">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            Configuración general de marca
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Gestiona aquí los datos del emisor, el nombre de marca visible en cotización y el logotipo global de la aplicación.
        </p>
    </header>

    <form method="post" action="{{ route('branding.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="quote_brand_name" value="Nombre de marca para cotización" />
            <x-text-input id="quote_brand_name" name="quote_brand_name" type="text" class="mt-1 block w-full" :value="old('quote_brand_name', $appBranding->quote_brand_name)" />
            <x-input-error :messages="$errors->brandingSettings->get('quote_brand_name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="issuer_name" value="Nombre del emisor" />
            <x-text-input id="issuer_name" name="issuer_name" type="text" class="mt-1 block w-full" :value="old('issuer_name', $appBranding->issuer_name)" />
            <x-input-error :messages="$errors->brandingSettings->get('issuer_name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="issuer_rfc" value="RFC del emisor" />
            <x-text-input id="issuer_rfc" name="issuer_rfc" type="text" class="mt-1 block w-full" :value="old('issuer_rfc', $appBranding->issuer_rfc)" />
            <x-input-error :messages="$errors->brandingSettings->get('issuer_rfc')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="issuer_business_name" value="Razón social o nombre comercial" />
            <x-text-input id="issuer_business_name" name="issuer_business_name" type="text" class="mt-1 block w-full" :value="old('issuer_business_name', $appBranding->issuer_business_name)" />
            <x-input-error :messages="$errors->brandingSettings->get('issuer_business_name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="brand_logo" value="Logotipo global" />

            <div class="mt-2 flex flex-wrap items-center gap-3">
                <label for="brand_logo" class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-800">
                    <x-tabler-icon name="file-invoice" class="h-4 w-4" />
                    Seleccionar archivo
                </label>

                <span class="text-sm text-slate-500" x-text="fileName || 'Ningún archivo seleccionado'"></span>
            </div>

            <input
                id="brand_logo"
                name="brand_logo"
                type="file"
                accept="image/*"
                class="sr-only"
                @change="fileName = $event.target.files.length ? $event.target.files[0].name : ''"
            />

            <x-input-error :messages="$errors->brandingSettings->get('brand_logo')" class="mt-2" />

            @if ($appBranding->logo_url)
                <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Vista previa actual</p>
                    <img src="{{ $appBranding->logo_url }}" alt="Logotipo actual" class="mt-2 h-16 w-auto object-contain">

                    <label for="remove_logo" class="mt-3 inline-flex items-center gap-2 text-sm text-slate-700">
                        <input id="remove_logo" type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        Quitar logotipo actual
                    </label>
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Guardar identidad</x-primary-button>

            @if (session('status') === 'branding-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >Guardado.</p>
            @endif
        </div>
    </form>
</section>
