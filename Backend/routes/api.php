<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\DiscussionController;
use App\Http\Controllers\Api\FeedbackSummaryController;
use App\Http\Controllers\Api\InviteController;
use App\Http\Controllers\Api\OrganizerApprovalController;
use App\Http\Controllers\Api\OrganizerAuthController;
use App\Http\Controllers\Api\OrgController;
use App\Http\Controllers\Api\ParticipantAuthController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\TaskTemplateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes (no auth) - matches plan §2's public route table.
|--------------------------------------------------------------------------
*/
// Throttled together - login/register/password-reset are the endpoints an
// automated credential-stuffing or account-enumeration attempt would hit.
// Fully parallel organizer/participant pairs (see OrganizerAuthController/
// ParticipantAuthController) - genuinely separate accounts per the adviser's
// review, not one shared users table with a role flag.
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/organizer/register', [OrganizerAuthController::class, 'register']);
    Route::post('/auth/organizer/login', [OrganizerAuthController::class, 'login']);
    Route::post('/auth/organizer/forgot-password', [OrganizerAuthController::class, 'forgotPassword']);
    Route::post('/auth/organizer/reset-password', [OrganizerAuthController::class, 'resetPassword']);
    Route::post('/auth/organizer/reset-password/validate', [OrganizerAuthController::class, 'validateResetToken']);

    Route::post('/auth/participant/register', [ParticipantAuthController::class, 'register']);
    Route::post('/auth/participant/login', [ParticipantAuthController::class, 'login']);
    Route::post('/auth/participant/forgot-password', [ParticipantAuthController::class, 'forgotPassword']);
    Route::post('/auth/participant/reset-password', [ParticipantAuthController::class, 'resetPassword']);
    Route::post('/auth/participant/reset-password/validate', [ParticipantAuthController::class, 'validateResetToken']);
});
// Two distinct named routes, not one shared 'verification.verify' - the
// same numeric id can exist in both tables at once, so the signed link has
// to say which table to check (see Organizer/ParticipantVerifyEmailNotification).
Route::get('/organizer/email/verify/{id}/{hash}', [OrganizerAuthController::class, 'verify'])
    ->middleware('signed')
    ->name('organizer.verification.verify');
Route::get('/participant/email/verify/{id}/{hash}', [ParticipantAuthController::class, 'verify'])
    ->middleware('signed')
    ->name('participant.verification.verify');

Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{slug}', [EventController::class, 'show']);
Route::get('/invites/{token}', [InviteController::class, 'show']);
Route::get('/orgs', [OrgController::class, 'directory']);
// Public + minimal (id/name only) - feeds the organizer signup form's
// organization picker, which runs before any account/token exists.
Route::get('/orgs/list', [OrgController::class, 'list']);
Route::get('/org/{organization:slug}', [OrgController::class, 'showPublic']);
// Unauthenticated write endpoints an abuse script could otherwise hammer
// with no account and no ownership check to fall back on.
Route::middleware('throttle:30,1')->group(function () {
    // Self-serve walk-in check-in only - no account needed, matches an
    // on-site kiosk flow. Pre-event registration (below, authenticated) is the norm.
    Route::post('/events/{event}/walk-in', [RegistrationController::class, 'walkIn']);
    Route::post('/events/{event}/feedback', [FeedbackController::class, 'store']);
    // Both keyed by something guessable with no account behind them (an
    // email, a sequential id) - unthrottled, either could be scripted to
    // enumerate who's registered for what.
    Route::get('/pass/lookup', [RegistrationController::class, 'lookup']);
    Route::get('/registrations/{registration}', [RegistrationController::class, 'show']);
});
// Embedded as an <img> in confirmation/reminder emails, so it has to be
// fetchable by the recipient's mail client with no auth header.
Route::get('/registrations/{registration}/qr.png', [QrCodeController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Authenticated routes (auth:sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Escape hatches only - deliberately left off the 'verified' gate below
    // so an unverified account can always get itself unstuck: log out, see
    // who it's logged in as, fix a typo'd email/avatar, or ask for a fresh
    // verification link. Nothing else works until the account is verified.
    // Split by account type (not just left generic) because updateProfile's
    // email-uniqueness check has to query the right table - a Participant
    // token hitting the organizer routes would check uniqueness against the
    // wrong table entirely.
    Route::middleware('organizer')->group(function () {
        Route::post('/auth/organizer/logout', [OrganizerAuthController::class, 'logout']);
        Route::get('/auth/organizer/me', [OrganizerAuthController::class, 'me']);
        Route::put('/auth/organizer/me', [OrganizerAuthController::class, 'updateProfile']);
        Route::post('/auth/organizer/me/avatar', [OrganizerAuthController::class, 'uploadAvatar'])->middleware('throttle:20,1');
        Route::post('/auth/organizer/email/verification-notification', [OrganizerAuthController::class, 'resendVerification']);
    });
    Route::middleware('participant')->group(function () {
        Route::post('/auth/participant/logout', [ParticipantAuthController::class, 'logout']);
        Route::get('/auth/participant/me', [ParticipantAuthController::class, 'me']);
        Route::put('/auth/participant/me', [ParticipantAuthController::class, 'updateProfile']);
        Route::post('/auth/participant/me/avatar', [ParticipantAuthController::class, 'uploadAvatar'])->middleware('throttle:20,1');
        Route::post('/auth/participant/email/verification-notification', [ParticipantAuthController::class, 'resendVerification']);
    });
});

// Previously 'verified' only gated event creation and event registration
// (2 routes); widened per adviser review to a real site-wide gate - having
// an account is no longer enough on its own to do anything with it. This
// group is for actions any verified account can take regardless of whether
// it's ever been approved to organize anything.
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Strictly participant actions - an Organizer token can't register for
    // an event (see EnsureParticipant).
    Route::middleware('participant')->group(function () {
        // Same reasoning as the public write throttle above - having an
        // account doesn't mean a single user should be able to fire an
        // unbounded number of registrations per minute.
        Route::middleware('throttle:30,1')->group(function () {
            Route::post('/events/{event}/register', [RegistrationController::class, 'store']);
        });
        Route::get('/my/registrations', [RegistrationController::class, 'mine']);
    });

    // The one surface both account types share - org members and event
    // registrants alike (see DiscussionController::authorizeAccess).
    Route::get('/orgs/{organization}/discussion', [DiscussionController::class, 'index']);
    Route::post('/orgs/{organization}/discussion', [DiscussionController::class, 'store']);
    Route::get('/discussion/threads/{thread}', [DiscussionController::class, 'show']);
    Route::post('/discussion/threads/{thread}/replies', [DiscussionController::class, 'storeReply']);
});

// Organizer tooling proper - on top of being verified, the account also has
// to be an Organizer (not a Participant) and have been through admin
// approval (see EnsureOrganizerApproved, which checks both). A brand-new
// participant signup can register for events, network, and post in a
// discussion the moment it's verified, same as before; everything here
// stays locked until an admin has approved the account as an organizer.
Route::middleware(['auth:sanctum', 'verified', 'organizer.approved'])->group(function () {
    Route::get('/organizers/pending', [OrganizerApprovalController::class, 'pending']);
    Route::post('/organizers/{user}/approve', [OrganizerApprovalController::class, 'approve']);
    Route::post('/organizers/{user}/reject', [OrganizerApprovalController::class, 'reject']);

    Route::get('/admin/events', [EventController::class, 'adminIndex']);
    // Calls the Anthropic API per request - throttled tighter than a normal
    // write endpoint since it has a real per-call cost, not just abuse risk.
    Route::post('/events/generate-description', [EventController::class, 'generateDescription'])
        ->middleware('throttle:10,1');
    Route::post('/events/upload-image', [EventController::class, 'uploadImage'])
        ->middleware('throttle:20,1');
    Route::post('/events', [EventController::class, 'store']);
    Route::put('/events/{event}', [EventController::class, 'update']);
    Route::delete('/events/{event}', [EventController::class, 'destroy']);
    Route::post('/events/{event}/approve', [EventController::class, 'approve']);
    Route::post('/events/{event}/reject', [EventController::class, 'reject']);
    Route::post('/events/{event}/submit', [EventController::class, 'submit']);
    Route::post('/events/{event}/complete', [EventController::class, 'complete']);
    Route::post('/events/{event}/cancel', [EventController::class, 'cancel']);
    Route::post('/events/{event}/duplicate', [EventController::class, 'duplicate']);
    Route::post('/events/{event}/tasks', [EventController::class, 'addTask']);
    Route::patch('/events/{event}/tasks/{task}', [EventController::class, 'toggleTask']);

    Route::middleware('throttle:30,1')->group(function () {
        Route::post('/events/{event}/registrations', [RegistrationController::class, 'addGuest']);
        Route::post('/events/{event}/registrations/import', [RegistrationController::class, 'importCsv']);
    });
    Route::get('/events/{event}/registrations', [RegistrationController::class, 'indexForEvent']);
    Route::put('/registrations/{registration}', [RegistrationController::class, 'update']);
    Route::delete('/registrations/{registration}', [RegistrationController::class, 'destroy']);
    Route::post('/registrations/{registration}/verify-payment', [RegistrationController::class, 'verifyPayment']);
    Route::post('/registrations/{registration}/promote', [RegistrationController::class, 'promote']);

    Route::post('/attendance/scan', [AttendanceController::class, 'scan']);

    Route::get('/feedback', [FeedbackController::class, 'index']);
    Route::post('/events/{event}/feedback-summary', [FeedbackSummaryController::class, 'generate']);

    Route::get('/analytics', [AnalyticsController::class, 'index']);

    Route::get('/orgs/mine', [OrgController::class, 'mine']);
    Route::post('/orgs', [OrgController::class, 'store']);
    Route::put('/orgs/{organization}', [OrgController::class, 'update']);
    Route::delete('/orgs/{organization}', [OrgController::class, 'destroy']);
    Route::post('/orgs/{organization}/logo', [OrgController::class, 'uploadLogo'])->middleware('throttle:20,1');
    Route::get('/orgs/{organization}/members', [OrgController::class, 'members']);
    Route::post('/orgs/{organization}/members/{user}/promote', [OrgController::class, 'promoteMember']);
    Route::post('/orgs/{organization}/members/{user}/demote', [OrgController::class, 'demoteMember']);
    Route::delete('/orgs/{organization}/members/{user}', [OrgController::class, 'removeMember']);
    Route::get('/orgs/{organization}/invites', [OrgController::class, 'invites']);
    Route::post('/orgs/{organization}/invites', [OrgController::class, 'storeInvite'])->middleware('throttle:20,1');
    Route::delete('/orgs/{organization}/invites/{invite}', [OrgController::class, 'destroyInvite']);
    Route::post('/invites/{token}/accept', [InviteController::class, 'accept']);

    Route::get('/task-templates', [TaskTemplateController::class, 'index']);
    Route::post('/task-templates', [TaskTemplateController::class, 'store']);
});
