<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
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
        ];
    }

    public function roleNames(): array
    {
        return DB::table('user_roles')
            ->where('user_id', $this->getKey())
            ->pluck('role')
            ->all();
    }

    public function permissions(): array
    {
        $roles = config('rbac.roles');
        $permissions = [];

        foreach ($this->roleNames() as $role) {
            $permissions = array_merge($permissions, $roles[$role]['permissions'] ?? []);
        }

        return in_array('*', $permissions, true)
            ? array_keys(config('rbac.permissions'))
            : array_values(array_unique($permissions));
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roleNames(), true);
    }

    public function assignRoles(array $roles): void
    {
        DB::table('user_roles')->where('user_id', $this->getKey())->delete();

        $now = now();
        $rows = collect($roles)->unique()->map(fn ($role) => [
            'user_id' => $this->getKey(),
            'role' => $role,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        if ($rows !== []) {
            DB::table('user_roles')->insert($rows);
        }
    }
}
