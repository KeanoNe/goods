<?php

namespace App\Services;

use App\Models\Article;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class BestandswertService
{
    /**
     * Ermittelt den Wert des vorhandenen Lagers zum Aufrufzeitpunkt.
     *
     * Der Bestand selbst trägt keinen Lieferanten — stocks kennt nur
     * Artikel, Lagerplatz und Menge. Die Aufschlüsselung wird deshalb aus
     * den Eingangsbuchungen hergeleitet: nach FIFO verlässt die älteste
     * Ware das Lager zuerst, es bleiben also die jüngsten Zugänge liegen.
     * Was sich nicht zuordnen lässt, erscheint als abgesetzte Restzeile und
     * wird mit dem Preis des Standard-Lieferanten bewertet.
     *
     * @return array{
     *     stichtag: CarbonInterface,
     *     articles: list<array{
     *         article_id: int,
     *         sku: string,
     *         name: string,
     *         geloescht: bool,
     *         rows: list<array{supplier: string, quantity: int, unit_price: float, total: float, ohne_lieferant: bool}>,
     *         subtotal: float
     *     }>,
     *     grand_total: float,
     *     ohne_preis_artikel: int,
     *     ohne_preis_menge: int,
     *     artikel_gesamt: int
     * }
     */
    public function build(): array
    {
        $stichtag = now();

        $bestaende = DB::table('stocks')
            ->selectRaw('article_id')
            ->selectRaw('sum(quantity) as menge')
            ->groupBy('article_id')
            ->havingRaw('sum(quantity) > 0')
            ->pluck('menge', 'article_id');

        if ($bestaende->isEmpty()) {
            return [
                'stichtag' => $stichtag,
                'articles' => [],
                'grand_total' => 0.0,
                'ohne_preis_artikel' => 0,
                'ohne_preis_menge' => 0,
                'artikel_gesamt' => 0,
            ];
        }

        $artikelIds = $bestaende->keys()->all();

        $stammdaten = Article::withTrashed()
            ->whereIn('id', $artikelIds)
            ->get()
            ->keyBy('id');

        // Eingangsbuchungen mit Lieferant, jüngste zuerst. Der Join auf
        // suppliers läuft bewusst über den Query Builder, damit auch der
        // Name eines inzwischen gelöschten Lieferanten erhalten bleibt.
        $zugaenge = DB::table('stock_movements')
            ->join('suppliers', 'suppliers.id', '=', 'stock_movements.supplier_id')
            ->whereIn('stock_movements.article_id', $artikelIds)
            ->where('stock_movements.type', 'in')
            ->orderByDesc('stock_movements.created_at')
            ->orderByDesc('stock_movements.id')
            ->get([
                'stock_movements.article_id',
                'stock_movements.quantity',
                'stock_movements.unit_price',
                'suppliers.name as lieferant',
            ])
            ->groupBy('article_id');

        $standardpreise = DB::table('article_supplier')
            ->join('suppliers', 'suppliers.id', '=', 'article_supplier.supplier_id')
            ->whereIn('article_supplier.article_id', $artikelIds)
            ->where('article_supplier.is_default', true)
            ->get([
                'article_supplier.article_id',
                'article_supplier.price',
                'suppliers.name as lieferant',
            ])
            ->keyBy('article_id');

        $artikel = [];
        $gesamtsumme = 0.0;
        $ohnePreisArtikel = 0;
        $ohnePreisMenge = 0;

        foreach ($bestaende as $artikelId => $menge) {
            $restmenge = (int) $menge;
            $zugeordnet = [];

            foreach ($zugaenge->get($artikelId, collect()) as $zugang) {
                if ($restmenge <= 0) {
                    break;
                }

                $anteil = min((int) $zugang->quantity, $restmenge);
                $restmenge -= $anteil;

                $schluessel = $zugang->lieferant.'|'.(string) $zugang->unit_price;

                if (! isset($zugeordnet[$schluessel])) {
                    $zugeordnet[$schluessel] = [
                        'supplier' => $zugang->lieferant,
                        'quantity' => 0,
                        'unit_price' => (float) $zugang->unit_price,
                    ];
                }

                $zugeordnet[$schluessel]['quantity'] += $anteil;
            }

            $zeilen = [];

            foreach ($zugeordnet as $eintrag) {
                $zeilen[] = [
                    'supplier' => $eintrag['supplier'],
                    'quantity' => $eintrag['quantity'],
                    'unit_price' => $eintrag['unit_price'],
                    'total' => round($eintrag['quantity'] * $eintrag['unit_price'], 2),
                    'ohne_lieferant' => false,
                ];
            }

            usort($zeilen, fn (array $a, array $b) => [$a['supplier'], $a['unit_price']] <=> [$b['supplier'], $b['unit_price']]);

            if ($restmenge > 0) {
                $standard = $standardpreise->get($artikelId);
                $preis = $standard !== null ? (float) $standard->price : 0.0;

                $zeilen[] = [
                    'supplier' => $standard !== null
                        ? 'Ohne Lieferant (Standardpreis '.$standard->lieferant.')'
                        : 'Ohne Lieferant (kein Preis hinterlegt)',
                    'quantity' => $restmenge,
                    'unit_price' => $preis,
                    'total' => round($restmenge * $preis, 2),
                    'ohne_lieferant' => true,
                ];

                if ($standard === null) {
                    $ohnePreisArtikel++;
                    $ohnePreisMenge += $restmenge;
                }
            }

            $zwischensumme = round(array_sum(array_column($zeilen, 'total')), 2);
            $gesamtsumme += $zwischensumme;

            $modell = $stammdaten->get($artikelId);

            $artikel[] = [
                'article_id' => (int) $artikelId,
                'sku' => $modell?->sku ?? 'Unbekannt',
                'name' => $modell?->name ?? 'Unbekannter Artikel',
                'geloescht' => $modell?->trashed() ?? false,
                'rows' => $zeilen,
                'subtotal' => $zwischensumme,
            ];
        }

        usort($artikel, fn (array $a, array $b) => strcmp($a['sku'], $b['sku']));

        return [
            'stichtag' => $stichtag,
            'articles' => $artikel,
            'grand_total' => round($gesamtsumme, 2),
            'ohne_preis_artikel' => $ohnePreisArtikel,
            'ohne_preis_menge' => $ohnePreisMenge,
            'artikel_gesamt' => count($artikel),
        ];
    }
}
