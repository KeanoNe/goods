<?php

namespace Tests\Feature;

use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Zxing\QrReader;

class StorageLocationQrCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_code_wird_als_png_download_ausgeliefert(): void
    {
        $this->actingAs(User::factory()->create());

        $storageLocation = StorageLocation::factory()->create();

        $response = $this->get(route('storage-locations.qr-code', $storageLocation));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader(
            'Content-Disposition',
            'attachment; filename="storage-location-'.$storageLocation->id.'-qr.png"'
        );
    }

    public function test_qr_code_enthaelt_die_lagerplatz_id(): void
    {
        $this->actingAs(User::factory()->create());

        $storageLocation = StorageLocation::factory()->create();

        $response = $this->get(route('storage-locations.qr-code', $storageLocation));

        $reader = new QrReader($response->getContent(), QrReader::SOURCE_TYPE_BLOB);

        $this->assertSame((string) $storageLocation->id, $reader->text());
    }

    public function test_qr_code_ist_fuer_gaeste_nicht_erreichbar(): void
    {
        $storageLocation = StorageLocation::factory()->create();

        $response = $this->get(route('storage-locations.qr-code', $storageLocation));

        $response->assertRedirect(route('login'));
    }
}
