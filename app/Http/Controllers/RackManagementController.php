<?php

namespace App\Http\Controllers;

use App\Models\Rack;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RackManagementController extends Controller
{
    public function index()
    {
        return Inertia::render('Racks/Index', [
            'racks' => Rack::with(['warehouse', 'shelves'])
                ->withCount(['shelves', 'shelves as storage_locations_count' => function ($query) {
                    $query->withCount('storageLocations');
                }])
                ->get(),
            'warehouses' => Warehouse::all()
        ]);
    }

    public function create()
    {
        return Inertia::render('Racks/UpsertRack', [
            'warehouses' => Warehouse::all()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'warehouse_id' => 'required|exists:warehouses,id',
            'description' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        Rack::create($validated);

        return redirect()->route('warehouses.index', ["tab" => "racks"])
            ->with('message', 'Regal erfolgreich erstellt');
    }

    public function edit(Rack $rack)
    {
        return Inertia::render('Racks/UpsertRack', [
            'rack' => $rack,
            'warehouses' => Warehouse::all()
        ]);
    }

    public function update(Request $request, Rack $rack)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'warehouse_id' => 'required|exists:warehouses,id',
            'description' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        $rack->update($validated);

        return redirect()->route('warehouses.index', ["tab" => "racks"])
            ->with('message', 'Regal erfolgreich aktualisiert');
    }

    public function destroy(Rack $rack)
    {
        $dependencies = [
            'shelves' => $rack->shelves()->count(),
            'storageLocations' => $rack->shelves()
                ->withCount('storageLocations')
                ->get()
                ->sum('storage_locations_count')
        ];

        $rack->delete();

        return redirect()->route('warehouses.index', ["tab" => "racks"])
            ->with('message', 'Regal erfolgreich gelöscht');
    }

    public function trashed()
    {
        return Inertia::render('Racks/Trashed', [
            'trashedRacks' => Rack::onlyTrashed()
                ->with('warehouse')
                ->withCount(['shelves', 'shelves as storage_locations_count' => function ($query) {
                    $query->withCount('storageLocations');
                }])
                ->get()
        ]);
    }

    public function restore($id)
    {
        $rack = Rack::onlyTrashed()->findOrFail($id);
        $rack->restore();

        return redirect()->route('racks.trashed')
            ->with('message', 'Regal erfolgreich wiederhergestellt');
    }

    public function forceDelete($id)
    {
        $rack = Rack::onlyTrashed()->findOrFail($id);
        $rack->forceDelete();

        return redirect()->route('racks.trashed')
            ->with('message', 'Regal endgültig gelöscht');
    }
}
