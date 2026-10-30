<?php

namespace App\Services\Ebay;

/**
 * Verifies the X-EBAY-SIGNATURE header eBay sends with marketplace account deletion notifications.
 *
 * The header is base64-encoded JSON holding the signing key ID and an ECDSA signature of the raw
 * request body. The matching public key comes from the Notification API.
 *
 * @see https://developer.ebay.com/marketplace-account-deletion
 * @see https://developer.ebay.com/api-docs/commerce/notification/resources/public_key/methods/getPublicKey
 */
class NotificationSignature
{
    public function __construct(private EbayClient $client) {}

    public function isValid(?string $header, string $body): bool
    {
        if (blank($header)) {
            return false;
        }

        $decoded = json_decode((string) base64_decode($header, true), true);

        if (! is_array($decoded) || blank($decoded['kid'] ?? null) || blank($decoded['signature'] ?? null)) {
            return false;
        }

        try {
            $publicKey = self::toPem($this->client->notificationPublicKey((string) $decoded['kid']));
        } catch (EbayException) {
            return false;
        }

        $signature = base64_decode((string) $decoded['signature'], true);

        if ($signature === false) {
            return false;
        }

        return openssl_verify($body, $signature, $publicKey, OPENSSL_ALGO_SHA1) === 1;
    }

    /**
     * eBay returns the key with its PEM armour on one line; OpenSSL needs proper line breaks.
     */
    public static function toPem(string $key): string
    {
        $body = (string) preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\s+/', '', $key);

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split($body, 64, "\n")."-----END PUBLIC KEY-----\n";
    }
}
