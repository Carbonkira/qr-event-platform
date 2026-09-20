<?php

namespace App\Services;

/**
 * The check-in code encoded in a registration's QR pass.
 *
 * Format: "QR-E{event}-{P|WI}{seq}-{random}" e.g. QR-E012-P034-7HQ2XK9M.
 * The event and sequence parts are just for humans (staff read them off a
 * screen, and they keep codes unique per event); the random tail is the
 * actual secret. The code used to end at the sequence - "QR-E012-P034" - so
 * anyone who knew the format could walk through every registration on the
 * platform (event 1, seq 1, 2, 3...). 8 characters from a 32-symbol alphabet
 * is ~10^12 combinations, which makes a code impossible to guess.
 *
 * Registrations made before this keep their old code: it's already in an
 * email or on someone's phone, and re-issuing it would strand that pass.
 */
class QrCodes
{
    // No 0/O/1/I - a code is sometimes typed in by hand at the door.
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const SECRET_LENGTH = 8;

    public static function generate(int $eventId, bool $walkIn, int $sequence): string
    {
        return sprintf(
            'QR-E%s-%s%s-%s',
            str_pad((string) $eventId, 3, '0', STR_PAD_LEFT),
            $walkIn ? 'WI' : 'P',
            str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            self::secret(),
        );
    }

    /**
     * The running number inside a code, old format or new. A code that
     * doesn't follow the format at all (hand-made fixtures, imported data)
     * falls back to reading its last three characters, which is what this
     * used to do for everything.
     */
    public static function sequenceOf(string $code): int
    {
        if (preg_match('/-(?:P|WI)(\d+)(?:-|$)/', $code, $m)) {
            return (int) $m[1];
        }

        return (int) substr($code, -3);
    }

    private static function secret(): string
    {
        $secret = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::SECRET_LENGTH; $i++) {
            $secret .= self::ALPHABET[random_int(0, $max)];
        }

        return $secret;
    }
}
