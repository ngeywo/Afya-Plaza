<?php

namespace App\Models;

use App\Enums\AccountState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'avatar',
        'is_active', 'is_verified', 'last_login_at',
        'phone_verified_at', 'account_state',
        'deactivated_at', 'account_rejection_reason',
        'account_rejection_at', 'account_activated_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = [
        'account_state' => AccountState::REGISTERED->value,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'phone_verified_at' => 'datetime',
            'account_state' => AccountState::class,
            'deactivated_at' => 'datetime',
            'account_rejection_at' => 'datetime',
            'account_activated_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'facility_admin')
            ->withPivot('is_primary')->withTimestamps();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function followingDoctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'follows');
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function hasAnyRole(array $slugs): bool
    {
        return $this->roles()->whereIn('slug', $slugs)->exists();
    }

    public function hasPermission(string $slug): bool
    {
        return $this->roles()->whereHas('permissions', fn ($q) => $q->where('slug', $slug))->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    // ─── Phase 23: Account lifecycle ─────────────────────────────────────────────

    public function verificationCodes(): HasMany
    {
        return $this->hasMany(VerificationCode::class);
    }

    public function verificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class);
    }

    public function hasVerifiedPhone(): bool
    {
        return ! is_null($this->phone_verified_at);
    }

    public function hasVerifiedContact(): bool
    {
        return $this->email_verified_at !== null || $this->hasVerifiedPhone();
    }

    public function accountState(): AccountState
    {
        return $this->account_state ?? AccountState::REGISTERED;
    }

    public function canAuthenticate(): bool
    {
        return $this->accountState()->canAuthenticate();
    }

    public function isClosedAccount(): bool
    {
        return $this->accountState()->isClosed();
    }

    public function isDeactivated(): bool
    {
        return $this->account_state === AccountState::DEACTIVATED;
    }

    public function isSuspendedAccount(): bool
    {
        return $this->account_state === AccountState::SUSPENDED;
    }

    public function isDisabledAccount(): bool
    {
        return $this->account_state === AccountState::DISABLED;
    }

    public function transitionTo(AccountState $state): void
    {
        $this->account_state = $state;
        $this->save();
    }
}
