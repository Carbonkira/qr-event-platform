<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Shared everywhere a password is actually being set (register, reset,
     * change) - `confirmed` expects a matching `password_confirmation`
     * field (the frontend sends passwordConfirmation, camelCased back to
     * snake_case by ConvertCamelCaseRequestToSnakeCase). uncompromised()
     * checks the password against known-breached lists via the HaveIBeenPwned
     * API (k-anonymity, the real password is never sent).
     */
    private function passwordRules(): array
    {
        return ['confirmed', PasswordRule::min(8)->mixedCase()->numbers()->symbols()->uncompromised()];
    }

    /**
     * Create an account. There's only one account type - the same user can
     * go on to organize events and register for other people's events (see
     * User::events()/registrations()), so this endpoint serves both the
     * "become an organizer" entry point and the account-creation step of
     * event registration (see RegistrationController::store).
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => array_merge(['required', 'string'], $this->passwordRules()),
            'institution' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'institution' => $data['institution'] ?? null,
        ]);

        $user->sendEmailVerificationNotification();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Only one active session per account - logging in somewhere new
        // signs out every other device/browser this account was logged
        // into, rather than letting tokens pile up indefinitely.
        $user->tokens()->delete();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Deletes every token for this account, not just the one used to make
     * this request - logging out from one place signs out everywhere,
     * consistent with the single-active-session policy in login().
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Lets an account edit its own name/email/institution and, optionally,
     * its password (current_password required to change it - not to save
     * the rest of the form, so touching your name doesn't force you to
     * re-type your password). Changing the email re-locks verification,
     * matching how a brand-new account starts out unverified, and queues a
     * fresh verification email to the new address.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'institution' => ['sometimes', 'nullable', 'string', 'max:255'],
            'current_password' => ['required_with:password', 'string'],
            'password' => array_merge(['sometimes', 'string'], $this->passwordRules()),
        ]);

        if (array_key_exists('password', $data)) {
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['The current password is incorrect.'],
                ]);
            }
            $user->password = Hash::make($data['password']);
        }

        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }
        if (array_key_exists('institution', $data)) {
            $user->institution = $data['institution'];
        }

        $emailChanged = array_key_exists('email', $data) && $data['email'] !== $user->email;
        if ($emailChanged) {
            $user->email = $data['email'];
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json($user);
    }

    /**
     * Same store-and-return-url pattern as EventController::uploadImage.
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:5120'], // 5MB, matches other image uploads
        ]);

        $user = $request->user();
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->avatar = Storage::disk('public')->url($path);
        $user->save();

        return response()->json($user);
    }

    /**
     * The link a verification email points at (Illuminate\Auth\Notifications\
     * VerifyEmail builds this URL via the named route below, signed and
     * carrying {id}/{hash}). Reached directly by the browser when the user
     * clicks the email, so it redirects to the SPA rather than returning JSON.
     */
    public function verify(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $frontend = rtrim(config('services.frontend.url'), '/');

        if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            return redirect("{$frontend}/email-verified?verified=0");
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect("{$frontend}/email-verified?verified=1");
    }

    public function resendVerification(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.']);
    }

    /**
     * Always responds the same way regardless of whether the email exists,
     * so this can't be used to enumerate registered accounts.
     */
    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::broker()->sendResetLink(['email' => $data['email']]);

        return response()->json(['message' => 'If an account exists for that email, a reset link has been sent.']);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => array_merge(['required', 'string'], $this->passwordRules()),
        ]);

        $status = Password::broker()->reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                // A password reset is a good reason to invalidate any tokens
                // issued before the account may have been compromised.
                $user->tokens()->delete();
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
