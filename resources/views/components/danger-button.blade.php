<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-lg font-semibold text-sm text-white tracking-wide bg-gradient-to-r from-rose-600 to-red-600 shadow-md shadow-red-900/20 hover:from-rose-500 hover:to-red-500 active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
