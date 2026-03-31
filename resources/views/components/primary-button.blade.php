<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-lg font-semibold text-sm text-white tracking-wide bg-gradient-to-r from-sky-600 via-blue-600 to-blue-700 shadow-md shadow-blue-900/20 hover:from-sky-500 hover:via-blue-500 hover:to-blue-600 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 active:scale-[0.99] transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
