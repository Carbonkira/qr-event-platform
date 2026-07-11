<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_an_unverified_account_and_sends_verification_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ana Reyes',
            'email' => 'ana@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated()->assertJsonStructure(['user', 'token']);

        $user = User::where('email', 'ana@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    /**
     * Regression test: both of these used to send synchronously during the
     * request that triggered them (register/resend-verification,
     * forgot-password) - if the SMTP endpoint is ever slow or unreachable,
     * that hung the request itself instead of just delaying an
     * already-queued job. Confirmed in production, not theoretical.
     */
    public function test_email_verification_and_password_reset_notifications_are_queued(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new VerifyEmailNotification);
        $this->assertInstanceOf(ShouldQueue::class, new ResetPasswordNotification('https://example.com'));
    }

    public function test_logging_in_again_revokes_the_previous_session(): void
    {
        // Checked via the database directly, not by re-authenticating with
        // the old token in the same test: Sanctum's request guard caches
        // its resolved user for the test's lifetime, so a second real HTTP
        // call with a stale token would misleadingly still "work" here
        // regardless of whether the token row was actually deleted.
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);

        $this->postJson('/api/auth/login', ['email' => 'test@example.com', 'password' => 'password123'])->assertOk();
        $this->assertSame(1, $user->tokens()->count());
        $firstTokenId = $user->tokens()->first()->id;

        $this->postJson('/api/auth/login', ['email' => 'test@example.com', 'password' => 'password123'])->assertOk();

        $this->assertSame(1, $user->tokens()->count());
        $this->assertNotSame($firstTokenId, $user->tokens()->first()->id);
    }

    public function test_logout_revokes_every_session_not_just_the_current_one(): void
    {
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);
        // Bypass login's own single-session revocation so there are
        // genuinely two live tokens to prove logout clears both.
        $tokenA = $user->createToken('api')->plainTextToken;
        $user->createToken('api');
        $this->assertSame(2, $user->tokens()->count());

        $this->withHeader('Authorization', "Bearer {$tokenA}")->postJson('/api/auth/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_login_succeeds_with_correct_credentials(): void
    {
        $user = User::create([
            'name' => 'Test User', 'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'test@example.com', 'password' => 'password123',
        ])->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::create(['name' => 'Test User', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);

        $this->postJson('/api/auth/login', [
            'email' => 'test@example.com', 'password' => 'wrong',
        ])->assertUnprocessable();
    }

    public function test_forgot_password_sends_reset_link_and_never_reveals_whether_the_email_exists(): void
    {
        Notification::fake();
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);

        $known = $this->postJson('/api/auth/forgot-password', ['email' => 'test@example.com']);
        $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com']);

        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->json('message'), $unknown->json('message'));
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_reset_password_with_a_valid_token_changes_the_password_and_revokes_tokens(): void
    {
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com', 'password' => bcrypt('old-password')]);
        $token = $user->createToken('api')->plainTextToken;
        $this->assertCount(1, $user->tokens);

        $resetToken = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $resetToken,
            'email' => 'test@example.com',
            'password' => 'new-password123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_update_profile_requires_authentication(): void
    {
        $this->putJson('/api/auth/me', ['name' => 'New Name'])->assertUnauthorized();
    }

    public function test_can_update_name_and_institution_without_touching_password(): void
    {
        $user = User::create(['name' => 'Old Name', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/me', ['name' => 'New Name', 'institution' => 'Ateneo'])
            ->assertOk()
            ->assertJsonPath('name', 'New Name')
            ->assertJsonPath('institution', 'Ateneo');

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    public function test_changing_password_requires_the_correct_current_password(): void
    {
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/me', ['current_password' => 'wrong', 'password' => 'new-password123'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['currentPassword']);

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    public function test_changing_password_with_the_correct_current_password_succeeds(): void
    {
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/me', ['current_password' => 'password123', 'password' => 'new-password123'])
            ->assertOk();

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    public function test_changing_email_re_locks_verification_and_queues_a_new_verification_email(): void
    {
        Notification::fake();
        $user = User::create(['name' => 'Test User', 'email' => 'old@example.com', 'password' => bcrypt('password123'), 'email_verified_at' => now()]);
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/me', ['email' => 'new@example.com'])->assertOk();

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_cannot_update_email_to_one_already_taken_by_another_account(): void
    {
        User::create(['name' => 'Other', 'email' => 'taken@example.com', 'password' => bcrypt('password123')]);
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/me', ['email' => 'taken@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_email_verification_link_marks_the_account_verified_and_redirects(): void
    {
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->get($url);

        $response->assertRedirect();
        $this->assertStringContainsString('verified=1', $response->headers->get('Location'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_auth_endpoints_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'nope@example.com', 'password' => 'wrong']);
        }

        $this->postJson('/api/auth/login', ['email' => 'nope@example.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }
}
