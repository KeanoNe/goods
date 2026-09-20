<?php

namespace Database\Seeders;

use App\Models\Rack;
use App\Models\Shelf;
use App\Models\StorageLocation;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run()
    {
        // Erstelle ein Testlager
        $warehouse = Warehouse::create([
            'name' => 'Hauptlager',
            'description' => 'Unser Hauptlager in Berlin',
            'location' => 'Berlin',
        ]);

        // Erstelle ein Testregal
        $rack = Rack::create([
            'name' => 'Regal A1',
            'description' => 'Erstes Regal im Hauptlager',
            'warehouse_id' => $warehouse->id,
        ]);

        // Erstelle einen Testregalboden
        $shelf = Shelf::create([
            'name' => 'Boden 1',
            'description' => 'Erster Boden in Regal A1',
            'rack_id' => $rack->id,
        ]);

        // Erstelle einen Testlagerplatz
        StorageLocation::create([
            'name' => 'A1-1-1',
            'description' => 'Erster Lagerplatz auf Boden 1',
            'shelf_id' => $shelf->id,
        ]);
    }
}
