<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierManagementController extends Controller
{
    public function index()
    {
        return Inertia::render('Suppliers/Index', [
            'suppliers' => Supplier::withCount('articles')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Suppliers/UpsertSupplier');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->validierungsregeln());

        Supplier::create($validated);

        return redirect()->route('suppliers.index')
            ->with('message', 'Lieferant erfolgreich erstellt');
    }

    public function edit(Supplier $supplier)
    {
        return Inertia::render('Suppliers/UpsertSupplier', [
            'supplier' => $supplier,
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate($this->validierungsregeln());

        $supplier->update($validated);

        return redirect()->route('suppliers.index')
            ->with('message', 'Lieferant erfolgreich aktualisiert');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('message', 'Lieferant erfolgreich gelöscht');
    }

    public function trashed()
    {
        return Inertia::render('Suppliers/Trashed', [
            'trashedSuppliers' => Supplier::onlyTrashed()->orderBy('name')->get(),
        ]);
    }

    public function restore($id)
    {
        $supplier = Supplier::onlyTrashed()->findOrFail($id);
        $supplier->restore();

        return redirect()->route('suppliers.trashed')
            ->with('message', 'Lieferant erfolgreich wiederhergestellt');
    }

    public function forceDelete($id)
    {
        $supplier = Supplier::onlyTrashed()->findOrFail($id);

        if ($supplier->stockMovements()->exists()) {
            return redirect()->route('suppliers.trashed')
                ->withErrors([
                    'supplier' => 'Der Lieferant kann nicht endgültig gelöscht werden, weil er in Lagerbewegungen verwendet wird.',
                ]);
        }

        $supplier->forceDelete();

        return redirect()->route('suppliers.trashed')
            ->with('message', 'Lieferant endgültig gelöscht');
    }

    /**
     * Validierungsregeln für store() und update().
     *
     * @return array<string, string>
     */
    private function validierungsregeln(): array
    {
        return [
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'customer_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
