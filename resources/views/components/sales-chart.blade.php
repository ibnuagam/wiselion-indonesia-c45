@props(['rows', 'metric' => 'revenue'])
@php
    $values = $rows->pluck($metric)->all();
    $maximum = max([1, ...$values]);
    $count = count($values);
    $coords = [];
    foreach ($values as $i => $value) {
        $x = $count === 1 ? 310 : 48 + $i * 530 / max(1, $count - 1);
        $y = 176 - $value / $maximum * 135;
        $coords[] = [$x, $y];
    }
    $polyline = implode(' ', array_map(fn ($point) => implode(',', $point), $coords));
@endphp
@if($count)
<svg class="sales-chart" viewBox="0 0 620 220" role="img" aria-label="{{ $metric === 'revenue' ? 'Grafik omset per bulan tercatat' : 'Grafik unit terjual per bulan tercatat' }}">
    @foreach([0, .5, 1] as $fraction)
    <line x1="48" x2="588" y1="{{ 176 - $fraction * 135 }}" y2="{{ 176 - $fraction * 135 }}" class="chart-grid" />
    <text x="39" y="{{ 180 - $fraction * 135 }}" text-anchor="end" class="chart-label">{{ $metric === 'revenue' ? number_format($maximum * $fraction / 1000000, 1, ',', '.').'jt' : number_format($maximum * $fraction, 0, ',', '.') }}</text>
    @endforeach
    @if($count > 1)<polygon points="48,176 {{ $polyline }} 578,176" class="chart-area" /><polyline points="{{ $polyline }}" class="chart-line" />@endif
    @foreach($coords as $i => $point)<circle cx="{{ $point[0] }}" cy="{{ $point[1] }}" r="4" class="chart-point"><title>{{ $rows[$i]['label'] }}: {{ $metric === 'revenue' ? 'Rp ' : '' }}{{ number_format($values[$i], 0, ',', '.') }}</title></circle><text x="{{ $point[0] }}" y="202" text-anchor="middle" class="chart-label">{{ $rows[$i]['label'] }}</text>@endforeach
</svg>
@else<p class="empty-state">Belum ada penjualan pada periode terpilih.</p>@endif
