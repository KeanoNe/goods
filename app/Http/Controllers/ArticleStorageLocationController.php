<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\StorageLocation;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

class ArticleStorageLocationController extends Controller
{
    /**
     * Weist einen Lagerplatz einem Artikel zu.
     *
     * Erzeugt einen neuen Eintrag in der stocks-Tabelle.
     * Erwartet im Request:
     * - storage_location_id: ID eines vorhandenen Lagerplatzes (required)
     * - quantity: Anfangsbestand (optional, default = 0)
     */
    public function store(Request $request, Article $article)
    {
        $validated = $request->validate([
            'storage_location_id' => 'required|exists:storage_locations,id',
            'quantity' => 'nullable|integer|min:0'
        ]);

        $quantity = $validated['quantity'] ?? 0;

        // Prüfen, ob bereits ein Stock-Eintrag für diesen Artikel/Lagerplatz existiert
        $existingStock = Stock::where('article_id', $article->id)
            ->where('storage_location_id', $validated['storage_location_id'])
            ->first();

        if ($existingStock) {
            return back()->withErrors(['storage_location_id' => 'Dieser Lagerplatz ist bereits mit dem Artikel verknüpft.']);
        }

        // Neuen Stock-Eintrag erstellen
        $newStock = Stock::create([
            'article_id' => $article->id,
            'storage_location_id' => $validated['storage_location_id'],
            'quantity' => $quantity,
        ]);

        // Wenn eine positive Menge angelegt wird, erzeugen wir auch einen StockMovement-Eintrag.
        if ($quantity > 0) {
            StockMovement::create([
                'article_id' => $article->id,
                // Da wir Bestand zuteilen, ist dies ein Zugang. Wir setzen to_storage_location_id:
                'to_storage_location_id' => $newStock->storage_location_id,
                'quantity' => $quantity,
                'type' => 'in', // oder 'correction', falls Du das eher als Korrektur verstehst
                'user_id' => Auth::user()->id,
                'notes' => 'Initialer Bestand beim Zuweisen des Lagerplatzes'
            ]);
        }

        return redirect()->route('articles.show', $article->id)
            ->with('message', 'Lagerplatz erfolgreich zugewiesen.');
    }

    /**
     * Entfernt die Zuordnung eines Lagerplatzes von einem Artikel.
     *
     * Löscht den Stock-Eintrag aus der Datenbank.
     */
    public function destroy(Article $article, StorageLocation $storageLocation)
    {
        // Versuchen den entsprechenden Stock-Satz zu finden
        $stock = Stock::where('article_id', $article->id)
            ->where('storage_location_id', $storageLocation->id)
            ->first();

        if (!$stock) {
            throw new ModelNotFoundException('Die Zuordnung konnte nicht gefunden werden.');
        }

        $stock->delete();

        return redirect()->route('articles.show', $article->id)
            ->with('message', 'Lagerplatz-Zuordnung erfolgreich entfernt.');
    }

    public function correction(Request $request, Article $article, StorageLocation $storageLocation)
    {
        $validated = $request->validate([
            'new_quantity' => 'required|integer|min:0'
        ]);

        $stock = Stock::where('article_id', $article->id)
            ->where('storage_location_id', $storageLocation->id)
            ->firstOrFail();

        // Alte Menge merken
        $oldQuantity = $stock->quantity;
        $newQuantity = $validated['new_quantity'];
        $difference = $newQuantity - $oldQuantity;

        // Stock aktualisieren
        $stock->update(['quantity' => $newQuantity]);

        // Stock-Movement anlegen
        // Da es sich um eine Korrektur handelt, können from/to_storage_location gleich sein,
        // oder from_storage_location leer, wenn gewünscht. Hier setzen wir beide auf den selben Platz:
        StockMovement::create([
            'article_id' => $article->id,
            'from_storage_location_id' => $difference < 0 ? $storageLocation->id : null, // bei negativer Korrektur interpretieren wir es als Ausgang von diesem Platz
            'to_storage_location_id' => $difference > 0 ? $storageLocation->id : null, // bei positiver Korrektur interpretieren wir es als Eingang in diesen Platz
            'quantity' => abs($difference),
            'type' => 'correction',
            'user_id' => Auth::user()->id,
            'notes' => $request->input('notes', 'Bestandskorrektur')
        ]);

        return redirect()->route('articles.show', $article->id)
            ->with('message', 'Bestand erfolgreich korrigiert.');
    }
}
