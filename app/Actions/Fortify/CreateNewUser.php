<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $isFirst = ! User::exists();

        $slugBase = Str::slug(Str::before($input['email'], '@')) ?: 'tenant';
        $slug = $slugBase;
        $suffix = 0;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $slugBase.'-'.(++$suffix);
        }

        $tenant = Tenant::create([
            'name' => $input['name'],
            'slug' => $slug,
        ]);

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'tenant_id' => $tenant->id,
        ]);

        RolesAndPermissionsSeeder::ensureDefaults();

        // İlk kaydolan kullanıcı platformun süper admini olur.
        if ($isFirst) {
            $user->assignRole(RolesAndPermissionsSeeder::ROLE_SUPER_ADMIN);
        } else {
            $user->assignRole(RolesAndPermissionsSeeder::ROLE_TENANT_USER);
        }

        return $user;
    }
}
