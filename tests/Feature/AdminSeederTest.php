<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_updates_the_configured_super_admin_idempotently(): void
    {
        config([
            'super_admin.name' => 'Super Administrateur',
            'super_admin.email' => 'admin@example.ci',
            'super_admin.password' => 'mot-de-passe-securise',
        ]);

        $seeder = new DatabaseSeeder;
        $seeder->run();
        $seeder->run();

        $this->assertDatabaseCount('users', 1);

        $user = User::where('email', 'admin@example.ci')->firstOrFail();
        $this->assertSame(['admin'], $user->roleNames());
        $this->assertTrue(Hash::check('mot-de-passe-securise', $user->password));
    }
}
