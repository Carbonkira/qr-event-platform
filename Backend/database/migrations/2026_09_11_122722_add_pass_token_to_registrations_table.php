<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes a real privacy hole: the public pass endpoints
 * (RegistrationController::show()/QrCodeController::show()) were keyed
 * only by a registration's plain sequential id - trivially enumerable
 * (1, 2, 3, ...), letting anyone walk through every registration on the
 * platform and read off other people's names and (once payment clears)
 * their actual check-in QR code. This token makes the URL itself the
 * secret, the same way a signed link already works elsewhere in this app.
 *
 * Nullable, not backfilled - existing registrations keep working exactly
 * as before (both endpoints treat a null pass_token as "no token required",
 * see the grandfathering comment in RegistrationController::show()).
 * Every new registration always gets one (see Registration::booted()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('pass_token', 64)->nullable()->after('qr_code');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('pass_token');
        });
    }
};
