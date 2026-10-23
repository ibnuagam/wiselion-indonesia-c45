@props(['name'])
<svg {{ $attributes->merge(['width' => 20, 'height' => 20, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => 1.6, 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>
    @switch($name)
        @case('arrow')<path d="M4 12h15m-6-6 6 6-6 6"/>@break
        @case('eye')<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>@break
        @case('mail')<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/>@break
        @case('lock')<rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/>@break
        @case('grid')<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>@break
        @case('box')<path d="m12 3 9 5v8l-9 5-9-5V8l9-5Zm0 10 9-5M3 8l9 5v8M7 5.8l9 5"/>@break
        @case('chart')<path d="M4 3v17h17M8 15V9m5 6V5m5 10v-4"/>@break
        @case('logout')<path d="M10 4H5v16h5m5-12 4 4-4 4m-6-4h10"/>@break
        @case('search')<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>@break
        @case('check')<path d="m5 12 4 4L19 6"/>@break
    @endswitch
</svg>
