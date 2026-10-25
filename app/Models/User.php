<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use App\Modules\Platform\Models\Access\AccessAssignment;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

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

    /**
     * The client company a client account belongs to (null for the Baydaq team).
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Suite roles this user holds, each at one point of the client's tree.
     *
     * @return HasMany<AccessAssignment, $this>
     */
    public function accessAssignments(): HasMany
    {
        return $this->hasMany(AccessAssignment::class);
    }

    public function preferredLocale(): string
    {
        return $this->locale;
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole([Role::SystemAdmin, Role::Admin]);
    }

    /**
     * A team member holding any of the given permissions (the system admin holds them all).
     */
    public function hasAdminPermission(Permission ...$permissions): bool
    {
        if (! $this->isAdmin()) {
            return false;
        }

        if ($this->isSystemAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($this->checkPermissionTo($permission->value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Team members holding any of the given permissions, the system admin included.
     *
     * @return Collection<int, User>
     */
    public static function teamHolding(Permission ...$permissions): Collection
    {
        $names = array_map(fn (Permission $permission): string => $permission->value, $permissions);
        $holds = fn (Builder $query) => $query->whereIn('name', $names);

        return static::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', Role::SystemAdmin->value))
            ->orWhereHas('permissions', $holds)
            ->orWhereHas('roles.permissions', $holds)
            ->get();
    }

    /**
     * The client accounts (not suite employees) of a client.
     *
     * @return Collection<int, User>
     */
    public static function clientAccountsOf(int $clientId): Collection
    {
        return static::query()->where('client_id', $clientId)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', Role::Client->value))
            ->get();
    }

    public function isSystemAdmin(): bool
    {
        return $this->hasRole(Role::SystemAdmin);
    }

    /**
     * Where the user lands after signing in: the admin for the team, the portal for client
     * accounts (owners see Build and Business there), the suite for everyone else (employees).
     */
    public function homeUrl(): string
    {
        if ($this->isAdmin()) {
            return route('admin.dashboard');
        }

        if ($this->hasRole(Role::Client)) {
            return route('portal.home');
        }

        // People with a role in the suite land there; employees without one get their own page.
        return $this->accessAssignments()->exists() ? route('suite.entry') : route('me.home');
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
