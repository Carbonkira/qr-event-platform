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
