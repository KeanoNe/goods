<?php

namespace App\Http\Controllers;

use App\Models\Rack;
use App\Models\Shelf;
use App\Models\StorageLocation;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WarehouseManagementController extends Controller
{
    public function index()
    {
        return Inertia::render('Warehouses/Index', [
            'warehouses' => Warehouse::with('racks.shelves')->get(),
            'racks' => Rack::with('warehouse', 'shelves', 'storageLocations')->get(),
            'shelves' => Shelf::with('rack.warehouse', 'storageLocations')->get(),
            'locations' => StorageLocation::with('shelf.rack.warehouse', 'articles')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Warehouses/UpsertWarehouse');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        Warehouse::create($validated);

        return redirect()->route('warehouses.index')
            ->with('message', 'Lager erfolgreich erstellt');
    }

    public function edit(Warehouse $warehouse)
    {
        return Inertia::render('Warehouses/UpsertWarehouse', [
            'warehouse' => $warehouse
        ]);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        $warehouse->update($validated);

        return redirect()->route('warehouses.index')
            ->with('message', 'Lager erfolgreich aktualisiert');
    }

    public function destroy(Warehouse $warehouse)
    {
        // Laden der abhängigen Elemente
        $dependencies = [
            'racks' => $warehouse->racks()->count(),
            'shelves' => $warehouse->racks()->withCount('shelves')->get()->sum('shelves_count'),
            'storageLocations' => $warehouse->racks()
                ->withCount(['shelves' => function ($query) {
                    $query->withCount('storageLocations');
                }])
                ->get()
                ->sum(function ($rack) {
                    return $rack->shelves->sum('storage_locations_count');
                })
        ];

        $warehouse->delete();

        return redirect()->route('warehouses.index')
            ->with('message', 'Lager erfolgreich gelöscht');
    }

    public function trashed()
    {
        return Inertia::render('Warehouses/Trashed', [
            'trashedWarehouses' => Warehouse::onlyTrashed()
                ->withCount(['racks', 'racks as total_shelves' => function ($query) {
                    $query->withCount('shelves');
                }])
                ->get()
        ]);
    }

    public function restore($id)
    {
        $warehouse = Warehouse::onlyTrashed()->findOrFail($id);
        $warehouse->restore();

        return redirect()->route('warehouses.trashed')
            ->with('message', 'Lager erfolgreich wiederhergestellt');
    }

    public function forceDelete($id)
    {
        $warehouse = Warehouse::onlyTrashed()->findOrFail($id);
        $warehouse->forceDelete();

        return redirect()->route('warehouses.trashed')
            ->with('message', 'Lager endgültig gelöscht');
    }
}
