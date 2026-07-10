<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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
            'password' => ['required', 'string', 'min:8'],
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

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
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
            'password' => ['required', 'string', 'min:8'],
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
