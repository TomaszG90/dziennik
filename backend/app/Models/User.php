<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    public const DEFAULT_PRIVACY_SETTINGS = [
        'name' => false,
        'email' => false,
        'firstName' => false,
        'lastName' => false,
        'city' => false,
        'birthDate' => false,
        'avatar' => false,
    ];

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_visibility',
        'first_name',
        'last_name',
        'city',
        'birth_date',
        'avatar_path',
        'privacy_settings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'profile_visibility' => 'string',
            'birth_date' => 'date:Y-m-d',
            'privacy_settings' => 'array',
        ];
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function sentFriendRequests(): HasMany
    {
        return $this->hasMany(FriendRequest::class, 'sender_id');
    }

    public function receivedFriendRequests(): HasMany
    {
        return $this->hasMany(FriendRequest::class, 'recipient_id');
    }

    public function friends(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friend_requests', 'sender_id', 'recipient_id')
            ->where('status', 'accepted')
            ->orWherePivot('recipient_id', $this->id)
            ->wherePivot('status', 'accepted');
    }

    public function guestTrips()
    {
        return Trip::whereHas('invitedGuests', function ($query) {
            $query->where('invitee_id', $this->id)->where('status', 'accepted');
        })->orderBy('start_date');
    }
}
