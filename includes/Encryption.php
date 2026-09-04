<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

class Encryption {
    public static function encrypt(string $value): string|false {
        $key = self::getKey();
        if (false === $key || !extension_loaded('openssl')) {
            self::logUnavailableEncryption();
            return false;
        }

        try {
            $iv = random_bytes(12);
        } catch (\Exception $exception) {
            Utils::log('error', 'Could not generate a cryptographically secure encryption nonce.');
            return false;
        }

        $tag = '';
        $encryptedValue = openssl_encrypt(
            $value,
            config()->get('encryption.cipher_method'),
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if (!is_string($encryptedValue) || $tag === '') {
            Utils::log('error', 'Could not encrypt a service credential.');
            return false;
        }

        return config()->get('encryption.version_prefix') . base64_encode($iv . $tag . $encryptedValue);
    }

    public static function decrypt(string $encryptedValue): string|false {
        $key = self::getKey();
        if (false === $key || !extension_loaded('openssl')) {
            self::logUnavailableEncryption();
            return false;
        }

        $versionPrefix = config()->get('encryption.version_prefix');
        if (str_starts_with($encryptedValue, $versionPrefix)) {
            return self::decryptCurrentValue(substr($encryptedValue, strlen($versionPrefix)), $key);
        }

        $legacyKey = self::getLegacyKey();

        return false !== $legacyKey ? self::decryptLegacyValue($encryptedValue, $legacyKey) : false;
    }

    public static function getOption(string $option): string|false {
        $value = get_option($option, '');
        if (!is_string($value) || $value === '') {
            return false;
        }

        return self::decrypt($value);
    }

    public static function updateOption(string $option, string $value): bool {
        $encryptedValue = self::encrypt($value);
        if (false === $encryptedValue) {
            return false;
        }

        return update_option($option, $encryptedValue, false);
    }

    public static function migrateStoredOptions(): void {
        $migrationOption = config()->get('migrations.encrypted_service_options');
        if (get_option($migrationOption)) {
            return;
        }

        if (!self::canEncrypt()) {
            self::logUnavailableEncryption();
            return;
        }

        foreach (config()->get('services', []) as $service) {
            foreach ($service['options'] ?? [] as $option) {
                self::migrateOption($option);
            }
        }

        update_option($migrationOption, '1', false);
    }

    private static function migrateOption(string $option): void {
        $value = get_option($option, '');
        if (
            !is_string($value) ||
            $value === '' ||
            str_starts_with($value, config()->get('encryption.version_prefix'))
        ) {
            return;
        }

        $legacyKey = self::getLegacyKey();
        $plaintext = false !== $legacyKey
            ? self::decryptLegacyValue($value, $legacyKey)
            : false;
        if (false === $plaintext) {
            $plaintext = $value;
        }

        self::updateOption($option, $plaintext);
    }

    private static function decryptCurrentValue(string $encodedValue, string $key): string|false {
        $value = base64_decode($encodedValue, true);
        if (false === $value || strlen($value) <= 28) {
            return false;
        }

        $iv = substr($value, 0, 12);
        $tag = substr($value, 12, 16);
        $ciphertext = substr($value, 28);

        $decryptedValue = openssl_decrypt(
            $ciphertext,
            config()->get('encryption.cipher_method'),
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return is_string($decryptedValue) ? $decryptedValue : false;
    }

    private static function decryptLegacyValue(string $encryptedValue, string $key): string|false {
        $encodedValue = base64_decode($encryptedValue, true);
        if (false === $encodedValue) {
            return false;
        }

        $ivLength = openssl_cipher_iv_length(config()->get('encryption.legacy_cipher_method'));
        $iv = substr($encodedValue, 0, $ivLength);
        $ciphertext = substr($encodedValue, $ivLength);
        $value = openssl_decrypt(
            $ciphertext,
            config()->get('encryption.legacy_cipher_method'),
            $key,
            0,
            $iv
        );
        $legacySalt = self::getLegacySalt();

        if (!is_string($value) || false === $legacySalt || !str_ends_with($value, $legacySalt)) {
            return false;
        }

        return substr($value, 0, -strlen($legacySalt));
    }

    private static function getKey(): string|false {
        if (!defined('AUTH_KEY') || AUTH_KEY === '' || !defined('AUTH_SALT') || AUTH_SALT === '') {
            return false;
        }

        return hash('sha256', AUTH_KEY . AUTH_SALT, true);
    }

    private static function getLegacyKey(): string|false {
        return defined('AUTH_KEY') && AUTH_KEY !== '' ? AUTH_KEY : false;
    }

    private static function getLegacySalt(): string|false {
        return defined('AUTH_SALT') && AUTH_SALT !== '' ? AUTH_SALT : false;
    }

    private static function logUnavailableEncryption(): void {
        Utils::log(
            'error',
            'Service credentials cannot be stored because OpenSSL or WordPress authentication keys are unavailable.'
        );
    }

    private static function canEncrypt(): bool {
        return false !== self::getKey() && extension_loaded('openssl');
    }
}
