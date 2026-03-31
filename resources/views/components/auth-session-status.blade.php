@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 font-medium text-sm text-sky-700']) }}>
        {{ $status }}
    </div>
@endif
