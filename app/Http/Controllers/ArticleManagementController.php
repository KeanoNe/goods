<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Stock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class ArticleManagementController extends Controller
{
    public function index()
    {
        return Inertia::render('Articles/Index', [
            'articles' => Article::query()
                ->withSum('stocks', 'quantity')
                ->withCount('stocks')
                ->get()
        ]);
    }

    public function create()
    {
        return Inertia::render('Articles/UpsertArticle');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sku' => 'required|string|max:255|unique:articles',
            'minimum_stock' => 'required|integer|min:0',
            'barcode' => 'nullable|string|unique:articles',
            'qr_code' => 'nullable|string|unique:articles',
            'notes' => 'nullable|string'
        ]);

        Article::create($validated);

        return redirect()->route('articles.index')
            ->with('message', 'Artikel erfolgreich erstellt');
    }

    public function edit(Article $article)
    {
        return Inertia::render('Articles/UpsertArticle', [
            'article' => $article->load(['stocks.storageLocation.shelf.rack.warehouse'])
        ]);
    }

    public function update(Request $request, Article $article)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sku' => 'required|string|max:255|unique:articles,sku,' . $article->id,
            'minimum_stock' => 'required|integer|min:0',
            'barcode' => 'nullable|string|unique:articles,barcode,' . $article->id,
            'qr_code' => 'nullable|string|unique:articles,qr_code,' . $article->id,
            'notes' => 'nullable|string'
        ]);

        $article->update($validated);

        return redirect()->route('articles.index')
            ->with('message', 'Artikel erfolgreich aktualisiert');
    }

    public function show(Article $article)
    {
        $today = now()->startOfDay();
        $startDate = $today->copy()->subDays(13); // letzte 14 Tage inkl. heute

        // Hole alle Movements der letzten 14 Tage
        $movements = $article->stockMovements()
            ->whereBetween('created_at', [$startDate, $today->endOfDay()])
            ->get();

        // Erstelle eine Sammlung für die täglichen Änderungen
        $dailyChanges = collect();
        // Dies gibt uns den frühesten Tag, an dem es Movements gibt
        $earliestMovement = $movements->min(fn ($m) => $m->created_at);

        // Durchlaufe alle Tage im Zeitraum
        for ($date = $startDate->copy(); $date->lte($today); $date->addDay()) {
            // Standard: kein Movement → net_change = 0
            $netChange = 0;

            // Berechne nur Movements für Tage, an denen es auch welche gibt
            $dayMovements = $movements->filter(fn ($m) => $m->created_at->isSameDay($date));
            foreach ($dayMovements as $move) {
                switch ($move->type) {
                    case 'in':
                    case 'transfer':
                        $netChange += $move->quantity;
                        break;
                    case 'out':
                        $netChange -= $move->quantity;
                        break;
                    case 'correction':
                        // Bei correction entscheidet die Richtung
                        if ($move->to_storage_location_id) {
                            $netChange += $move->quantity;
                        }
                        if ($move->from_storage_location_id) {
                            $netChange -= $move->quantity;
                        }
                        break;
                }
            }

            $dailyChanges->push([
                'date' => $date->format('Y-m-d'),
                'net_change' => $netChange,
            ]);
        }

        // Nun berechnen wir die Bestände pro Tag
        // Vor dem ersten Movement ist alles 0.
        // Ab dem ersten Movement-Tag summieren wir die net_change auf einen laufenden Bestand.

        $cumulativeStockData = [];
        $runningStock = 0;

        // Finde den ersten Tag, an dem es tatsächlich ein Movement gab
        // (earliestMovement kann null sein, wenn es keine Movements gibt)
        $firstMovementDay = $earliestMovement ? $earliestMovement->startOfDay() : null;

        foreach ($dailyChanges as $dayData) {
            $currentDay = Carbon::createFromFormat('Y-m-d', $dayData['date']);
            $netChange = $dayData['net_change'];

            if ($firstMovementDay && $currentDay->gte($firstMovementDay)) {
                // Ab dem ersten Movement-Tag summieren wir auf
                $runningStock += $netChange;
                $cumulativeStockData[] = [
                    'date' => $dayData['date'],
                    'stock' => $runningStock
                ];
            } else {
                // Vor dem ersten Movement-Tag bleibt der Bestand 0
                $cumulativeStockData[] = [
                    'date' => $dayData['date'],
                    'stock' => 0
                ];
            }
        }

        return Inertia::render('Articles/Show', [
            'article' => $article->load(['stocks.storageLocation.shelf.rack.warehouse']),
            'stockMovements' => $article->stockMovements()
                ->with([
                    'fromStorageLocation.shelf.rack.warehouse',
                    'toStorageLocation.shelf.rack.warehouse',
                    'user'
                ])
                ->latest()
                ->paginate(10),
            'availableStorageLocations' => \App\Models\StorageLocation::with('shelf.rack.warehouse')->get(),
            'dailyChanges' => $dailyChanges,
            'cumulativeStockData' => $cumulativeStockData
        ]);
    }

    public function destroy(Article $article)
    {
        $dependencies = [
            'stocks' => $article->stocks()->sum('quantity'),
            'movements' => $article->stockMovements()->count()
        ];

        $article->delete();

        return redirect()->route('articles.index')
            ->with('message', 'Artikel erfolgreich gelöscht');
    }

    public function trashed()
    {
        return Inertia::render('Articles/Trashed', [
            'trashedArticles' => Article::onlyTrashed()
                ->withSum('stocks', 'quantity')
                ->withCount(['stocks', 'stockMovements'])
                ->get()
        ]);
    }

    public function restore($id)
    {
        $article = Article::onlyTrashed()->findOrFail($id);
        $article->restore();

        return redirect()->route('articles.trashed')
            ->with('message', 'Artikel erfolgreich wiederhergestellt');
    }

    public function forceDelete($id)
    {
        $article = Article::onlyTrashed()->findOrFail($id);
        $article->forceDelete();

        return redirect()->route('articles.trashed')
            ->with('message', 'Artikel endgültig gelöscht');
    }
}
