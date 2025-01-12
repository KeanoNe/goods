<?php

use App\Http\Controllers\ArticleManagementController;
use App\Http\Controllers\ArticleStorageLocationController;
use App\Http\Controllers\RackManagementController;
use App\Http\Controllers\ShelfManagementController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StorageLocationManagementController;
use App\Http\Controllers\WarehouseManagementController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('welcome');

Route::get('impressum', function () {
    return Inertia::render('Impressum');
})->name('impressum');

Route::get('privacy', function () {
    return Inertia::render('PrivacyPolicy');
})->name('privacy');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/articles', function () {
        return Inertia::render('Articles');
    })->name('articles');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'),'verified'])->group(function () {
    Route::get('/warehouses', [WarehouseManagementController::class, 'index'])->name('warehouses.index');
    Route::get('/warehouses/create', [WarehouseManagementController::class, 'create'])->name('warehouses.create');
    Route::post('/warehouses', [WarehouseManagementController::class, 'store'])->name('warehouses.store');
    Route::get('/warehouses/{warehouse}/edit', [WarehouseManagementController::class, 'edit'])->name('warehouses.edit');
    Route::put('/warehouses/{warehouse}', [WarehouseManagementController::class, 'update'])->name('warehouses.update');
    Route::delete('/warehouses/{warehouse}', [WarehouseManagementController::class, 'destroy'])->name('warehouses.destroy');
    Route::get('/warehouses/trashed', [WarehouseManagementController::class, 'trashed'])->name('warehouses.trashed');
    Route::put('/warehouses/{warehouse}/restore', [WarehouseManagementController::class, 'restore'])->name('warehouses.restore');
    Route::delete('/warehouses/{warehouse}/force', [WarehouseManagementController::class, 'forceDelete'])->name('warehouses.force-delete');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'),'verified'])->group(function () {
    // Route::get('/racks', [RackManagementController::class, 'index'])->name('racks.index');
    Route::get('/racks/create', [RackManagementController::class, 'create'])->name('racks.create');
    Route::post('/racks', [RackManagementController::class, 'store'])->name('racks.store');
    Route::get('/racks/{rack}/edit', [RackManagementController::class, 'edit'])->name('racks.edit');
    Route::put('/racks/{rack}', [RackManagementController::class, 'update'])->name('racks.update');
    Route::delete('/racks/{rack}', [RackManagementController::class, 'destroy'])->name('racks.destroy');
    Route::get('/racks/trashed', [RackManagementController::class, 'trashed'])->name('racks.trashed');
    Route::put('/racks/{rack}/restore', [RackManagementController::class, 'restore'])->name('racks.restore');
    Route::delete('/racks/{rack}/force', [RackManagementController::class, 'forceDelete'])->name('racks.force-delete');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'),'verified'])->group(function () {
    // Route::get('/shelves', [ShelfManagementController::class, 'index'])->name('shelves.index');
    Route::get('/shelves/create', [ShelfManagementController::class, 'create'])->name('shelves.create');
    Route::post('/shelves', [ShelfManagementController::class, 'store'])->name('shelves.store');
    Route::get('/shelves/{shelf}/edit', [ShelfManagementController::class, 'edit'])->name('shelves.edit');
    Route::put('/shelves/{shelf}', [ShelfManagementController::class, 'update'])->name('shelves.update');
    Route::delete('/shelves/{shelf}', [ShelfManagementController::class, 'destroy'])->name('shelves.destroy');
    Route::get('/shelves/trashed', [ShelfManagementController::class, 'trashed'])->name('shelves.trashed');
    Route::put('/shelves/{shelf}/restore', [ShelfManagementController::class, 'restore'])->name('shelves.restore');
    Route::delete('/shelves/{shelf}/force', [ShelfManagementController::class, 'forceDelete'])->name('shelves.force-delete');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'),'verified'])->group(function () {
    // Route::get('/storage-locations', [StorageLocationManagementController::class, 'index'])->name('storage-locations.index');
    Route::get('/storage-locations/create', [StorageLocationManagementController::class, 'create'])->name('storage-locations.create');
    Route::post('/storage-locations', [StorageLocationManagementController::class, 'store'])->name('storage-locations.store');
    Route::get('/storage-locations/{storageLocation}/edit', [StorageLocationManagementController::class, 'edit'])->name('storage-locations.edit');
    Route::put('/storage-locations/{storageLocation}', [StorageLocationManagementController::class, 'update'])->name('storage-locations.update');
    Route::delete('/storage-locations/{storageLocation}', [StorageLocationManagementController::class, 'destroy'])->name('storage-locations.destroy');
    Route::get('/stouri: rage-locations/{storageLocation}', [StorageLocationManagementController::class, 'get'])->name('storage-locations.get');
    Route::get('/storage-locations/trashed', [StorageLocationManagementController::class, 'trashed'])->name('storage-locations.trashed');
    Route::put('/storage-locations/{storageLocation}/restore', [StorageLocationManagementController::class, 'restore'])->name('storage-locations.restore');
    Route::delete('/storage-locations/{storageLocation}/force', [StorageLocationManagementController::class, 'forceDelete'])->name('storage-locations.force-delete');
    Route::get('/locations/{storageLocation}/qr-code', [StorageLocationManagementController::class, 'generateQrCode'])->name('storage-locations.qr-code');
});


Route::middleware(['auth:sanctum', config('jetstream.auth_session'),'verified'])->group(function () {
    Route::get('/articles', [ArticleManagementController::class, 'index'])->name('articles.index');
    Route::get('/articles/create', [ArticleManagementController::class, 'create'])->name('articles.create');
    Route::post('/articles', [ArticleManagementController::class, 'store'])->name('articles.store');
    Route::get('/articles/{article}', [ArticleManagementController::class, 'show'])->name('articles.show');
    Route::get('/articles/{article}/edit', [ArticleManagementController::class, 'edit'])->name('articles.edit');
    Route::put('/articles/{article}', [ArticleManagementController::class, 'update'])->name('articles.update');
    Route::delete('/articles/{article}', [ArticleManagementController::class, 'destroy'])->name('articles.destroy');
    Route::get('/articles/trashed', [ArticleManagementController::class, 'trashed'])->name('articles.trashed');
    Route::put('/articles/{article}/restore', [ArticleManagementController::class, 'restore'])->name('articles.restore');
    Route::delete('/articles/{article}/force', [ArticleManagementController::class, 'forceDelete'])->name('articles.force-delete');

    Route::post('/articles/{article}/storage-locations', [ArticleStorageLocationController::class, 'store'])
        ->name('articles.storage-locations.store');

    // DELETE: Lagerplatz-Zuordnung von einem Artikel entfernen
    Route::delete('/articles/{article}/storage-locations/{storageLocation}', [ArticleStorageLocationController::class, 'destroy'])
        ->name('articles.storage-locations.destroy');

    Route::post('/articles/{article}/storage-locations/{storageLocation}/correction', [ArticleStorageLocationController::class, 'correction'])
        ->name('articles.storage-locations.correction');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'),'verified'])
    ->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

        // Hier würden auch Deine anderen Routen für Artikel, Lagerplätze etc. stehen
    });


Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    // Stock Movement Routes
    Route::prefix('stock')->name('stock.')->group(function () {
        // Übersichtsseite für Lagerbewegungen
        Route::get('/movements', [StockMovementController::class, 'index'])
            ->name('movements.index');

        // API Endpunkte für Lagerbewegungen
        Route::prefix('api')->name('api.')->group(function () {
            // Lagerort Details abrufen
            Route::get('/locations/{storageLocation}', [StockMovementController::class, 'getLocation'])
                ->name('location.show');

            // Bestand aktualisieren
            Route::post('/movements', [StockMovementController::class, 'update'])
                ->name('movement.store');
        });
    });
});
