<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            'quantity' => 'nullable|integer|min:0',
        ]);

        $quantity = $validated['quantity'] ?? 0;

        // Prüfen, ob bereits ein Stock-Eintrag für diesen Artikel/Lagerplatz existiert
        $existingStock = Stock::where('article_id', $article->id)
            ->where('storage_location_id', $validated['storage_location_id'])
            ->first();

        if ($existingStock) {
            return back()->withErrors(['storage_location_id' => 'Dieser Lagerplatz ist bereits mit dem Artikel verknüpft.']);
        }

        DB::transaction(function () use ($article, $validated, $quantity) {
            $newStock = Stock::create([
                'article_id' => $article->id,
                'storage_location_id' => $validated['storage_location_id'],
                'quantity' => $quantity,
            ]);

            // Bestand zuzuteilen ist ein Zugang, deshalb to_storage_location_id.
            if ($quantity > 0) {
                StockMovement::create([
                    'article_id' => $article->id,
                    'to_storage_location_id' => $newStock->storage_location_id,
                    'quantity' => $quantity,
                    'type' => 'in',
                    'user_id' => Auth::id(),
                    'notes' => 'Initialer Bestand beim Zuweisen des Lagerplatzes',
                ]);
            }
        });

        return redirect()->route('articles.show', $article->id)
            ->with('message', 'Lagerplatz erfolgreich zugewiesen.');
    }

    /**
     * Entfernt die Zuordnung eines Lagerplatzes von einem Artikel.
     *
     * Ein noch vorhandener Restbestand wird zuvor ausgebucht. Ohne diese
     * Buchung verschwände er spurlos aus dem Journal, und Bestand und
     * Historie liefen auseinander.
     */
    public function destroy(Article $article, StorageLocation $storageLocation)
    {
        DB::transaction(function () use ($article, $storageLocation) {
            // Bestandszeile erst innerhalb der Transaktion und gesperrt laden.
            // Ohne die Sperre können zwei gleichzeitige Entfernungen dieselbe
            // Menge lesen, beide dieselbe Ausbuchung journalisieren und die
            // zweite Löschung liefe als stiller No-Op auf einer bereits
            // gelöschten Zeile — Journal und Bestand liefen auseinander.
            $stock = Stock::where('article_id', $article->id)
                ->where('storage_location_id', $storageLocation->id)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                throw new ModelNotFoundException('Die Zuordnung konnte nicht gefunden werden.');
            }

            if ($stock->quantity > 0) {
                StockMovement::create([
                    'article_id' => $article->id,
                    'from_storage_location_id' => $storageLocation->id,
                    'to_storage_location_id' => null,
                    'quantity' => $stock->quantity,
                    'type' => 'out',
                    'user_id' => Auth::id(),
                    'notes' => 'Lagerplatz-Zuordnung entfernt',
                ]);
            }

            $stock->delete();
        });

        return redirect()->route('articles.show', $article->id)
            ->with('message', 'Lagerplatz-Zuordnung erfolgreich entfernt.');
    }

    public function correction(Request $request, Article $article, StorageLocation $storageLocation)
    {
        $validated = $request->validate([
            'new_quantity' => 'required|integer|min:0',
        ]);

        $newQuantity = $validated['new_quantity'];

        DB::transaction(function () use ($article, $storageLocation, $newQuantity, $request) {
            // Bestandszeile erst innerhalb der Transaktion und gesperrt laden,
            // damit die alte Menge nicht unter einer gleichzeitigen Buchung
            // veraltet — sonst passt die gebuchte Differenz nicht zur
            // tatsächlichen Änderung und ein gleichzeitiges Update würde
            // stillschweigend verworfen.
            $stock = Stock::where('article_id', $article->id)
                ->where('storage_location_id', $storageLocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldQuantity = $stock->quantity;
            $difference = $newQuantity - $oldQuantity;

            $stock->update(['quantity' => $newQuantity]);

            // Ohne Mengenänderung gibt es nichts zu buchen. Bisher entstand
            // hier eine Bewegung mit Menge 0 und ohne Lagerplatz.
            if ($difference === 0) {
                return;
            }

            StockMovement::create([
                'article_id' => $article->id,
                'from_storage_location_id' => $difference < 0 ? $storageLocation->id : null,
                'to_storage_location_id' => $difference > 0 ? $storageLocation->id : null,
                'quantity' => abs($difference),
                'type' => 'correction',
                'user_id' => Auth::id(),
                'notes' => $request->input('notes', 'Bestandskorrektur'),
            ]);
        });

        return redirect()->route('articles.show', $article->id)
            ->with('message', 'Bestand erfolgreich korrigiert.');
    }
}
