<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalesImportRequest;
use App\Models\SalesImport;
use App\Services\SalesImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesImportController extends Controller
{
    public function index(): View
    {
        return view('management.imports.index', ['imports' => SalesImport::orderByDesc('id')->paginate(10)]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, SalesImportService::HEADERS, ',', '"', '');
            fclose($output);
        }, 'template-penjualan-wiselion.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(SalesImportRequest $request, SalesImportService $service): RedirectResponse
    {
        $batch = $service->preview($request->file('file'), $request->validated('data_origin'), $request->user());

        return to_route('sales-imports.show', $batch);
    }

    public function show(SalesImport $salesImport): View
    {
        return view('management.imports.show', ['batch' => $salesImport]);
    }

    public function commit(Request $request, SalesImport $salesImport, SalesImportService $service): RedirectResponse
    {
        $request->validate(['confirmed' => ['accepted']]);
        try {
            $service->commit($salesImport, $request->user());
        } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['import' => 'Data produk/bulan baru saja ditambahkan melalui proses lain. Unggah ulang CSV untuk memeriksa konflik.']);
        }

        return to_route('sales-imports.show', $salesImport)->with('success', 'Impor selesai. Pengiriman ulang pratinjau yang sama tidak menggandakan data.');
    }
}
