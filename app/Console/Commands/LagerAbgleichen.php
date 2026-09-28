<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LagerAbgleichen extends Command
{
    protected $signature = 'lager:abgleichen {--schreiben : Korrekturbuchungen tatsächlich schreiben}';

    protected $description = 'Vergleicht Bewegungsjournal und Bestand und gleicht Abweichungen per Korrekturbuchung an';

    private const NOTIZ = 'Abgleich Journal und Bestand, Ursache: gelöschte Lagerplatz-Zuordnungen ohne Buchung';

    private const URHEBER_ID = 1;

    public function handle(): int
    {
        $journal = StockMovement::journalstaende();
        $bestand = $this->bestaende();

        $zuBuchen = [];
        $nichtAbgleichbar = [];

        foreach ($journal->keys()->merge($bestand->keys())->unique() as $artikelId) {
            $differenz = (int) ($bestand[$artikelId] ?? 0) - (int) ($journal[$artikelId] ?? 0);

            if ($differenz === 0) {
                continue;
            }

            $lagerplatzId = $this->lagerplatzFuer((int) $artikelId);

            if ($lagerplatzId === null) {
                $nichtAbgleichbar[] = (int) $artikelId;

                continue;
            }

            $zuBuchen[] = [
                'article_id' => (int) $artikelId,
                'bestand' => (int) ($bestand[$artikelId] ?? 0),
                'journal' => (int) ($journal[$artikelId] ?? 0),
                'differenz' => $differenz,
                'lagerplatz_id' => $lagerplatzId,
            ];
        }

        $this->bericht($zuBuchen, $nichtAbgleichbar);

        if (! $this->option('schreiben')) {
            $this->newLine();
            $this->info('Probelauf, es wurde nichts geschrieben. Mit --schreiben ausführen, um die Buchungen anzulegen.');

            return self::SUCCESS;
        }

        if (! User::whereKey(self::URHEBER_ID)->exists()) {
            $this->error(sprintf(
                'Urheber-Benutzer mit ID %d existiert nicht. Es wurde nichts geschrieben.',
                self::URHEBER_ID
            ));

            return self::FAILURE;
        }

        foreach ($zuBuchen as $eintrag) {
            DB::transaction(function () use ($eintrag) {
                StockMovement::create([
                    'article_id' => $eintrag['article_id'],
                    'from_storage_location_id' => $eintrag['differenz'] < 0 ? $eintrag['lagerplatz_id'] : null,
                    'to_storage_location_id' => $eintrag['differenz'] > 0 ? $eintrag['lagerplatz_id'] : null,
                    'quantity' => abs($eintrag['differenz']),
                    'type' => 'correction',
                    'user_id' => self::URHEBER_ID,
                    'notes' => self::NOTIZ,
                ]);
            });
        }

        $this->newLine();
        $this->info(count($zuBuchen).' Korrekturbuchungen geschrieben. Die Bestände wurden nicht verändert.');

        return self::SUCCESS;
    }

    /**
     * Tatsächlicher Bestand je Artikel laut stocks.
     *
     * @return Collection<int, int>
     */
    private function bestaende(): Collection
    {
        return DB::table('stocks')
            ->selectRaw('article_id')
            ->selectRaw('sum(quantity) as menge')
            ->groupBy('article_id')
            ->pluck('menge', 'article_id');
    }

    /**
     * Lagerplatz für die Korrekturbuchung: aus der jüngsten Bewegung des
     * Artikels, die einen Lagerplatz trägt, ersatzweise aus einem
     * Bestandseintrag.
     *
     * Historische Korrekturbuchungen (vor diesem Branch) konnten mit
     * Menge 0 und beiden Lagerplatz-Spalten null entstehen. Solche
     * Bewegungen werden übersprungen, damit ältere, brauchbare
     * Bewegungen nicht durch sie verdeckt werden.
     */
    private function lagerplatzFuer(int $artikelId): ?int
    {
        $juengste = DB::table('stock_movements')
            ->where('article_id', $artikelId)
            ->where(fn ($query) => $query->whereNotNull('to_storage_location_id')
                ->orWhereNotNull('from_storage_location_id'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['from_storage_location_id', 'to_storage_location_id']);

        if ($juengste !== null) {
            $id = $juengste->to_storage_location_id ?? $juengste->from_storage_location_id;

            if ($id !== null) {
                return (int) $id;
            }
        }

        $ausBestand = DB::table('stocks')
            ->where('article_id', $artikelId)
            ->value('storage_location_id');

        return $ausBestand !== null ? (int) $ausBestand : null;
    }

    /**
     * @param  list<array{article_id: int, bestand: int, journal: int, differenz: int, lagerplatz_id: int}>  $zuBuchen
     * @param  list<int>  $nichtAbgleichbar
     */
    private function bericht(array $zuBuchen, array $nichtAbgleichbar): void
    {
        if ($zuBuchen === []) {
            $this->info('Journal und Bestand stimmen bei allen Artikeln überein.');
        } else {
            $this->table(
                ['Artikel', 'Bezeichnung', 'Bestand', 'Journal', 'Differenz'],
                array_map(function (array $eintrag): array {
                    $artikel = Article::withTrashed()->find($eintrag['article_id']);

                    return [
                        $eintrag['article_id'],
                        $artikel?->name ?? 'unbekannt',
                        $eintrag['bestand'],
                        $eintrag['journal'],
                        sprintf('%+d', $eintrag['differenz']),
                    ];
                }, $zuBuchen)
            );

            $this->line(sprintf(
                'Betroffene Artikel: %d, Summe der Beträge: %d Stück',
                count($zuBuchen),
                array_sum(array_map(fn (array $e): int => abs($e['differenz']), $zuBuchen))
            ));
        }

        if ($nichtAbgleichbar !== []) {
            $this->warn(sprintf(
                'Nicht abgleichbar, weil kein Lagerplatz ermittelbar ist: %s',
                implode(', ', $nichtAbgleichbar)
            ));
        }
    }
}
