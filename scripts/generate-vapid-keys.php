<?php

/**
 * VAPID-Schlüsselpaar generieren für Web-Push-Benachrichtigungen.
 * Ausführen mit: php scripts/generate-vapid-keys.php
 */

$key     = openssl_pkey_new(['ec' => ['curve_name' => 'prime256v1'], 'private_key_type' => OPENSSL_KEYTYPE_EC]);
$details = openssl_pkey_get_details($key);

// Öffentlicher Schlüssel: unkomprimiertes EC-Format (0x04 || X || Y = 65 Bytes)
$x      = str_pad((string)$details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
$y      = str_pad((string)$details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
$pubRaw = "\x04" . $x . $y;
$pubB64 = rtrim(strtr(base64_encode($pubRaw), '+/', '-_'), '=');

// Privater Schlüssel: 32-Byte-Skalar
$dRaw   = str_pad((string)$details['ec']['d'], 32, "\x00", STR_PAD_LEFT);
$privB64 = rtrim(strtr(base64_encode($dRaw), '+/', '-_'), '=');

echo "# VAPID-Schlüssel – in .env eintragen:\n";
echo "VAPID_PUBLIC_KEY={$pubB64}\n";
echo "VAPID_PRIVATE_KEY={$privB64}\n";
echo "VAPID_SUBJECT=mailto:admin@billardliga.local\n";
