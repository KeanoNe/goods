<?php

namespace App\Http\Controllers;

use App\Services\BalanceReportService;
use App\Services\BestandswertService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class BalanceReportController extends Controller
{
    public function __construct(
        private BalanceReportService $balanceReport,
        private BestandswertService $bestandswert,
    ) {}

    public function index(Request $request)
    {
        [$from, $to] = $this->zeitraum($request);

        $bericht = $this->balanceReport->build($from, $to);
        $wert = $this->bestandswert->build();

        return Inertia::render('Reports/Balance', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'articles' => $bericht['articles'],
            'grandTotal' => $bericht['grand_total'],
            'bestandswert' => [
                'stichtag' => $wert['stichtag']->toDateString(),
                'articles' => $wert['articles'],
                'grandTotal' => $wert['grand_total'],
                'ohnePreisArtikel' => $wert['ohne_preis_artikel'],
                'ohnePreisMenge' => $wert['ohne_preis_menge'],
                'artikelGesamt' => $wert['artikel_gesamt'],
            ],
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
        $bericht['bestandswert'] = $this->bestandswert->build();

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
                'Zeitraum: %s – %s',
                $bericht['from']->format('d.m.Y'),
                $bericht['to']->format('d.m.Y')
            )]));
            $writer->addRow(Row::fromValues(['Erstellt am: '.now()->format('d.m.Y')]));
            $writer->addRow(Row::fromValues([]));

            $writer->addRow(Row::fromValuesWithStyle(
                ['Artikelnummer', 'Bezeichnung', 'Lieferant', 'Menge', 'Stückpreis', 'Gesamtwert'],
                $fett
            ));

            if ($bericht['articles'] === []) {
                $writer->addRow(Row::fromValues(['Im gewählten Zeitraum gibt es keine Bestandsbewegungen']));
            }

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

            $wert = $bericht['bestandswert'];

            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValuesWithStyle(
                ['Bestandswert zum '.$wert['stichtag']->format('d.m.Y')],
                $fett
            ));
            $writer->addRow(Row::fromValues(['Momentaufnahme des Lagers, zeitraumunabhängig.']));
            $writer->addRow(Row::fromValues([]));

            $writer->addRow(Row::fromValuesWithStyle(
                ['Artikelnummer', 'Bezeichnung', 'Lieferant', 'Menge', 'Stückpreis', 'Wert'],
                $fett
            ));

            if ($wert['articles'] === []) {
                $writer->addRow(Row::fromValues(['Derzeit liegt kein Bestand im Lager']));
            }

            foreach ($wert['articles'] as $artikel) {
                foreach ($artikel['rows'] as $index => $zeile) {
                    $writer->addRow(Row::fromValues([
                        $index === 0 ? $artikel['sku'] : '',
                        $index === 0
                            ? $artikel['name'].($artikel['geloescht'] ? ' (gelöscht)' : '')
                            : '',
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
                ['', 'Gesamtwert des Lagers', '', '', '', $wert['grand_total']],
                $fett
            ));

            if ($wert['ohne_preis_artikel'] > 0) {
                $writer->addRow(Row::fromValues(['', sprintf(
                    'Für %d von %d Artikeln (%s Stück) ist kein Preis hinterlegt; der Gesamtwert ist insoweit unvollständig.',
                    $wert['ohne_preis_artikel'],
                    $wert['artikel_gesamt'],
                    number_format($wert['ohne_preis_menge'], 0, ',', '.')
                )]));
            }

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
