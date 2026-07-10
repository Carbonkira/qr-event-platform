<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

class QrCodeController extends Controller
{
    /**
     * Renders a registration's QR pass as a PNG. Only used server-side
     * (embedded as an <img> in confirmation/reminder emails) - the app
     * itself renders the same qr_code string client-side via the `qrcode`
     * npm package, since a live page can do that without a round-trip.
     */
    public function show(Registration $registration)
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($registration->qr_code)
            ->size(300)
            ->margin(10)
            ->build();

        return response($result->getString(), 200)
            ->header('Content-Type', $result->getMimeType())
            ->header('Cache-Control', 'public, max-age=31536000, immutable');
    }
}
