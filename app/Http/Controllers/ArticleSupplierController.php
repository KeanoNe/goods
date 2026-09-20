<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ArticleSupplierController extends Controller
{
    /**
     * Ordnet dem Artikel einen Lieferanten mit Stückpreis zu.
     *
     * Der erste Lieferant eines Artikels wird immer zum Standard, damit beim
     * Buchen stets eine Vorauswahl existiert.
     */
    public function store(Request $request, Article $article)
    {
        $validated = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'price' => 'required|numeric|min:0',
            'is_default' => 'boolean',
        ]);

        if ($article->suppliers()->whereKey($validated['supplier_id'])->exists()) {
            return back()->withErrors([
                'supplier_id' => 'Dieser Lieferant ist dem Artikel bereits zugeordnet.',
            ]);
        }

        DB::transaction(function () use ($article, $validated) {
            Article::whereKey($article->id)->lockForUpdate()->first();

            $alsStandard = ($validated['is_default'] ?? false) || $article->suppliers()->doesntExist();

            if ($alsStandard) {
                $this->standardZuruecksetzen($article);
            }

            $article->suppliers()->attach($validated['supplier_id'], [
                'price' => $validated['price'],
                'is_default' => $alsStandard,
            ]);
        });

        return redirect()->route('articles.show', $article)
            ->with('message', 'Lieferant erfolgreich zugeordnet');
    }

    /**
     * Ändert den Stückpreis einer bestehenden Zuordnung. Die Standardmarkierung
     * wird nur verändert, wenn `is_default` ausdrücklich auf true gesetzt wird —
     * fehlt sie oder ist sie false, bleibt der bisherige Standard unangetastet,
     * damit ein Artikel nie ohne Standard zurückbleibt.
     */
    public function update(Request $request, Article $article, Supplier $supplier)
    {
        abort_unless($article->suppliers()->whereKey($supplier->id)->exists(), 404);

        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
            'is_default' => 'boolean',
        ]);

        DB::transaction(function () use ($article, $supplier, $validated) {
            Article::whereKey($article->id)->lockForUpdate()->first();

            $pivotDaten = ['price' => $validated['price']];

            if ($validated['is_default'] ?? false) {
                $this->standardZuruecksetzen($article);
                $pivotDaten['is_default'] = true;
            }

            $article->suppliers()->updateExistingPivot($supplier->id, $pivotDaten);
        });

        return redirect()->route('articles.show', $article)
            ->with('message', 'Lieferantenpreis erfolgreich aktualisiert');
    }

    /**
     * Löst die Zuordnung. War der entfernte Lieferant der Standard, rückt
     * der nächste verbliebene nach.
     */
    public function destroy(Article $article, Supplier $supplier)
    {
        abort_unless($article->suppliers()->whereKey($supplier->id)->exists(), 404);

        DB::transaction(function () use ($article, $supplier) {
            Article::whereKey($article->id)->lockForUpdate()->first();

            $zuordnung = $article->suppliers()->whereKey($supplier->id)->first();

            abort_unless($zuordnung !== null, 404);

            $warStandard = (bool) $zuordnung->pivot->is_default;

            $article->suppliers()->detach($supplier->id);

            if ($warStandard) {
                // Über die Query Builder-Tabelle statt der Beziehung, damit ein
                // verbliebener, aber bereits in den Papierkorb verschobener
                // Lieferant als Nachfolger nicht durch den Soft-Delete-Scope
                // verborgen wird.
                $nachfolgerId = DB::table('article_supplier')
                    ->where('article_id', $article->id)
                    ->value('supplier_id');

                if ($nachfolgerId !== null) {
                    DB::table('article_supplier')
                        ->where('article_id', $article->id)
                        ->where('supplier_id', $nachfolgerId)
                        ->update(['is_default' => true]);
                }
            }
        });

        return redirect()->route('articles.show', $article)
            ->with('message', 'Lieferantenzuordnung erfolgreich entfernt');
    }

    /**
     * Setzt alle Standardmarkierungen des Artikels zurück. MySQL kennt keine
     * partiellen Unique-Indizes, deshalb wird die Invariante hier erzwungen.
     */
    private function standardZuruecksetzen(Article $article): void
    {
        DB::table('article_supplier')
            ->where('article_id', $article->id)
            ->update(['is_default' => false]);
    }
}
