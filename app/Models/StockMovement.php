<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'supplier_id',
        'from_storage_location_id',
        'to_storage_location_id',
        'quantity',
        'unit_price',
        'type', // 'in', 'out', 'transfer', 'correction'
        'notes',
        'user_id',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function fromStorageLocation()
    {
        return $this->belongsTo(StorageLocation::class, 'from_storage_location_id');
    }

    public function toStorageLocation()
    {
        return $this->belongsTo(StorageLocation::class, 'to_storage_location_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Netto-Bestand je Artikel laut Bewegungsjournal.
     *
     * Ein transfer verschiebt nur zwischen Lagerplätzen und verändert den
     * Artikelbestand nicht. Korrekturen zählen mit, anders als in der
     * Bilanz: hier geht es darum, ob Journal und Bestand zusammenpassen,
     * nicht um den Warenfluss eines Zeitraums.
     *
     * @return Collection<int, int> Schlüssel ist die article_id
     */
    public static function journalstaende(): Collection
    {
        return DB::table('stock_movements')
            ->selectRaw('article_id')
            ->selectRaw("sum(case
                when type = 'in' then quantity
                when type = 'out' then -quantity
                when type = 'correction' and to_storage_location_id is not null then quantity
                when type = 'correction' and from_storage_location_id is not null then -quantity
                else 0 end) as netto")
            ->groupBy('article_id')
            ->pluck('netto', 'article_id');
    }
}
