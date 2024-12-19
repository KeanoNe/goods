<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $appends = ['total_quantity'];

    protected $fillable = [
        'name',
        'description',
        'sku',
        'minimum_stock',
        'barcode',
        'qr_code',
        'notes'
    ];

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getTotalQuantityAttribute()
    {
        return $this->stocks()->sum('quantity');
    }
}
