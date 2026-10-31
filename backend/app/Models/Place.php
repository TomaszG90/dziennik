<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Place extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'name',
        'latitude',
        'longitude',
        'position',
        'country_code',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'position' => 'integer',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function outgoingLegs(): HasMany
    {
        return $this->hasMany(Leg::class, 'from_place_id');
    }

    public function incomingLegs(): HasMany
    {
        return $this->hasMany(Leg::class, 'to_place_id');
    }
}
