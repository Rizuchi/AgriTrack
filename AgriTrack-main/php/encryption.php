<?php

function getEncryptionKey(): string
{
    
    $key = 'AgriTrack-Flores-Cruz-Estrellado-D'; //KEY

    return hash('sha256', $key, true);
}

function encryptData(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    $iv = random_bytes(16);
    $ciphertext = openssl_encrypt($value, 'AES-256-CBC', getEncryptionKey(), OPENSSL_RAW_DATA, $iv);

    if ($ciphertext === false) {
        throw new Exception('Encryption failed.');
    }

    return base64_encode($iv . $ciphertext);
}

function decryptData(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    $decoded = base64_decode($value, true);
    if ($decoded === false) {
        return $value;
    }

    $iv = substr($decoded, 0, 16);
    $ciphertext = substr($decoded, 16);

    $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', getEncryptionKey(), OPENSSL_RAW_DATA, $iv);

    return $plaintext === false ? $value : $plaintext;
}

function encryptDeterministic(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    $iv = substr(hash_hmac('sha256', $value, getEncryptionKey(), true), 0, 16);
    $ciphertext = openssl_encrypt($value, 'AES-256-CBC', getEncryptionKey(), OPENSSL_RAW_DATA, $iv);

    if ($ciphertext === false) {
        throw new Exception('Encryption failed.');
    }

    return base64_encode($iv . $ciphertext);
}