<?php

namespace Tests\Feature\Seeders;

use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A real user must never see lorem-ipsum demo content. EmployerSeeder,
 * CandidateSeeder and ApplicationSeeder generate exactly that (fake
 * companies, jobs, bios, cover letters) for local demoing, so
 * DatabaseSeeder must refuse to run them outside local/testing.
 * AdminSeeder always runs (every environment needs an admin account) but
 * must never ship a guessable password in production.
 */
class DemoDataSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_is_seeded_in_the_testing_environment(): void
    {
        // phpunit.xml sets APP_ENV=testing, so no override needed here.
        $this->seed();

        $this->assertTrue(Company::query()->exists());
        $this->assertTrue(Job::query()->exists());
        $this->assertTrue(User::where('role', 'candidate')->exists());
        $this->assertTrue(Application::query()->exists());
    }

    public function test_demo_data_is_skipped_in_production_but_the_admin_account_still_seeds(): void
    {
        $this->app['env'] = 'production';

        // db:seed is a "destructive" command Laravel guards with a
        // confirmation prompt in production — --force bypasses it, exactly
        // as a real deploy would.
        $this->artisan('db:seed', ['--force' => true]);

        $this->assertFalse(Company::query()->exists(), 'No demo companies outside local/testing.');
        $this->assertFalse(Job::query()->exists(), 'No demo jobs outside local/testing.');
        $this->assertFalse(User::where('role', 'candidate')->exists(), 'No demo candidates outside local/testing.');
        $this->assertFalse(Application::query()->exists(), 'No demo applications outside local/testing.');

        $this->assertTrue(User::where('role', 'admin')->exists(), 'The admin account must still be created.');
    }

    public function test_admin_password_defaults_to_the_documented_demo_password_in_local_and_testing(): void
    {
        $this->seed(\Database\Seeders\AdminSeeder::class);

        $admin = User::where('email', 'admin@larajob.test')->firstOrFail();

        $this->assertTrue(Hash::check('password', $admin->password));
    }

    public function test_admin_password_is_randomly_generated_in_production_when_not_configured(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', ['--class' => \Database\Seeders\AdminSeeder::class, '--force' => true]);

        $admin = User::where('email', 'admin@larajob.test')->firstOrFail();

        $this->assertFalse(Hash::check('password', $admin->password), 'Production must never fall back to the known demo password.');
    }

    public function test_admin_email_and_password_are_configurable_via_env(): void
    {
        $this->app['env'] = 'production';
        config(['admin.email' => 'owner@example.com', 'admin.password' => 'a-strong-real-password']);

        $this->artisan('db:seed', ['--class' => \Database\Seeders\AdminSeeder::class, '--force' => true]);

        $admin = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('a-strong-real-password', $admin->password));
    }

    public function test_reseeding_does_not_reset_an_existing_admins_password(): void
    {
        $this->seed(\Database\Seeders\AdminSeeder::class);

        $admin = User::where('email', 'admin@larajob.test')->firstOrFail();
        $admin->forceFill(['password' => Hash::make('a-changed-password')])->save();

        // Running the seeder again must not clobber the changed password.
        $this->seed(\Database\Seeders\AdminSeeder::class);

        $admin->refresh();
        $this->assertTrue(Hash::check('a-changed-password', $admin->password));
    }
}
