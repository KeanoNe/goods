<?php

namespace App\Http\Controllers;

use App\Services\BalanceReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

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

    public function export(Request $request)
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'format' => 'required|in:pdf,xlsx',
        ]);

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();

        $bericht = $this->balanceReport->build($from, $to);

        $dateiname = sprintf(
            'bilanz_%s_bis_%s',
            $from->format('Y-m-d'),
            $to->format('Y-m-d')
        );

        return $validated['format'] === 'pdf'
            ? $this->alsPdf($bericht, $dateiname)
            : $this->alsXlsx($bericht, $dateiname);
    }

    /**
     * @param  array<string, mixed>  $bericht
     */
    private function alsPdf(array $bericht, string $dateiname)
    {
        return Pdf::loadView('reports.balance', $bericht)
            ->setPaper('a4')
            ->download($dateiname.'.pdf');
    }

    /**
     * @param  array<string, mixed>  $bericht
     */
    private function alsXlsx(array $bericht, string $dateiname)
    {
        return response()->streamDownload(function () use ($bericht) {
            $fett = (new Style)->withFontBold(true);

            $writer = new Writer;
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValuesWithStyle(['Bestandsbilanz'], $fett));
            $writer->addRow(Row::fromValues([sprintf(
                'Zeitraum: %s - %s',
                $bericht['from']->format('d.m.Y'),
                $bericht['to']->format('d.m.Y')
            )]));
            $writer->addRow(Row::fromValues(['Erstellt am: '.now()->format('d.m.Y')]));
            $writer->addRow(Row::fromValues([]));

            $writer->addRow(Row::fromValuesWithStyle(
                ['Artikelnummer', 'Bezeichnung', 'Lieferant', 'Menge', 'Stückpreis', 'Gesamtwert'],
                $fett
            ));

            foreach ($bericht['articles'] as $artikel) {
                foreach ($artikel['rows'] as $index => $zeile) {
                    $writer->addRow(Row::fromValues([
                        $index === 0 ? $artikel['sku'] : '',
                        $index === 0 ? $artikel['name'] : '',
                        $zeile['supplier'],
                        $zeile['quantity'],
                        $zeile['unit_price'],
                        $zeile['total'],
                    ]));
                }

                $writer->addRow(Row::fromValuesWithStyle(
                    ['', 'Zwischensumme '.$artikel['name'], '', '', '', $artikel['subtotal']],
                    $fett
                ));
            }

            $writer->addRow(Row::fromValuesWithStyle(
                ['', 'Gesamtsumme', '', '', '', $bericht['grand_total']],
                $fett
            ));

            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['_______________________', '', '_______________________']));
            $writer->addRow(Row::fromValues(['Ort, Datum', '', 'Unterschrift']));

            $writer->close();
        }, $dateiname.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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
