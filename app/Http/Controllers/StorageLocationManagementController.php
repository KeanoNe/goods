<?php

namespace App\Http\Controllers;

use App\Models\StorageLocation;
use App\Models\Shelf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class StorageLocationManagementController extends Controller
{
    public function index()
    {
        return Inertia::render('StorageLocations/Index', [
            'storageLocations' => StorageLocation::with(['shelf.rack.warehouse'])
                ->withCount('stocks')
                ->get(),
            'shelves' => Shelf::with(['rack.warehouse'])->get()
        ]);
    }

    public function create()
    {
        return Inertia::render('StorageLocations/UpsertStorageLocation', [
            'shelves' => Shelf::with(['rack.warehouse'])->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'shelf_id' => 'required|exists:shelves,id',
            'description' => 'nullable|string',
            'qr_code' => 'nullable|string|unique:storage_locations',
            'barcode' => 'nullable|string|unique:storage_locations',
            'notes' => 'nullable|string'
        ]);

        StorageLocation::create($validated);

        return redirect()->route('warehouses.index', ["tab" => "locations"])
            ->with('message', 'Lagerplatz erfolgreich erstellt');
    }

    public function edit(StorageLocation $storageLocation)
    {
        return Inertia::render('StorageLocations/UpsertStorageLocation', [
            'storageLocation' => $storageLocation,
            'shelves' => Shelf::with(['rack.warehouse'])->get()
        ]);
    }

    public function get(StorageLocation $storageLocation)
    {
        return response()->json($storageLocation);
    }

    public function update(Request $request, StorageLocation $storageLocation)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'shelf_id' => 'required|exists:shelves,id',
            'description' => 'nullable|string',
            'qr_code' => 'nullable|string|unique:storage_locations,qr_code,' . $storageLocation->id,
            'barcode' => 'nullable|string|unique:storage_locations,barcode,' . $storageLocation->id,
            'notes' => 'nullable|string'
        ]);

        $storageLocation->update($validated);

        return redirect()->route('warehouses.index', ["tab" => "locations"])
            ->with('message', 'Lagerplatz erfolgreich aktualisiert');
    }

    public function destroy(StorageLocation $storageLocation)
    {
        $dependencies = [
            'stocks' => $storageLocation->stocks()->count()
        ];

        $storageLocation->delete();

        return redirect()->route('warehouses.index', ["tab" => "locations"])
            ->with('message', 'Lagerplatz erfolgreich gelöscht');
    }

    public function trashed()
    {
        return Inertia::render('StorageLocations/Trashed', [
            'trashedStorageLocations' => StorageLocation::onlyTrashed()
                ->with(['shelf.rack.warehouse'])
                ->withCount('stocks')
                ->get()
        ]);
    }

    public function restore($id)
    {
        $storageLocation = StorageLocation::onlyTrashed()->findOrFail($id);
        $storageLocation->restore();

        return redirect()->route('storage-locations.trashed')
            ->with('message', 'Lagerplatz erfolgreich wiederhergestellt');
    }

    public function forceDelete($id)
    {
        $storageLocation = StorageLocation::onlyTrashed()->findOrFail($id);
        $storageLocation->forceDelete();

        return redirect()->route('storage-locations.trashed')
            ->with('message', 'Lagerplatz endgültig gelöscht');
    }

    public function generateQrCode(StorageLocation $storageLocation)
    {
        $writer = new PngWriter();

        // Create QR code
        $qrCode = new QrCode(
            data: (string)$storageLocation->id,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 150 * 3.78, // Convert 15cm to pixels (1cm = 37.8px at 96 DPI)
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255)
        );

        $result = $writer->write($qrCode);

        // Validate the result
        $writer->validateResult($result, (string)$storageLocation->id);

        $headers = [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="storage-location-' . $storageLocation->id . '-qr.png"',
        ];

        return response($result->getString(), 200, $headers);
    }
}
