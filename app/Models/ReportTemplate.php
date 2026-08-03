<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReportTemplate extends Model
{
    protected $fillable = [
        'user_id', 'property_id', 'name', 'description', 'is_shared', 'config',
    ];

    protected $casts = [
        'is_shared' => 'boolean',
        'config' => 'array',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function property() { return $this->belongsTo(Property::class); }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere(function (Builder $q2) use ($user) {
                    $q2->where('is_shared', true);
                    // Superusers aren't tied to a single property (they switch
                    // property context via session), so any shared template
                    // should be visible to them regardless of which property
                    // it was created under.
                    if ($user->role !== 'superuser') {
                        $q2->where(function (Builder $q3) use ($user) {
                            $q3->whereNull('property_id')->orWhere('property_id', $user->property_id);
                        });
                    }
                });
        });
    }
}
