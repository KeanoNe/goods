<?php

namespace App\Http\Controllers;

use App\Services\BalanceReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BalanceReportController extends Controller
{
    public function __construct(private BalanceReportService $balanceReport) {}

    public function index(Request $request)
    {
        [$from, $to] = $this->zeitraum($request);

        $bericht = $this->balanceReport->build($from, $to);

        return Inertia::render('Reports/Balance', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'articles' => $bericht['articles'],
            'grandTotal' => $bericht['grand_total'],
        ]);
    }

    /**
     * Ermittelt den auszuwertenden Zeitraum. Ohne Angabe gilt der laufende
     * Monat vom Monatsersten bis heute.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function zeitraum(Request $request): array
    {
        $validated = $request->validate([
            'from' => 'nullable|date|required_with:to',
            'to' => 'nullable|date|required_with:from|after_or_equal:from',
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])
            : Carbon::now()->startOfMonth();

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])
            : Carbon::now();

        return [$from->startOfDay(), $to->endOfDay()];
    }
}
