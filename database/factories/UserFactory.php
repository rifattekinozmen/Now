<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Lojistik admin izni olmadan kullanıcı (middleware testleri için).
     */
    public function withoutLogisticsRole(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->syncRoles([]);
        });
    }

    /**
     * Sadece `logistics.view` — yazma ve toplu işlem yok.
     */
    public function logisticsViewer(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->syncRoles([]);
            RolesAndPermissionsSeeder::ensureDefaults();
            $role = Role::query()->where('name', RolesAndPermissionsSeeder::ROLE_LOGISTICS_VIEWER)->first();
            if ($role === null) {
                return;
            }
            $user->assignRole($role);
            $user->forgetCachedPermissions();
        });
    }

    /**
     * `logistics.view` + `logistics.orders.write` — ince izin senaryoları için.
     */
    public function logisticsOrderClerk(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->syncRoles([]);
            RolesAndPermissionsSeeder::ensureDefaults();
            $role = Role::query()->where('name', RolesAndPermissionsSeeder::ROLE_LOGISTICS_ORDER_CLERK)->first();
            if ($role === null) {
                return;
            }
            $user->assignRole($role);
            $user->forgetCachedPermissions();
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->hasRole(RolesAndPermissionsSeeder::ROLE_LOGISTICS_VIEWER)) {
                return;
            }

            if ($user->hasRole(RolesAndPermissionsSeeder::ROLE_LOGISTICS_ORDER_CLERK)) {
                return;
            }

            if (! Role::query()->where('name', RolesAndPermissionsSeeder::ROLE_TENANT_USER)->exists()) {
                return;
            }

            if (! $user->hasRole(RolesAndPermissionsSeeder::ROLE_TENANT_USER)) {
                $user->assignRole(RolesAndPermissionsSeeder::ROLE_TENANT_USER);
            }
        });
    }
}
