<?php

namespace App\Http\Controllers;

use App\Models\Shelf;
use App\Models\Rack;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShelfManagementController extends Controller
{
    public function index()
    {
        return Inertia::render('Shelves/Index', [
            'shelves' => Shelf::with(['rack.warehouse'])
                ->withCount('storageLocations')
                ->get(),
            'racks' => Rack::with('warehouse')->get()
        ]);
    }

    public function create()
    {
        return Inertia::render('Shelves/UpsertShelf', [
            'racks' => Rack::with('warehouse')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rack_id' => 'required|exists:racks,id',
            'description' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        Shelf::create($validated);

        return redirect()->route('warehouses.index', ["tab" => "shelves"])
            ->with('message', 'Regalboden erfolgreich erstellt');
    }

    public function edit(Shelf $shelf)
    {
        return Inertia::render('Shelves/UpsertShelf', [
            'shelf' => $shelf,
            'racks' => Rack::with('warehouse')->get()
        ]);
    }

    public function update(Request $request, Shelf $shelf)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rack_id' => 'required|exists:racks,id',
            'description' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        $shelf->update($validated);

        return redirect()->route('warehouses.index', ["tab" => "shelves"])
            ->with('message', 'Regalboden erfolgreich aktualisiert');
    }

    public function destroy(Shelf $shelf)
    {
        $dependencies = [
            'storageLocations' => $shelf->storageLocations()->count()
        ];

        $shelf->delete();

        return redirect()->route('warehouses.index', ["tab" => "shelves"])
            ->with('message', 'Regalboden erfolgreich gelöscht');
    }

    public function trashed()
    {
        return Inertia::render('Shelves/Trashed', [
            'trashedShelves' => Shelf::onlyTrashed()
                ->with(['rack.warehouse'])
                ->withCount('storageLocations')
                ->get()
        ]);
    }

    public function restore($id)
    {
        $shelf = Shelf::onlyTrashed()->findOrFail($id);
        $shelf->restore();

        return redirect()->route('shelves.trashed')
            ->with('message', 'Regalboden erfolgreich wiederhergestellt');
    }

    public function forceDelete($id)
    {
        $shelf = Shelf::onlyTrashed()->findOrFail($id);
        $shelf->forceDelete();

        return redirect()->route('shelves.trashed')
            ->with('message', 'Regalboden endgültig gelöscht');
    }
}
