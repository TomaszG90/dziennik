<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array{
 *     type: 'LineString',
 *     coordinates: list<array{0: float, 1: float}>
 * }|null $geometry
 */
class Leg extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'from_place_id',
        'to_place_id',
        'transport',
        'distance',
        'geometry',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'distance' => 'decimal:2',
            'geometry' => 'array',
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
