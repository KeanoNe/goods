<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rack extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'warehouse_id',
        'notes'
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function shelves()
    {
        return $this->hasMany(Shelf::class);
    }

    public function storageLocations()
    {
        return $this->hasManyThrough(StorageLocation::class, Shelf::class);
    }
}
