<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'bio', 'password', 'role', 'property_id', 'requested_by_user_id', 'status', 'must_change_password', 'permissions', 'avatar', 'last_seen_at', 'approval_requested_at', 'approval_decided_at', 'approval_note'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at'      => 'datetime',
            'approval_requested_at' => 'datetime',
            'approval_decided_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'requested_by_user_id');
    }

    public function requestedUsers(): HasMany
    {
        return $this->hasMany(self::class, 'requested_by_user_id');
    }

    public function isSuperuser(): bool
    {
        return $this->role === 'superuser';
    }
}
