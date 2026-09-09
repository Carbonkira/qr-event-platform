<?php

namespace Tests\Feature;

use App\Models\Organizer;
use App\Models\Participant;
use App\Notifications\ParticipantVerifyEmailNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * See OrganizerAuthTest - same shape and same underlying controller logic
 * (ParticipantAuthController mirrors OrganizerAuthController exactly, just
 * scoped to the participants table/broker), covering the core scenarios
 * rather than every single case again.
 */
class ParticipantAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeUncompromisedPasswordCheck(): void
    {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
    }

    public function test_register_creates_an_unverified_account_and_sends_verification_email(): void
    {
        Notification::fake();
        $this->fakeUncompromisedPasswordCheck();

        $response = $this->postJson('/api/auth/participant/register', [
            'name' => 'Juan Dela Cruz', 'email' => 'juan@example.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()->assertJsonStructure(['user', 'token']);

        $participant = Participant::where('email', 'juan@example.com')->first();
        $this->assertNotNull($participant);
        $this->assertNull($participant->email_verified_at);

        Notification::assertSentTo($participant, ParticipantVerifyEmailNotification::class);
    }

    public function test_register_rejects_a_password_that_does_not_meet_strength_requirements(): void
    {
        $this->fakeUncompromisedPasswordCheck();

        $this->postJson('/api/auth/participant/register', [
            'name' => 'Juan', 'email' => 'juan@example.com',
            'password' => 'onlylowercase', 'password_confirmation' => 'onlylowercase',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_login_succeeds_with_correct_credentials(): void
    {
        $participant = Participant::create(['name' => 'Juan', 'email' => 'juan@example.com', 'password' => bcrypt('password123')]);

        $this->postJson('/api/auth/participant/login', ['email' => 'juan@example.com', 'password' => 'password123'])
            ->assertOk()->assertJsonPath('user.id', $participant->id);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        Participant::create(['name' => 'Juan', 'email' => 'juan@example.com', 'password' => bcrypt('password123')]);

        $this->postJson('/api/auth/participant/login', ['email' => 'juan@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    /**
     * Same id can validly exist in both tables at once (see
     * SplitUsersIntoAccounts) - an Organizer's credentials must still never
     * work against the participant login endpoint.
     */
    public function test_an_organizer_account_cannot_log_in_through_the_participant_endpoint(): void
    {
        Organizer::create(['name' => 'O', 'email' => 'dual@example.com', 'password' => bcrypt('password123')]);

        $this->postJson('/api/auth/participant/login', ['email' => 'dual@example.com', 'password' => 'password123'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_logging_in_again_revokes_the_previous_session(): void
    {
        $participant = Participant::create(['name' => 'Juan', 'email' => 'juan@example.com', 'password' => bcrypt('password123')]);

        $this->postJson('/api/auth/participant/login', ['email' => 'juan@example.com', 'password' => 'password123'])->assertOk();
        $firstTokenId = $participant->tokens()->first()->id;

        $this->postJson('/api/auth/participant/login', ['email' => 'juan@example.com', 'password' => 'password123'])->assertOk();

        $this->assertSame(1, $participant->tokens()->count());
        $this->assertNotSame($firstTokenId, $participant->tokens()->first()->id);
    }

    public function test_logout_revokes_the_session(): void
    {
        $participant = Participant::create(['name' => 'Juan', 'email' => 'juan@example.com', 'password' => bcrypt('password123')]);
        $token = $participant->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/auth/participant/logout')->assertOk();

        $this->assertSame(0, $participant->tokens()->count());
    }

    public function test_update_profile_requires_authentication(): void
    {
        $this->putJson('/api/auth/participant/me', ['name' => 'New Name'])->assertUnauthorized();
    }

    public function test_can_update_name_and_institution(): void
    {
        $participant = Participant::create(['name' => 'Old Name', 'email' => 'juan@example.com', 'password' => bcrypt('password123')]);
        Sanctum::actingAs($participant);

        $this->putJson('/api/auth/participant/me', ['name' => 'New Name', 'institution' => 'Ateneo'])
            ->assertOk()
            ->assertJsonPath('name', 'New Name')
            ->assertJsonPath('institution', 'Ateneo');
    }

    public function test_cannot_update_email_to_one_already_taken_by_another_participant_account(): void
    {
        Participant::create(['name' => 'Other', 'email' => 'taken@example.com', 'password' => bcrypt('password123')]);
        $participant = Participant::create(['name' => 'Juan', 'email' => 'juan@example.com', 'password' => bcrypt('password123')]);
        Sanctum::actingAs($participant);

        $this->putJson('/api/auth/participant/me', ['email' => 'taken@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * The same email is allowed to exist in *both* tables at once (a dual
     * organizer+participant account, see SplitUsersIntoAccounts) - only
     * uniqueness *within* participants is enforced.
     */
    public function test_can_update_email_to_one_already_taken_by_an_organizer_account(): void
    {
        Organizer::create(['name' => 'O', 'email' => 'taken@example.com', 'password' => bcrypt('password123')]);
        $participant = Participant::create(['name' => 'Juan', 'email' => 'juan@example.com', 'password' => bcrypt('password123')]);
        Sanctum::actingAs($participant);

        $this->putJson('/api/auth/participant/me', ['email' => 'taken@example.com'])->assertOk();
    }

    public function test_email_verification_link_marks_the_account_verified_and_redirects(): void
    {
        $participant = Participant::create(['name' => 'Juan', 'email' => 'juan@example.com', 'password' => bcrypt('password123')]);
        $url = URL::temporarySignedRoute(
            'participant.verification.verify',
            now()->addMinutes(60),
            ['id' => $participant->id, 'hash' => sha1($participant->email)]
        );

        $response = $this->get($url);

        $response->assertRedirect();
        $this->assertStringContainsString('verified=1', $response->headers->get('Location'));
        $this->assertNotNull($participant->fresh()->email_verified_at);
    }

    public function test_forgot_password_sends_a_reset_link_for_a_known_email(): void
    {
        Notification::fake();
        $participant = Participant::create(['name' => 'Juan', 'email' => 'juan@example.com', 'password' => bcrypt('password123')]);

        $this->postJson('/api/auth/participant/forgot-password', ['email' => 'juan@example.com'])->assertOk();

        Notification::assertSentTo($participant, ResetPasswordNotification::class);
    }

    public function test_reset_password_with_a_valid_token_changes_the_password_and_revokes_tokens(): void
    {
        $this->fakeUncompromisedPasswordCheck();
        $participant = Participant::create(['name' => 'Juan', 'email' => 'juan@example.com', 'password' => bcrypt('old-password')]);
        $participant->createToken('api');

        $resetToken = Password::broker('participants')->createToken($participant);

        $this->postJson('/api/auth/participant/reset-password', [
            'token' => $resetToken, 'email' => 'juan@example.com',
            'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $participant->fresh()->password));
        $this->assertCount(0, $participant->fresh()->tokens);
    }
}
