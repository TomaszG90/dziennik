<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leg extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'from_place_id',
        'to_place_id',
        'transport',
        'distance',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'distance' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function fromPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'from_place_id');
    }

    public function toPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'to_place_id');
    }
}
