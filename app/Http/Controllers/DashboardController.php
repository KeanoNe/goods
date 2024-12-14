<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Gesamtbestand aller Artikel
        $totalStock = DB::table('stocks')->sum('quantity');

        // 2. Anzahl der Artikel unter Mindestbestand
        $articlesUnderMinimumCount = Article::withSum('stocks', 'quantity')
            ->get()
            ->filter(function ($article) {
                return $article->stocks_sum_quantity < $article->minimum_stock;
            })
            ->count();

        // 3. Letzte Bestandsbewegungen (die letzten 5)
        $lastMovements = StockMovement::with(['article', 'user'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($movement) {
                return [
                    'date' => $movement->created_at->format('Y-m-d'),
                    'article' => $movement->article->name,
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'user' => $movement->user ? $movement->user->name : 'Unbekannt',
                ];
            });

        // 4. Anzahl der Artikel ohne Bewegung
        $articlesWithoutMovementCount = Article::whereDoesntHave('stockMovements')->count();

        // 5. Anzahl aller Artikel
        $totalArticlesCount = Article::count();

        // 6. Liste der Artikel unter Mindestbestand
        $articlesUnderMinimumList = Article::withSum('stocks', 'quantity')
            ->get()
            ->filter(function ($article) {
                return $article->stocks_sum_quantity < $article->minimum_stock;
            })
            ->map(function ($article) {
                return [
                    'id' => $article->id,
                    'name' => $article->name,
                    'current_stock' => $article->stocks_sum_quantity,
                    'minimum_stock' => $article->minimum_stock,
                ];
            });

        return Inertia::render('Dashboard', [
            'totalStock' => $totalStock,
            'articlesUnderMinimumCount' => $articlesUnderMinimumCount,
            'lastMovements' => $lastMovements,
            'articlesWithoutMovementCount' => $articlesWithoutMovementCount,
            'totalArticlesCount' => $totalArticlesCount,
            'articlesUnderMinimumList' => $articlesUnderMinimumList, // Neue Daten für die Tabelle
        ]);
    }
}
