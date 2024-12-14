<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class StorageLocation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'shelf_id',
        'qr_code',
        'barcode',
        'notes'
    ];

    public function shelf()
    {
        return $this->belongsTo(Shelf::class, "shelf_id");
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'stocks');
    }
}
