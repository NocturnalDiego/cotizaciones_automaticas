@props([
    'name',
    'size' => 20,
    'stroke' => 1.8,
])

<svg
    xmlns="http://www.w3.org/2000/svg"
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="{{ $stroke }}"
    stroke-linecap="round"
    stroke-linejoin="round"
    {{ $attributes }}
>
    <path stroke="none" d="M0 0h24v24H0z" fill="none" />

    @switch($name)
        @case('plus')
            <path d="M12 5l0 14" />
            <path d="M5 12l14 0" />
            @break

        @case('list-details')
            <path d="M13 5h8" />
            <path d="M13 9h5" />
            <path d="M13 15h8" />
            <path d="M13 19h5" />
            <path d="M3 5h1v1h-1z" />
            <path d="M3 15h1v1h-1z" />
            @break

        @case('file-invoice')
            <path d="M14 3v4a1 1 0 0 0 1 1h4" />
            <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
            <path d="M9 7l1 0" />
            <path d="M9 13l6 0" />
            <path d="M13 17l2 0" />
            @break

        @case('alert-circle')
            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
            <path d="M12 8v4" />
            <path d="M12 16h.01" />
            @break

        @case('cash')
            <path d="M7 9m0 1a1 1 0 0 1 1 -1h8a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-8a1 1 0 0 1 -1 -1z" />
            <path d="M10 12h4" />
            <path d="M3 10v4a2 2 0 0 0 2 2h12" />
            <path d="M3 14a2 2 0 0 1 2 -2h12" />
            @break

        @case('chart-donut-3')
            <path d="M14 3.13a9 9 0 1 0 6.87 6.87" />
            <path d="M15 9h6a9 9 0 0 0 -6 -6v6" />
            <path d="M9 15h.01" />
            @break

        @case('clock-hour-4')
            <path d="M12 7v5l3 3" />
            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
            @break

        @case('receipt-2')
            <path d="M9 18l6 0" />
            <path d="M9 14l6 0" />
            <path d="M9 10l6 0" />
            <path d="M5 3m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v14l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2z" />
            @break

        @case('arrow-right')
            <path d="M5 12l14 0" />
            <path d="M13 18l6 -6" />
            <path d="M13 6l6 6" />
            @break

        @default
            <path d="M12 9v2m0 4v.01" />
            <path d="M5.07 19h13.86a2 2 0 0 0 1.74 -3l-6.93 -12a2 2 0 0 0 -3.48 0l-6.93 12a2 2 0 0 0 1.74 3" />
    @endswitch
</svg>
