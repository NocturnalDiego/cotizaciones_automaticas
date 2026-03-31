<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center px-4 py-2 bg-white/90 border border-slate-300 rounded-lg font-semibold text-sm text-slate-700 tracking-wide shadow-sm hover:bg-sky-50 hover:border-sky-200 hover:text-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 disabled:opacity-40 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
