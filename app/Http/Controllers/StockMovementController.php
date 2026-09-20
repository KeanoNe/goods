<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class StockMovementController extends Controller
{
    public function index()
    {
        // Lade die letzten Bewegungen für die Übersicht
        $movements = StockMovement::with(['article', 'fromStorageLocation', 'toStorageLocation', 'user'])
            ->latest()
            ->paginate(15);

        return Inertia::render('StockMovement/Index', [
            'movements' => $movements,
        ]);
    }

    public function getLocation(StorageLocation $storageLocation)
    {
        $location = StorageLocation::with([
            'shelf.rack.warehouse',
            'stocks' => function ($query) {
                // Nur Stocks laden, deren verknüpfte Article nicht gelöscht sind
                $query->whereHas('article', function ($query) {
                    $query->whereNull('deleted_at');
                });
            },
            'stocks.article',
            'stocks.article.suppliers',
        ])->findOrFail($storageLocation->id);

        $stocks = $location->stocks->map(function ($stock) {
            $suppliers = $stock->article->suppliers;
            $standard = $suppliers->first(fn ($supplier) => (bool) $supplier->pivot->is_default);

            return [
                'id' => $stock->article->id,
                'name' => $stock->article->name,
                'current_stock' => $stock->quantity,
                'sku' => $stock->article->sku,
                'suppliers' => $suppliers->map(fn ($supplier) => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'price' => $supplier->pivot->price,
                ])->values(),
                'default_supplier_id' => $standard?->id,
            ];
        });

        return response()->json([
            'location' => [
                'id' => $location->id,
                'name' => sprintf(
                    '%s - %s - %s - %s',
                    $location->shelf->rack->warehouse->name,
                    $location->shelf->rack->name,
                    $location->shelf->name,
                    $location->name
                ),
                'stocks' => $stocks,
            ],
        ]);
    }

    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'location_id' => 'required|exists:storage_locations,id',
                'quantity' => 'required|integer|min:1',
                'type' => 'required|in:add,remove',
                'article_id' => 'required|exists:articles,id',
                'supplier_id' => [
                    'nullable',
                    function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                        $istZugeordnet = DB::table('article_supplier')
                            ->where('article_id', $request->input('article_id'))
                            ->where('supplier_id', $value)
                            ->exists();

                        if (! $istZugeordnet) {
                            $fail('Der gewählte Lieferant ist diesem Artikel nicht zugeordnet.');

                            return;
                        }

                        // Die Pivot-Zuordnung bleibt beim Soft-Delete des Lieferanten
                        // bestehen — deshalb muss der Lieferant selbst zusätzlich
                        // geprüft werden.
                        if (Supplier::onlyTrashed()->whereKey($value)->exists()) {
                            $fail('Der gewählte Lieferant wurde in den Papierkorb verschoben und kann nicht mehr gebucht werden.');
                        }
                    },
                ],
                'notes' => 'nullable|string|max:1000',
            ]);

            return DB::transaction(function () use ($validated) {
                $storageLocation = StorageLocation::findOrFail($validated['location_id']);
                $article = Article::findOrFail($validated['article_id']);

                // Preis-Snapshot: der Stückpreis wird serverseitig aus der
                // Pivot-Tabelle gelesen, niemals aus dem Request übernommen.
                $unitPrice = null;

                if (! empty($validated['supplier_id'])) {
                    $unitPrice = DB::table('article_supplier')
                        ->where('article_id', $article->id)
                        ->where('supplier_id', $validated['supplier_id'])
                        ->value('price');
                }

                // Hole oder erstelle den Stock-Eintrag
                $stock = Stock::firstOrCreate(
                    [
                        'storage_location_id' => $storageLocation->id,
                        'article_id' => $article->id,
                    ],
                    ['quantity' => 0]
                );

                // Prüfe ob genug Bestand für Entnahme vorhanden ist
                if ($validated['type'] === 'remove' && $stock->quantity < $validated['quantity']) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Nicht genügend Bestand verfügbar. Aktueller Bestand: '.$stock->quantity],
                    ]);
                }

                // Aktualisiere den Bestand
                $newQuantity = $validated['type'] === 'add'
                    ? $stock->quantity + $validated['quantity']
                    : $stock->quantity - $validated['quantity'];

                $stock->update(['quantity' => $newQuantity]);

                // Erstelle einen Bewegungseintrag
                $movement = StockMovement::create([
                    'article_id' => $article->id,
                    'supplier_id' => $validated['supplier_id'] ?? null,
                    'from_storage_location_id' => $validated['type'] === 'remove' ? $storageLocation->id : null,
                    'to_storage_location_id' => $validated['type'] === 'add' ? $storageLocation->id : null,
                    'quantity' => $validated['quantity'],
                    'unit_price' => $unitPrice,
                    'type' => $validated['type'] === 'add' ? 'in' : 'out',
                    'notes' => $validated['notes'] ?? null,
                    'user_id' => Auth::id(),
                ]);

                // Prüfe Mindestbestand und bereite Warnung vor
                $warning = null;
                if ($newQuantity <= $article->minimum_stock) {
                    $warning = "Achtung: Bestand unterschreitet Mindestbestand von {$article->minimum_stock}!";
                }

                return response()->json([
                    'message' => 'Bestand erfolgreich aktualisiert',
                    'current_stock' => $newQuantity,
                    'movement' => $movement->load(['article', 'fromStorageLocation', 'toStorageLocation', 'user']),
                    'warning' => $warning,
                ]);
            });

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ein Fehler ist aufgetreten: '.$e->getMessage(),
            ], 500);
        }
    }
}
