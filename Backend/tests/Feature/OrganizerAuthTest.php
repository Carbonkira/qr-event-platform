<?php

namespace Tests\Feature;

use App\Models\Organizer;
use App\Notifications\OrganizerVerifyEmailNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** See ParticipantAuthTest - same shape, this half covers /api/auth/organizer/*. */
class OrganizerAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeUncompromisedPasswordCheck(): void
    {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
    }

    public function test_register_creates_an_unverified_pending_account_and_sends_verification_email(): void
    {
        Notification::fake();
        $this->fakeUncompromisedPasswordCheck();

        $response = $this->postJson('/api/auth/organizer/register', [
            'name' => 'Ana Reyes',
            'email' => 'ana@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()->assertJsonStructure(['user', 'token']);

        $organizer = Organizer::where('email', 'ana@example.com')->first();
        $this->assertNotNull($organizer);
        $this->assertNull($organizer->email_verified_at);
        $this->assertSame('pending', $organizer->approval_status);

        Notification::assertSentTo($organizer, OrganizerVerifyEmailNotification::class);
    }

    public function test_register_rejects_a_password_that_does_not_match_its_confirmation(): void
    {
        $this->fakeUncompromisedPasswordCheck();

        $this->postJson('/api/auth/organizer/register', [
            'name' => 'Ana Reyes', 'email' => 'ana@example.com',
            'password' => 'Password123!', 'password_confirmation' => 'SomethingElse123!',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        $this->assertNull(Organizer::where('email', 'ana@example.com')->first());
    }

    public function test_register_rejects_a_password_that_does_not_meet_strength_requirements(): void
    {
        $this->fakeUncompromisedPasswordCheck();

        $this->postJson('/api/auth/organizer/register', [
            'name' => 'Ana Reyes', 'email' => 'ana@example.com',
            'password' => 'onlylowercase', 'password_confirmation' => 'onlylowercase',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_register_rejects_a_known_breached_password(): void
    {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response("F5F70D47ADC2DB2EB397FBEF5F7BC560E29:5\n", 200)]);

        $this->postJson('/api/auth/organizer/register', [
            'name' => 'Ana Reyes', 'email' => 'ana@example.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_email_verification_notification_is_queued(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new OrganizerVerifyEmailNotification);
    }

    public function test_logging_in_again_revokes_the_previous_session(): void
    {
        $organizer = Organizer::create(['name' => 'Test Organizer', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);

        $this->postJson('/api/auth/organizer/login', ['email' => 'test@example.com', 'password' => 'password123'])->assertOk();
        $this->assertSame(1, $organizer->tokens()->count());
        $firstTokenId = $organizer->tokens()->first()->id;

        $this->postJson('/api/auth/organizer/login', ['email' => 'test@example.com', 'password' => 'password123'])->assertOk();

        $this->assertSame(1, $organizer->tokens()->count());
        $this->assertNotSame($firstTokenId, $organizer->tokens()->first()->id);
    }

    public function test_logout_revokes_every_session_not_just_the_current_one(): void
    {
        $organizer = Organizer::create(['name' => 'Test Organizer', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);
        $tokenA = $organizer->createToken('api')->plainTextToken;
        $organizer->createToken('api');
        $this->assertSame(2, $organizer->tokens()->count());

        $this->withHeader('Authorization', "Bearer {$tokenA}")->postJson('/api/auth/organizer/logout')->assertOk();

        $this->assertSame(0, $organizer->tokens()->count());
    }

    public function test_login_succeeds_with_correct_credentials(): void
    {
        $organizer = Organizer::create(['name' => 'Test Organizer', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);

        $this->postJson('/api/auth/organizer/login', ['email' => 'test@example.com', 'password' => 'password123'])
            ->assertOk()->assertJsonPath('user.id', $organizer->id);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        Organizer::create(['name' => 'Test Organizer', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);

        $this->postJson('/api/auth/organizer/login', ['email' => 'test@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_login_with_an_unregistered_email_reports_the_error_on_the_email_field(): void
    {
        $this->postJson('/api/auth/organizer/login', ['email' => 'nope@example.com', 'password' => 'whatever'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    /**
     * A Participant account's credentials must never work against the
     * organizer login endpoint, even if the same email/password pair
     * happens to exist in both tables - each account type only ever
     * authenticates against its own table (see OrganizerAuthController::login).
     */
    public function test_a_participant_account_cannot_log_in_through_the_organizer_endpoint(): void
    {
        \App\Models\Participant::create(['name' => 'P', 'email' => 'dual@example.com', 'password' => bcrypt('password123')]);

        $this->postJson('/api/auth/organizer/login', ['email' => 'dual@example.com', 'password' => 'password123'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_a_token_older_than_the_configured_expiration_is_rejected(): void
    {
        Organizer::create(['name' => 'Test Organizer', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);

        $login = $this->postJson('/api/auth/organizer/login', ['email' => 'test@example.com', 'password' => 'password123'])->assertOk();
        $token = $login->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/auth/organizer/me')->assertOk();

        \Laravel\Sanctum\PersonalAccessToken::query()->update(['created_at' => now()->subDays(31)]);
        \Illuminate\Support\Facades\Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/auth/organizer/me')->assertUnauthorized();
    }

    public function test_forgot_password_sends_a_reset_link_for_a_known_email(): void
    {
        Notification::fake();
        $organizer = Organizer::create(['name' => 'Test Organizer', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);

        $this->postJson('/api/auth/organizer/forgot-password', ['email' => 'test@example.com'])->assertOk();

        Notification::assertSentTo($organizer, ResetPasswordNotification::class);
    }

    public function test_forgot_password_reports_an_unregistered_email_without_sending_anything(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/organizer/forgot-password', ['email' => 'nobody@example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email']);

        Notification::assertNothingSent();
    }

    public function test_reset_password_with_a_valid_token_changes_the_password_and_revokes_tokens(): void
    {
        $this->fakeUncompromisedPasswordCheck();
        $organizer = Organizer::create(['name' => 'Test Organizer', 'email' => 'test@example.com', 'password' => bcrypt('old-password')]);
        $organizer->createToken('api');
        $this->assertCount(1, $organizer->tokens);

        $resetToken = Password::broker('organizers')->createToken($organizer);

        $this->postJson('/api/auth/organizer/reset-password', [
            'token' => $resetToken, 'email' => 'test@example.com',
            'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $organizer->fresh()->password));
        $this->assertCount(0, $organizer->fresh()->tokens);
    }

    public function test_validate_reset_token_confirms_a_real_unused_token(): void
    {
        $organizer = Organizer::create(['name' => 'Test Organizer', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);
        $resetToken = Password::broker('organizers')->createToken($organizer);

        $this->postJson('/api/auth/organizer/reset-password/validate', ['email' => 'test@example.com', 'token' => $resetToken])
            ->assertOk()->assertJson(['valid' => true]);
    }

    public function test_validate_reset_token_rejects_a_wrong_token_or_unregistered_email(): void
    {
        $organizer = Organizer::create(['name' => 'Test Organizer', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);
        Password::broker('organizers')->createToken($organizer);

        $this->postJson('/api/auth/organizer/reset-password/validate', ['email' => 'test@example.com', 'token' => 'made-up-token'])
            ->assertOk()->assertJson(['valid' => false]);

        $this->postJson('/api/auth/organizer/reset-password/validate', ['email' => 'nobody@example.com', 'token' => 'whatever'])
            ->assertOk()->assertJson(['valid' => false]);
    }

    public function test_update_profile_requires_authentication(): void
    {
        $this->putJson('/api/auth/organizer/me', ['name' => 'New Name'])->assertUnauthorized();
    }

    public function test_can_update_name_and_institution_without_touching_password(): void
    {
        $organizer = Organizer::create(['name' => 'Old Name', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);
        Sanctum::actingAs($organizer);

        $this->putJson('/api/auth/organizer/me', ['name' => 'New Name', 'institution' => 'Ateneo'])
            ->assertOk()
            ->assertJsonPath('name', 'New Name')
            ->assertJsonPath('institution', 'Ateneo');

        $this->assertTrue(Hash::check('password123', $organizer->fresh()->password));
    }

    public function test_upload_avatar_requires_authentication(): void
    {
        $file = \Illuminate\Http\UploadedFile::fake()->image('me.jpg');

        $this->postJson('/api/auth/organizer/me/avatar', ['avatar' => $file])->assertUnauthorized();
    }

    public function test_upload_avatar_requires_an_actual_image(): void
    {
        $organizer = Organizer::create(['name' => 'Test Organizer', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);
        Sanctum::actingAs($organizer);
        $file = \Illuminate\Http\UploadedFile::fake()->create('notes.txt', 10);

        $this->postJson('/api/auth/organizer/me/avatar', ['avatar' => $file])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_upload_avatar_stores_the_file_and_updates_the_organizer(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $organizer = Organizer::create(['name' => 'Test Organizer', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);
        Sanctum::actingAs($organizer);
        $file = \Illuminate\Http\UploadedFile::fake()->image('me.jpg', 400, 400);

        $response = $this->postJson('/api/auth/organizer/me/avatar', ['avatar' => $file])->assertOk();

        $url = $response->json('avatar');
        $this->assertNotEmpty($url);
        $this->assertSame($url, $organizer->fresh()->avatar);
        $storedPath = \Illuminate\Support\Str::after(parse_url($url, PHP_URL_PATH), '/storage/');
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($storedPath);
    }

    public function test_changing_password_requires_the_correct_current_password(): void
    {
        $this->fakeUncompromisedPasswordCheck();
        $organizer = Organizer::create(['name' => 'Test Organizer', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);
        Sanctum::actingAs($organizer);

        $this->putJson('/api/auth/organizer/me', ['current_password' => 'wrong', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['currentPassword']);

        $this->assertTrue(Hash::check('password123', $organizer->fresh()->password));
    }

    public function test_changing_password_with_the_correct_current_password_succeeds(): void
    {
        $this->fakeUncompromisedPasswordCheck();
        $organizer = Organizer::create(['name' => 'Test Organizer', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);
        Sanctum::actingAs($organizer);

        $this->putJson('/api/auth/organizer/me', ['current_password' => 'password123', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])
            ->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $organizer->fresh()->password));
    }

    public function test_changing_email_re_locks_verification_and_queues_a_new_verification_email(): void
    {
        Notification::fake();
        $organizer = Organizer::create(['name' => 'Test Organizer', 'email' => 'old@example.com', 'password' => bcrypt('password123'), 'email_verified_at' => now()]);
        Sanctum::actingAs($organizer);

        $this->putJson('/api/auth/organizer/me', ['email' => 'new@example.com'])->assertOk();

        $organizer->refresh();
        $this->assertSame('new@example.com', $organizer->email);
        $this->assertNull($organizer->email_verified_at);
        Notification::assertSentTo($organizer, OrganizerVerifyEmailNotification::class);
    }

    public function test_cannot_update_email_to_one_already_taken_by_another_organizer_account(): void
    {
        Organizer::create(['name' => 'Other', 'email' => 'taken@example.com', 'password' => bcrypt('password123')]);
        $organizer = Organizer::create(['name' => 'Test Organizer', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);
        Sanctum::actingAs($organizer);

        $this->putJson('/api/auth/organizer/me', ['email' => 'taken@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_email_verification_link_marks_the_account_verified_and_redirects(): void
    {
        $organizer = Organizer::create(['name' => 'Test Organizer', 'email' => 'test@example.com', 'password' => bcrypt('password123')]);
        $url = URL::temporarySignedRoute(
            'organizer.verification.verify',
            now()->addMinutes(60),
            ['id' => $organizer->id, 'hash' => sha1($organizer->email)]
        );

        $response = $this->get($url);

        $response->assertRedirect();
        $this->assertStringContainsString('verified=1', $response->headers->get('Location'));
        $this->assertNotNull($organizer->fresh()->email_verified_at);
    }

    public function test_auth_endpoints_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/organizer/login', ['email' => 'nope@example.com', 'password' => 'wrong']);
        }

        $this->postJson('/api/auth/organizer/login', ['email' => 'nope@example.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }
}
