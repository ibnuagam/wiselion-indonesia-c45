<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalysisRequest;
use App\Models\Analysis;
use App\Models\Sale;
use App\Services\AnalysisService;
use App\Services\C45Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalysisController extends Controller
{
    public function index(): View
    {
        $readiness = Sale::selectRaw('data_origin, COUNT(*) as total, SUM(CASE WHEN reference_label IS NOT NULL THEN 1 ELSE 0 END) as labelled')
            ->groupBy('data_origin')->get()->keyBy('data_origin');

        return view('analysis.index', ['analyses' => Analysis::where('status', 'completed')->whereNotNull('algorithm_version')->orderByDesc('id')->paginate(10), 'readiness' => $readiness]);
    }

    public function store(AnalysisRequest $request, AnalysisService $service): RedirectResponse
    {
        $analysis = $service->run($request->safe()->except('confirmed'), $request->user());

        return to_route('analyses.show', $analysis)->with('success', 'Analisis tersimpan. Data, pohon, aturan, dan evaluasi dibekukan pada snapshot ini.');
    }

    public function show(Analysis $analysis): View
    {
        abort_unless($analysis->status === 'completed' && $analysis->algorithm_version, 404);

        return view('analysis.show', ['analysis' => $analysis, 'labels' => C45Service::LABELS, 'features' => C45Service::FEATURE_NAMES,
            'testRows' => array_values(array_filter($analysis->snapshot, fn (array $row): bool => $row['partition'] === 'testing'))]);
    }

    public function download(Analysis $analysis): StreamedResponse
    {
        abort_unless($analysis->status === 'completed' && $analysis->algorithm_version, 404);

        return response()->streamDownload(function () use ($analysis): void {
            echo json_encode($analysis->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }, 'wiselion-analysis-'.$analysis->id.'.json', ['Content-Type' => 'application/json', 'Cache-Control' => 'private, no-store']);
    }
}
