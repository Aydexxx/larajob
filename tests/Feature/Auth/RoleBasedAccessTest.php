<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registration role assignment, role-based login redirects, and the
 * `role` middleware boundaries that keep each area locked to its audience.
 */
class RoleBasedAccessTest extends TestCase
{
    use RefreshDatabase;

    // --- Registration with role ---

    public function test_a_candidate_can_register_and_is_redirected_to_the_candidate_dashboard(): void
    {
        $response = $this->post('/register', [
            'name' => 'Casey Candidate',
            'email' => 'casey@example.com',
            'role' => 'candidate',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('candidate.dashboard', absolute: false));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'casey@example.com',
            'role' => 'candidate',
        ]);
    }

    public function test_an_employer_can_register_and_is_redirected_to_the_employer_dashboard(): void
    {
        $response = $this->post('/register', [
            'name' => 'Erin Employer',
            'email' => 'erin@example.com',
            'role' => 'employer',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('employer.dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'email' => 'erin@example.com',
            'role' => 'employer',
        ]);
    }

    public function test_registration_rejects_a_privileged_or_unknown_role(): void
    {
        // Visitors must not be able to self-assign the admin role.
        $response = $this->post('/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'role' => 'admin',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    // --- Login redirects by role ---

    public function test_login_redirects_each_role_to_its_own_dashboard(): void
    {
        $cases = [
            'candidate' => route('candidate.dashboard', absolute: false),
            'employer' => route('employer.dashboard', absolute: false),
            'admin' => route('admin.dashboard', absolute: false),
        ];

        foreach ($cases as $role => $expected) {
            $user = User::factory()->state(['role' => $role])->create();

            $response = $this->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

            $response->assertRedirect($expected);
            $this->post('/logout');
        }
    }

    // --- Role middleware boundaries ---

    public function test_a_candidate_cannot_access_employer_or_admin_areas(): void
    {
        $candidate = User::factory()->candidate()->create();

        $this->actingAs($candidate)->get('/employer/dashboard')->assertForbidden();
        $this->actingAs($candidate)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_an_employer_cannot_access_candidate_or_admin_areas(): void
    {
        $employer = User::factory()->employer()->create();

        $this->actingAs($employer)->get('/candidate/dashboard')->assertForbidden();
        $this->actingAs($employer)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_an_admin_can_reach_the_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
    }

    public function test_guests_are_redirected_to_login_from_protected_areas(): void
    {
        $this->get('/candidate/dashboard')->assertRedirect(route('login'));
        $this->get('/employer/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }
}
