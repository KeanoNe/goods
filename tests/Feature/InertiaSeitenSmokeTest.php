<?php

namespace Tests\Feature;

use App\Models\Rack;
use App\Models\Shelf;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Prueft, dass die Inertia-Seiten serverseitig fehlerfrei rendern und die
 * erwartete Komponente ausliefern. Deckt den Adapter-Sprung auf Inertia 3 ab.
 */
class InertiaSeitenSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function oeffentlicheSeiten(): array
    {
        return [
            'Welcome' => ['/', 'Welcome'],
            'Impressum' => ['/impressum', 'Impressum'],
            'Datenschutz' => ['/privacy', 'PrivacyPolicy'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function geschuetzteSeiten(): array
    {
        return [
            'Dashboard' => ['dashboard', 'Dashboard'],
            'Lager' => ['warehouses.index', 'Warehouses/Index'],
            'Lager anlegen' => ['warehouses.create', 'Warehouses/UpsertWarehouse'],
            'Lager-Papierkorb' => ['warehouses.trashed', 'Warehouses/Trashed'],
            'Regal anlegen' => ['racks.create', 'Racks/UpsertRack'],
            'Regal-Papierkorb' => ['racks.trashed', 'Racks/Trashed'],
            'Fach anlegen' => ['shelves.create', 'Shelves/UpsertShelf'],
            'Fach-Papierkorb' => ['shelves.trashed', 'Shelves/Trashed'],
            'Lagerplatz anlegen' => ['storage-locations.create', 'StorageLocations/UpsertStorageLocation'],
            'Lagerplatz-Papierkorb' => ['storage-locations.trashed', 'StorageLocations/Trashed'],
            'Artikel' => ['articles.index', 'Articles/Index'],
            'Artikel anlegen' => ['articles.create', 'Articles/UpsertArticle'],
            'Lieferanten' => ['suppliers.index', 'Suppliers/Index'],
            'Lieferant anlegen' => ['suppliers.create', 'Suppliers/UpsertSupplier'],
            'Lieferanten-Papierkorb' => ['suppliers.trashed', 'Suppliers/Trashed'],
            'Bestandsbewegungen' => ['stock.movements.index', 'StockMovement/Index'],
            'Bilanz' => ['reports.balance.index', 'Reports/Balance'],
        ];
    }

    #[DataProvider('oeffentlicheSeiten')]
    public function test_oeffentliche_seite_rendert(string $pfad, string $komponente): void
    {
        $response = $this->get($pfad);

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component($komponente));
    }

    #[DataProvider('geschuetzteSeiten')]
    public function test_geschuetzte_seite_rendert(string $routenname, string $komponente): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route($routenname));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component($komponente));
    }

    public function test_bearbeitungsseiten_der_lagerstruktur_rendern(): void
    {
        $this->actingAs(User::factory()->create());

        $warehouse = Warehouse::factory()->create();
        $rack = Rack::factory()->for($warehouse)->create();
        $shelf = Shelf::factory()->for($rack)->create();
        $storageLocation = StorageLocation::factory()->for($shelf)->create();

        $this->get(route('warehouses.edit', $warehouse))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Warehouses/UpsertWarehouse'));

        $this->get(route('racks.edit', $rack))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Racks/UpsertRack'));

        $this->get(route('shelves.edit', $shelf))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Shelves/UpsertShelf'));

        $this->get(route('storage-locations.edit', $storageLocation))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('StorageLocations/UpsertStorageLocation'));
    }

    public function test_bearbeitungsseite_des_lieferanten_rendert(): void
    {
        $this->actingAs(User::factory()->create());

        $supplier = Supplier::factory()->create();

        $this->get(route('suppliers.edit', $supplier))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Suppliers/UpsertSupplier'));
    }
}
