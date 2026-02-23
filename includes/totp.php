<?php
/**
 * includes/totp.php
 *
 * Pure-PHP RFC 6238 TOTP implementation — no Composer, no dependencies.
 * Compatible with Google Authenticator, Authy, and any TOTP app.
 */

class TOTP
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const STEP = 30;   // seconds per code window
    private const DIGITS = 6;    // code length
    private const WINDOW = 1;    // ±windows to accept (clock drift)

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /** Generate a random Base32 secret (16 chars = 80 bits). */
    public static function generateSecret(int $length = 16): string
    {
        $secret = '';
        $bytes = random_bytes($length);
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[ord($bytes[$i]) & 31];
        }
        return $secret;
    }

    /** Get the current valid TOTP code for a secret. */
    public static function getCode(string $secret, int $timeSlot = 0): string
    {
        $time = (int) floor((time() + $timeSlot * self::STEP) / self::STEP);
        return self::hotp(self::base32Decode($secret), $time);
    }

    /**
     * Verify a user-supplied code against the secret.
     * Accepts WINDOW slots before/after to handle clock drift.
     */
    public static function verify(string $secret, string $code): bool
    {
        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $key = self::base32Decode($secret);
        $currentSlot = (int) floor(time() / self::STEP);

        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            if (hash_equals(self::hotp($key, $currentSlot + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build a QR code URL (Google Charts) for scanning with an authenticator app.
     * otpauth://totp/Issuer:email?secret=SECRET&issuer=Issuer
     */
    public static function getQrUrl(string $secret, string $email, string $issuer = 'GymFlow'): string
    {
        $label = rawurlencode($issuer . ':' . $email);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::STEP,
        ]);
        $otpauth = 'otpauth://totp/' . $label . '?' . $params;
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($otpauth);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────────────────

    /** RFC 4226 HOTP: HMAC-SHA1 + dynamic truncation. */
    private static function hotp(string $key, int $counter): string
    {
        $counterBytes = pack('N*', 0) . pack('N*', $counter);   // 8 bytes big-endian
        $hash = hash_hmac('sha1', $counterBytes, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);
        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** Base32 decode (RFC 4648). */
    private static function base32Decode(string $input): string
    {
        $input = strtoupper(trim($input));
        $map = array_flip(str_split(self::BASE32_CHARS));
        $binary = '';
        $buffer = 0;
        $bitsLeft = 0;

        foreach (str_split($input) as $char) {
            if ($char === '=')
                break;
            if (!isset($map[$char]))
                continue;
            $buffer = ($buffer << 5) | $map[$char];
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $binary .= chr(($buffer >> ($bitsLeft - 8)) & 0xFF);
                $bitsLeft -= 8;
            }
        }
        return $binary;
    }
}
