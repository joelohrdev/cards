<?php

namespace App\Services\Ebay;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * A failure talking to eBay, carrying a message that's safe and useful to show the seller.
 */
class EbayException extends RuntimeException
{
    /**
     * Build an exception from an eBay error response, using eBay's own error text when present.
     */
    public static function fromResponse(Response $response, ?Throwable $previous = null): self
    {
        $errors = $response->json('errors');

        $messages = collect(is_array($errors) ? $errors : [])
            ->map(fn (mixed $error): ?string => is_array($error) ? ($error['longMessage'] ?? $error['message'] ?? null) : null)
            ->filter()
            ->unique()
            ->values();

        $description = $response->json('error_description');

        $message = match (true) {
            $messages->isNotEmpty() => $messages->implode(' '),
            is_string($description) && $description !== '' => $description,
            default => "eBay returned an unexpected error (HTTP {$response->status()}). Try again in a few minutes.",
        };

        return new self($message, $response->status(), $previous);
    }

    public static function notConfigured(): self
    {
        return new self('eBay isn’t configured. Set EBAY_CLIENT_ID, EBAY_CLIENT_SECRET and EBAY_RU_NAME in your .env file.');
    }

    public static function reconnectRequired(): self
    {
        return new self('Your eBay connection has expired. Reconnect your eBay account in eBay settings, then try again.');
    }
}
