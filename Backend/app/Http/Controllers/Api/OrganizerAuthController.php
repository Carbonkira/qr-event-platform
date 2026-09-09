<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Organizer's half of the split AuthController (see ParticipantAuthController
 * for the other) - same shape, same validation rules, same single-session-
 * per-login policy, just scoped to the organizers table/broker instead of
 * one shared users table. A brand-new organizer account starts
 * approval_status='pending' (the organizers table's own column default -
 * never set explicitly here, see EnsureOrganizerApproved) on top of the
 * existing email-verification requirement.
 */
class OrganizerAuthController extends Controller
{
    private function passwordRules(): array
    {
        return ['confirmed', PasswordRule::min(8)->mixedCase()->numbers()->symbols()->uncompromised()];
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:organizers,email'],
            'password' => array_merge(['required', 'string'], $this->passwordRules()),
            'institution' => ['nullable', 'string', 'max:255'],
        ]);

        $organizer = Organizer::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'institution' => $data['institution'] ?? null,
        ]);

        $organizer->sendEmailVerificationNotification();

        $token = $organizer->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $organizer,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $organizer = Organizer::where('email', $data['email'])->first();

        if (! $organizer) {
            throw ValidationException::withMessages([
                'email' => ['No organizer account found with that email.'],
            ]);
        }

        if (! Hash::check($data['password'], $organizer->password)) {
            throw ValidationException::withMessages([
                'password' => ['Password incorrect.'],
            ]);
        }

        $organizer->tokens()->delete();

        $token = $organizer->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $organizer,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $organizer = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('organizers', 'email')->ignore($organizer->id)],
            'institution' => ['sometimes', 'nullable', 'string', 'max:255'],
            'current_password' => ['required_with:password', 'string'],
            'password' => array_merge(['sometimes', 'string'], $this->passwordRules()),
        ]);

        if (array_key_exists('password', $data)) {
            if (! Hash::check($data['current_password'], $organizer->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['The current password is incorrect.'],
                ]);
            }
            $organizer->password = Hash::make($data['password']);
        }

        if (array_key_exists('name', $data)) {
            $organizer->name = $data['name'];
        }
        if (array_key_exists('institution', $data)) {
            $organizer->institution = $data['institution'];
        }

        $emailChanged = array_key_exists('email', $data) && $data['email'] !== $organizer->email;
        if ($emailChanged) {
            $organizer->email = $data['email'];
            $organizer->email_verified_at = null;
        }

        $organizer->save();

        if ($emailChanged) {
            $organizer->sendEmailVerificationNotification();
        }

        return response()->json($organizer);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:5120'],
        ]);

        $organizer = $request->user();
        $path = $request->file('avatar')->store('avatars', 'public');
        $organizer->avatar = Storage::disk('public')->url($path);
        $organizer->save();

        return response()->json($organizer);
    }

    public function verify(Request $request, string $id)
    {
        $organizer = Organizer::findOrFail($id);
        $frontend = rtrim(config('services.frontend.url'), '/');

        if (! hash_equals((string) $request->route('hash'), sha1($organizer->getEmailForVerification()))) {
            return redirect("{$frontend}/email-verified?verified=0");
        }

        if (! $organizer->hasVerifiedEmail()) {
            $organizer->markEmailAsVerified();
            event(new Verified($organizer));
        }

        return redirect("{$frontend}/email-verified?verified=1");
    }

    public function resendVerification(Request $request)
    {
        $organizer = $request->user();

        if ($organizer->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $organizer->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.']);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $organizer = Organizer::where('email', $data['email'])->first();

        if (! $organizer) {
            throw ValidationException::withMessages([
                'email' => ['No organizer account found with that email.'],
            ]);
        }

        Password::broker('organizers')->sendResetLink(['email' => $data['email']]);

        return response()->json(['message' => 'Reset link sent - check your inbox.']);
    }

    public function validateResetToken(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'token' => ['required', 'string'],
        ]);

        $organizer = Organizer::where('email', $data['email'])->first();
        $valid = $organizer && Password::broker('organizers')->tokenExists($organizer, $data['token']);

        return response()->json(['valid' => $valid]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => array_merge(['required', 'string'], $this->passwordRules()),
        ]);

        $status = Password::broker('organizers')->reset(
            $data,
            function (Organizer $organizer, string $password) {
                $organizer->forceFill(['password' => Hash::make($password)])->save();
                $organizer->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Password reset. Please log in with your new password.']);
    }
}
