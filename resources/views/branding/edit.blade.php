<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-2xl text-slate-800 leading-tight tracking-tight">
            Identidad de marca
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="app-surface p-4 sm:p-8">
                <div class="max-w-2xl">
                    @include('branding.partials.form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
