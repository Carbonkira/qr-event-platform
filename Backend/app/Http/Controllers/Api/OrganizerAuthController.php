<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ApplicationReceivedMail;
use App\Mail\NewOrganizerApplicationMail;
use App\Models\Organizer;
use App\Services\AdminContact;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
    /**
     * The acting organizer's own account, as every auth response returns it.
     * Someone still waiting on the admin (or turned down) also gets the number
     * to reach them on - the same one the application emails carry - so the app
     * can say who to contact right next to their status. It's attached here,
     * to their own responses only, and not to the model, so it can't leak
     * through the relations that serialize an Organizer for other people.
     */
    private function accountPayload(Organizer $organizer): array
    {
        $data = $organizer->toArray();

        if (! $organizer->isAdmin() && ! $organizer->isOrganizerApproved()) {
            $data['admin_contact_number'] = AdminContact::number();
        }

        return $data;
    }

    private function passwordRules(): array
    {
        return ['confirmed', PasswordRule::min(8)->mixedCase()->numbers()->symbols()->uncompromised()];
    }

    /** Digits plus the usual +, spaces, dashes and parentheses - not a strict per-country format. */
    private const CONTACT_NUMBER_RULE = 'regex:/^[0-9+\-()\s]{7,30}$/';

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:organizers,email'],
            'contact_number' => ['required', 'string', self::CONTACT_NUMBER_RULE],
            'password' => array_merge(['required', 'string'], $this->passwordRules()),
            'institution' => ['nullable', 'string', 'max:255'],
            // Picked from a dropdown of admin-created organizations -
            // optional (a signup with no organization yet is a valid state,
            // same as it's always been). Membership itself is only granted
            // once the admin approves the account, see
            // OrganizerApprovalController::approve().
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            // Or, if theirs isn't listed, the one they want the admin to
            // create - with an address, so the admin has something to verify
            // it against before vouching for it (never shown publicly).
            'organization_name' => ['nullable', 'string', 'max:255'],
            'organization_address' => ['nullable', 'string', 'max:500', 'required_with:organization_name'],
        ]);

        // Picking an existing organization wins - a requested-new one is
        // only kept when they didn't.
        $requestingNew = empty($data['organization_id']) && ! empty($data['organization_name']);

        $organizer = Organizer::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'institution' => $data['institution'] ?? null,
            'contact_number' => $data['contact_number'],
            'requested_organization_id' => $data['organization_id'] ?? null,
            'requested_organization_name' => $requestingNew ? $data['organization_name'] : null,
            'requested_organization_address' => $requestingNew ? $data['organization_address'] : null,
        ]);

        $organizer->sendEmailVerificationNotification();
        $this->notifyApplication($organizer);

        $token = $organizer->createToken('api')->plainTextToken;

        // approval_status is the column's own default ('pending'), which the
        // freshly created model doesn't carry until it's reloaded - and the
        // app decides what to show the applicant from it.
        return response()->json([
            'user' => $this->accountPayload($organizer->refresh()),
            'token' => $token,
        ], 201);
    }

    /**
     * Two emails per application: an acknowledgment to the applicant ("we
     * received your application... you can contact us at ...") and an alert
     * to every admin so nobody has to remember to check Approvals. Neither
     * may fail the signup itself - a broken mail config is logged, not thrown,
     * same as the confirmation emails elsewhere.
     */
    private function notifyApplication(Organizer $organizer): void
    {
        try {
            Mail::to($organizer->email)->queue(new ApplicationReceivedMail(
                $organizer->name,
                'organizer account',
                [
                    'Contact number' => $organizer->contact_number,
                    'Organization' => $organizer->requestedOrganization?->name ?? $organizer->requested_organization_name,
                ]
            ));

            foreach (Organizer::where('role', 'admin')->get() as $admin) {
                Mail::to($admin->email)->queue(new NewOrganizerApplicationMail($organizer));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to queue organizer application emails', ['organizer_id' => $organizer->id, 'error' => $e->getMessage()]);
        }
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
            'user' => $this->accountPayload($organizer),
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
        return response()->json($this->accountPayload($request->user()));
    }

    public function updateProfile(Request $request)
    {
        $organizer = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('organizers', 'email')->ignore($organizer->id)],
            'institution' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_number' => ['sometimes', 'nullable', 'string', self::CONTACT_NUMBER_RULE],
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
        if (array_key_exists('contact_number', $data)) {
            $organizer->contact_number = $data['contact_number'];
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

        return response()->json($this->accountPayload($organizer));
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

        return response()->json($this->accountPayload($organizer));
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
