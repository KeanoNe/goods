<?php

namespace Tests\Feature;

use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_lieferant_wird_angelegt(): void
    {
        $response = $this->post(route('suppliers.store'), [
            'name' => 'Schraubenhandel Nord',
            'contact_person' => 'Maria Groß',
            'email' => 'info@schraubenhandel-nord.test',
            'phone' => '040 123456',
            'address' => 'Hafenstraße 1, 20457 Hamburg',
            'customer_number' => 'KD-4711',
            'notes' => 'Liefert dienstags.',
        ]);

        $response->assertRedirect(route('suppliers.index'));
        $response->assertSessionHas('message', 'Lieferant erfolgreich erstellt');
        $this->assertDatabaseHas('suppliers', ['name' => 'Schraubenhandel Nord']);
    }

    public function test_lieferant_ohne_namen_wird_abgelehnt(): void
    {
        $response = $this->post(route('suppliers.store'), ['name' => '']);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('suppliers', 0);
    }

    public function test_lieferant_wird_aktualisiert(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Alt']);

        $response = $this->put(route('suppliers.update', $supplier), ['name' => 'Neu']);

        $response->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'Neu']);
    }

    public function test_lieferant_wandert_in_den_papierkorb(): void
    {
        $supplier = Supplier::factory()->create();

        $response = $this->delete(route('suppliers.destroy', $supplier));

        $response->assertRedirect(route('suppliers.index'));
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    public function test_lieferant_wird_wiederhergestellt(): void
    {
        $supplier = Supplier::factory()->create();
        $supplier->delete();

        $response = $this->put(route('suppliers.restore', $supplier->id));

        $response->assertRedirect(route('suppliers.trashed'));
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'deleted_at' => null]);
    }

    public function test_lieferant_ohne_bewegungen_wird_endgueltig_geloescht(): void
    {
        $supplier = Supplier::factory()->create();
        $supplier->delete();

        $response = $this->delete(route('suppliers.force-delete', $supplier->id));

        $response->assertRedirect(route('suppliers.trashed'));
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_lieferant_mit_bewegungen_wird_nicht_endgueltig_geloescht(): void
    {
        $supplier = Supplier::factory()->create();
        StockMovement::factory()->create(['supplier_id' => $supplier->id]);
        $supplier->delete();

        $response = $this->delete(route('suppliers.force-delete', $supplier->id));

        $response->assertSessionHasErrors('supplier');
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_lieferantenliste_ist_fuer_gaeste_gesperrt(): void
    {
        auth()->logout();

        $this->get(route('suppliers.index'))->assertRedirect(route('login'));
    }
}
