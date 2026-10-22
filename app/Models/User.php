<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Defaults matching the database, so a freshly created model has them loaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'must_change_password' => false,
        'locale' => 'ar',
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
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Emails are stored lowercase so sign-in (which lowercases what is typed) always matches.
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => mb_strtolower(trim($value)));
    }

    public function preferredLocale(): string
    {
        return $this->locale;
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole([Role::SystemAdmin, Role::Admin]);
    }

    public function isSystemAdmin(): bool
    {
        return $this->hasRole(Role::SystemAdmin);
    }

    /**
     * Where the user lands after signing in: the admin for the team, the portal for clients.
     */
    public function homeUrl(): string
    {
        return $this->isAdmin() ? route('admin.dashboard') : route('portal.home');
    }

    /**
     * Permission names for the UI. The system admin passes every check, so they get the full list.
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        if ($this->isSystemAdmin()) {
            return Permission::values();
        }

        return $this->getAllPermissions()->pluck('name')->values()->all();
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
