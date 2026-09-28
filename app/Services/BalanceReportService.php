<?php

namespace App\Services;

use App\Models\Article;
use App\Models\StockMovement;
use App\Models\Supplier;
use Carbon\CarbonInterface;

class BalanceReportService
{
    /**
     * Baut die Bestandsbilanz für einen Zeitraum auf.
     *
     * Gruppiert wird nach Artikel, Lieferant und Stückpreis, damit
     * Menge × Stückpreis in jeder Zeile exakt den Gesamtwert ergibt, auch
     * wenn sich ein Preis innerhalb des Zeitraums geändert hat.
     *
     * @return array{
     *     from: CarbonInterface,
     *     to: CarbonInterface,
     *     articles: list<array{
     *         article_id: int,
     *         sku: string,
     *         name: string,
     *         rows: list<array{supplier: string, quantity: int, unit_price: float, total: float}>,
     *         subtotal: float
     *     }>,
     *     grand_total: float
     * }
     */
    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $aggregat = StockMovement::query()
            ->selectRaw('article_id, supplier_id, unit_price')
            ->selectRaw("sum(case when type = 'in' then quantity else 0 end) as qty_in")
            ->selectRaw("sum(case when type = 'out' then quantity else 0 end) as qty_out")
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereIn('type', ['in', 'out'])
            ->groupBy('article_id', 'supplier_id', 'unit_price')
            ->get();

        $artikelStammdaten = Article::withTrashed()
            ->whereIn('id', $aggregat->pluck('article_id')->unique())
            ->get()
            ->keyBy('id');

        $lieferantenNamen = Supplier::withTrashed()
            ->whereIn('id', $aggregat->pluck('supplier_id')->filter()->unique())
            ->pluck('name', 'id');

        $artikel = [];
        $gesamtsumme = 0.0;

        foreach ($aggregat->groupBy('article_id') as $articleId => $aggregatZeilen) {
            $stammdaten = $artikelStammdaten->get($articleId);
            $zeilen = [];
            $zwischensumme = 0.0;

            foreach ($aggregatZeilen as $aggregatZeile) {
                $menge = (int) $aggregatZeile->qty_in - (int) $aggregatZeile->qty_out;
                $stueckpreis = (float) $aggregatZeile->unit_price;
                $wert = round($menge * $stueckpreis, 2);
                $zwischensumme += $wert;

                $zeilen[] = [
                    'supplier' => $aggregatZeile->supplier_id === null
                        ? 'Ohne Lieferant'
                        : ($lieferantenNamen[$aggregatZeile->supplier_id] ?? 'Unbekannter Lieferant'),
                    'quantity' => $menge,
                    'unit_price' => $stueckpreis,
                    'total' => $wert,
                ];
            }

            usort($zeilen, fn ($a, $b) => [$a['supplier'], $a['unit_price']] <=> [$b['supplier'], $b['unit_price']]);

            $zwischensumme = round($zwischensumme, 2);
            $gesamtsumme += $zwischensumme;

            $artikel[] = [
                'article_id' => (int) $articleId,
                'sku' => $stammdaten?->sku ?? 'Unbekannt',
                'name' => $stammdaten?->name ?? 'Unbekannter Artikel',
                'rows' => $zeilen,
                'subtotal' => $zwischensumme,
            ];
        }

        usort($artikel, fn ($a, $b) => strcmp($a['sku'], $b['sku']));

        return [
            'from' => $from,
            'to' => $to,
            'articles' => $artikel,
            'grand_total' => round($gesamtsumme, 2),
        ];
    }
}
