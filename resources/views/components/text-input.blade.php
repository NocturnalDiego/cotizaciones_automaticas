@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full border-slate-300 bg-white/90 text-slate-900 placeholder:text-slate-400 rounded-lg shadow-sm focus:border-sky-500 focus:ring-sky-500']) }}>
