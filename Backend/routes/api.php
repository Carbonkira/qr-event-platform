<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\DiscussionController;
use App\Http\Controllers\Api\FeedbackSummaryController;
use App\Http\Controllers\Api\InviteController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\OrgController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\TaskTemplateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes (no auth) - matches plan §2's public route table.
| Participants never log in; registration/feedback/pass-lookup stay public.
|--------------------------------------------------------------------------
*/
// Throttled together - login/register/password-reset are the endpoints an
// automated credential-stuffing or account-enumeration attempt would hit.
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
});
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{slug}', [EventController::class, 'show']);
Route::get('/invites/{token}', [InviteController::class, 'show']);
Route::get('/org/{organization:slug}', [OrgController::class, 'showPublic']);
// Unauthenticated write endpoints an abuse script could otherwise hammer
// with no account and no ownership check to fall back on.
Route::middleware('throttle:30,1')->group(function () {
    // Self-serve walk-in check-in only - no account needed, matches an
    // on-site kiosk flow. Pre-event registration (below, authenticated) is the norm.
    Route::post('/events/{event}/walk-in', [RegistrationController::class, 'walkIn']);
    Route::post('/events/{event}/feedback', [FeedbackController::class, 'store']);
});
Route::get('/pass/lookup', [RegistrationController::class, 'lookup']);
Route::get('/organization', [OrganizationController::class, 'show']);
// Embedded as an <img> in confirmation/reminder emails, so it has to be
// fetchable by the recipient's mail client with no auth header.
Route::get('/registrations/{registration}/qr.png', [QrCodeController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Authenticated routes (auth:sanctum) - any logged-in organizer, matching
| the mock's flat single-tier org model (plan §7: no separate admin role).
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/me', [AuthController::class, 'updateProfile']);
    Route::post('/auth/me/avatar', [AuthController::class, 'uploadAvatar'])->middleware('throttle:20,1');
    Route::post('/auth/email/verification-notification', [AuthController::class, 'resendVerification']);

    Route::get('/admin/events', [EventController::class, 'adminIndex']);
    // Calls the Anthropic API per request - throttled tighter than a normal
    // write endpoint since it has a real per-call cost, not just abuse risk.
    Route::post('/events/generate-description', [EventController::class, 'generateDescription'])
        ->middleware('throttle:10,1');
    Route::post('/events/upload-image', [EventController::class, 'uploadImage'])
        ->middleware('throttle:20,1');
    Route::post('/events', [EventController::class, 'store'])->middleware('verified');
    Route::put('/events/{event}', [EventController::class, 'update']);
    Route::delete('/events/{event}', [EventController::class, 'destroy']);
    Route::post('/events/{event}/approve', [EventController::class, 'approve']);
    Route::post('/events/{event}/reject', [EventController::class, 'reject']);
    Route::post('/events/{event}/submit', [EventController::class, 'submit']);
    Route::post('/events/{event}/complete', [EventController::class, 'complete']);
    Route::post('/events/{event}/duplicate', [EventController::class, 'duplicate']);
    Route::post('/events/{event}/tasks', [EventController::class, 'addTask']);
    Route::patch('/events/{event}/tasks/{task}', [EventController::class, 'toggleTask']);

    // Same reasoning as the public write throttle above - having an account
    // (or being an organizer) doesn't mean a single user should be able to
    // fire an unbounded number of registrations/imports per minute.
    Route::middleware('throttle:30,1')->group(function () {
        // Pre-event registration requires an account (see RegistrationController::store).
        Route::post('/events/{event}/register', [RegistrationController::class, 'store'])->middleware('verified');
        Route::post('/events/{event}/registrations', [RegistrationController::class, 'addGuest']);
        Route::post('/events/{event}/registrations/import', [RegistrationController::class, 'importCsv']);
    });
    Route::get('/events/{event}/registrations', [RegistrationController::class, 'indexForEvent']);
    Route::put('/registrations/{registration}', [RegistrationController::class, 'update']);
    Route::delete('/registrations/{registration}', [RegistrationController::class, 'destroy']);
    Route::post('/registrations/{registration}/verify-payment', [RegistrationController::class, 'verifyPayment']);
    Route::post('/registrations/{registration}/promote', [RegistrationController::class, 'promote']);
    Route::get('/my/registrations', [RegistrationController::class, 'mine']);

    Route::post('/attendance/scan', [AttendanceController::class, 'scan']);

    Route::get('/feedback', [FeedbackController::class, 'index']);
    Route::post('/events/{event}/feedback-summary', [FeedbackSummaryController::class, 'generate']);

    Route::get('/analytics', [AnalyticsController::class, 'index']);

    Route::put('/organization', [OrganizationController::class, 'update']);

    Route::get('/orgs/mine', [OrgController::class, 'mine']);
    Route::post('/orgs', [OrgController::class, 'store']);
    Route::put('/orgs/{organization}', [OrgController::class, 'update']);
    Route::post('/orgs/{organization}/logo', [OrgController::class, 'uploadLogo'])->middleware('throttle:20,1');
    Route::get('/orgs/{organization}/members', [OrgController::class, 'members']);
    Route::delete('/orgs/{organization}/members/{user}', [OrgController::class, 'removeMember']);
    Route::get('/orgs/{organization}/invites', [OrgController::class, 'invites']);
    Route::post('/orgs/{organization}/invites', [OrgController::class, 'storeInvite'])->middleware('throttle:20,1');
    Route::delete('/orgs/{organization}/invites/{invite}', [OrgController::class, 'destroyInvite']);
    Route::post('/invites/{token}/accept', [InviteController::class, 'accept']);

    Route::get('/orgs/{organization}/discussion', [DiscussionController::class, 'index']);
    Route::post('/orgs/{organization}/discussion', [DiscussionController::class, 'store']);
    Route::get('/discussion/threads/{thread}', [DiscussionController::class, 'show']);
    Route::post('/discussion/threads/{thread}/replies', [DiscussionController::class, 'storeReply']);

    Route::get('/task-templates', [TaskTemplateController::class, 'index']);
    Route::post('/task-templates', [TaskTemplateController::class, 'store']);
});
