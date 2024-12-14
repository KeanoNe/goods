<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'from_storage_location_id',
        'to_storage_location_id',
        'quantity',
        'type', // 'in', 'out', 'transfer', 'correction'
        'notes',
        'user_id'
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
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
}
