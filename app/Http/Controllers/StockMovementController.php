<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\Article;
use App\Models\StorageLocation;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockMovementController extends Controller
{
    public function index()
    {
        // Lade die letzten Bewegungen für die Übersicht
        $movements = StockMovement::with(['article', 'fromStorageLocation', 'toStorageLocation', 'user'])
            ->latest()
            ->paginate(15);

        return Inertia::render('StockMovement/Index', [
            'movements' => $movements
        ]);
    }

    public function getLocation(StorageLocation $storageLocation)
    {
        $location = StorageLocation::with([
            'shelf.rack.warehouse',
            'stocks.article'
        ])->findOrFail($storageLocation->id);

        // Hole alle Artikel an diesem Lagerort
        $stocks = $location->stocks->filter(function ($stock) {
            return $stock->article?->id != null && $stock->article->deleted_at === null;
        });

        $stocks = $stocks->map(function ($stock) {
            return [
                'id' => $stock->article->id,
                'name' => $stock->article->name,
                'current_stock' => $stock->quantity,
                'sku' => $stock->article->sku
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
                'stocks' => $stocks
            ]
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
                'notes' => 'nullable|string|max:1000'
            ]);

            return DB::transaction(function () use ($validated) {
                $storageLocation = StorageLocation::findOrFail($validated['location_id']);
                $article = Article::findOrFail($validated['article_id']);

                // Hole oder erstelle den Stock-Eintrag
                $stock = Stock::firstOrCreate(
                    [
                        'storage_location_id' => $storageLocation->id,
                        'article_id' => $article->id
                    ],
                    ['quantity' => 0]
                );

                // Prüfe ob genug Bestand für Entnahme vorhanden ist
                if ($validated['type'] === 'remove' && $stock->quantity < $validated['quantity']) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Nicht genügend Bestand verfügbar. Aktueller Bestand: ' . $stock->quantity]
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
                    'from_storage_location_id' => $validated['type'] === 'remove' ? $storageLocation->id : null,
                    'to_storage_location_id' => $validated['type'] === 'add' ? $storageLocation->id : null,
                    'quantity' => $validated['quantity'],
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
                    'warning' => $warning
                ]);
            });

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()
            ], 500);
        }
    }
}
