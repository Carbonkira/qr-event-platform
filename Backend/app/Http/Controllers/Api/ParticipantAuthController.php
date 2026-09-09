<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Participant;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * See OrganizerAuthController - same shape, scoped to the participants
 * table/broker instead. This is what backs Register.jsx's embedded
 * "create account" / "log in" step - registering for an event and creating
 * a participant account are two sides of the same flow, kept inline rather
 * than forcing a separate signup page first (explicit choice, kept as-is
 * from before the split).
 */
class ParticipantAuthController extends Controller
{
    private function passwordRules(): array
    {
        return ['confirmed', PasswordRule::min(8)->mixedCase()->numbers()->symbols()->uncompromised()];
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:participants,email'],
            'password' => array_merge(['required', 'string'], $this->passwordRules()),
            'institution' => ['nullable', 'string', 'max:255'],
        ]);

        $participant = Participant::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'institution' => $data['institution'] ?? null,
        ]);

        $participant->sendEmailVerificationNotification();

        $token = $participant->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $participant,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $participant = Participant::where('email', $data['email'])->first();

        if (! $participant) {
            throw ValidationException::withMessages([
                'email' => ['No account found with that email.'],
            ]);
        }

        if (! Hash::check($data['password'], $participant->password)) {
            throw ValidationException::withMessages([
                'password' => ['Password incorrect.'],
            ]);
        }

        $participant->tokens()->delete();

        $token = $participant->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $participant,
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
        $participant = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('participants', 'email')->ignore($participant->id)],
            'institution' => ['sometimes', 'nullable', 'string', 'max:255'],
            'current_password' => ['required_with:password', 'string'],
            'password' => array_merge(['sometimes', 'string'], $this->passwordRules()),
        ]);

        if (array_key_exists('password', $data)) {
            if (! Hash::check($data['current_password'], $participant->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['The current password is incorrect.'],
                ]);
            }
            $participant->password = Hash::make($data['password']);
        }

        if (array_key_exists('name', $data)) {
            $participant->name = $data['name'];
        }
        if (array_key_exists('institution', $data)) {
            $participant->institution = $data['institution'];
        }

        $emailChanged = array_key_exists('email', $data) && $data['email'] !== $participant->email;
        if ($emailChanged) {
            $participant->email = $data['email'];
            $participant->email_verified_at = null;
        }

        $participant->save();

        if ($emailChanged) {
            $participant->sendEmailVerificationNotification();
        }

        return response()->json($participant);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:5120'],
        ]);

        $participant = $request->user();
        $path = $request->file('avatar')->store('avatars', 'public');
        $participant->avatar = Storage::disk('public')->url($path);
        $participant->save();

        return response()->json($participant);
    }

    public function verify(Request $request, string $id)
    {
        $participant = Participant::findOrFail($id);
        $frontend = rtrim(config('services.frontend.url'), '/');

        if (! hash_equals((string) $request->route('hash'), sha1($participant->getEmailForVerification()))) {
            return redirect("{$frontend}/email-verified?verified=0");
        }

        if (! $participant->hasVerifiedEmail()) {
            $participant->markEmailAsVerified();
            event(new Verified($participant));
        }

        return redirect("{$frontend}/email-verified?verified=1");
    }

    public function resendVerification(Request $request)
    {
        $participant = $request->user();

        if ($participant->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $participant->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.']);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $participant = Participant::where('email', $data['email'])->first();

        if (! $participant) {
            throw ValidationException::withMessages([
                'email' => ['No account found with that email.'],
            ]);
        }

        Password::broker('participants')->sendResetLink(['email' => $data['email']]);

        return response()->json(['message' => 'Reset link sent - check your inbox.']);
    }

    public function validateResetToken(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'token' => ['required', 'string'],
        ]);

        $participant = Participant::where('email', $data['email'])->first();
        $valid = $participant && Password::broker('participants')->tokenExists($participant, $data['token']);

        return response()->json(['valid' => $valid]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => array_merge(['required', 'string'], $this->passwordRules()),
        ]);

        $status = Password::broker('participants')->reset(
            $data,
            function (Participant $participant, string $password) {
                $participant->forceFill(['password' => Hash::make($password)])->save();
                $participant->tokens()->delete();
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
