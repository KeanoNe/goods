<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shelf extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'rack_id',
        'notes'
    ];

    public function rack()
    {
        return $this->belongsTo(Rack::class);
    }

    public function storageLocations()
    {
        return $this->hasMany(StorageLocation::class);
    }
}
