<?php

namespace App\Services\Psa;

use App\Enums\Grader;
use App\Enums\Sport;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Looks up a PSA certification number with PSA's public API.
 *
 * Endpoint: GET {services.psa.url}/cert/GetByCertNumber/{certNumber} with an
 * "Authorization: Bearer {token}" header. A found cert returns 200 with a `PSACert` object
 * (CertNumber, Year, Brand, Category, CardNumber, Subject, Variety, CardGrade, ...). A cert that
 * doesn't exist also returns 200, with `ServerMessage: "No data found"` (or `IsValidRequest: false`
 * and "Invalid CertNo" for a malformed number). A 500 usually means invalid credentials.
 * PSA has no separate set field; its Brand (e.g. "TOPPS CHROME") doubles as the set name.
 * Free accounts are limited to 100 calls a day, so successful lookups are cached.
 *
 * @see https://www.psacard.com/publicapi/documentation
 * @see https://api.psacard.com/publicapi/swagger.json (operation Cert_GetByCertNumber, PublicPSACert model)
 */
class PsaCertLookup
{
    /**
     * How long a found cert is cached. Slab details never change once graded.
     */
    private const int CACHE_SECONDS = 60 * 60 * 24 * 30;

    public function isConfigured(): bool
    {
        return filled(config('services.psa.token'));
    }

    /**
     * Fetch a cert's details, or null when PSA has no such cert.
     *
     * @return array{grader: string, cert_number: string, grade: string|null, year: int|null, brand: string|null, set_name: string|null, card_number: string|null, player: string|null, variety: string|null, sport: string|null}|null
     *
     * @throws PsaLookupFailed
     */
    public function find(string $certNumber): ?array
    {
        $certNumber = trim($certNumber);
        $cacheKey = 'psa-cert:'.$certNumber;

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return self::fromCache($cached);
        }

        $result = $this->fetch($certNumber);

        if ($result !== null) {
            Cache::put($cacheKey, $result, self::CACHE_SECONDS);
        }

        return $result;
    }

    /**
     * @return array{grader: string, cert_number: string, grade: string|null, year: int|null, brand: string|null, set_name: string|null, card_number: string|null, player: string|null, variety: string|null, sport: string|null}|null
     *
     * @throws PsaLookupFailed
     */
    private function fetch(string $certNumber): ?array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('services.psa.url'), '/'))
                ->withToken((string) config('services.psa.token'))
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(8)
                ->retry([200, 600], 0, fn (\Throwable $exception): bool => $exception instanceof ConnectionException, throw: false)
                ->get('cert/GetByCertNumber/'.rawurlencode($certNumber));
        } catch (ConnectionException $exception) {
            throw new PsaLookupFailed('Could not reach PSA.', previous: $exception);
        }

        if ($response->status() === 204 || $response->notFound()) {
            return null;
        }

        if (! $response->successful()) {
            throw new PsaLookupFailed("PSA responded with HTTP {$response->status()}.");
        }

        return $this->parse($response, $certNumber);
    }

    /**
     * @return array{grader: string, cert_number: string, grade: string|null, year: int|null, brand: string|null, set_name: string|null, card_number: string|null, player: string|null, variety: string|null, sport: string|null}|null
     */
    private function parse(Response $response, string $certNumber): ?array
    {
        $cert = $response->json('PSACert');

        if (! is_array($cert) || blank($cert['CertNumber'] ?? null)) {
            return null;
        }

        $text = fn (string $key): ?string => is_scalar($cert[$key] ?? null) && trim((string) $cert[$key]) !== ''
            ? trim((string) $cert[$key])
            : null;

        $year = $text('Year');

        return [
            'grader' => Grader::Psa->value,
            'cert_number' => $text('CertNumber') ?? $certNumber,
            'grade' => $this->grade($text('CardGrade')),
            'year' => $year !== null && preg_match('/\d{4}/', $year, $match) ? (int) $match[0] : null,
            'brand' => $text('Brand'),
            'set_name' => $text('Brand'),
            'card_number' => $text('CardNumber'),
            'player' => $text('Subject'),
            'variety' => $text('Variety'),
            'sport' => $this->sport($text('Category')),
        ];
    }

    /**
     * PSA categories look like "BASEBALL CARDS"; map them to our sport values when they match.
     */
    private function sport(?string $category): ?string
    {
        if ($category === null) {
            return null;
        }

        $word = strtolower(strtok($category, ' ') ?: '');

        return Sport::tryFrom($word)->value ?? $category;
    }

    /**
     * PSA reports grades like "GEM MT 10" or "NM-MT 8"; keep just the number (or "Authentic").
     */
    private function grade(?string $cardGrade): ?string
    {
        if ($cardGrade === null) {
            return null;
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*$/', $cardGrade, $match)) {
            return $match[1];
        }

        return $cardGrade;
    }

    /**
     * Rebuild a cached lookup with its declared types.
     *
     * @param  array<mixed>  $cached
     * @return array{grader: string, cert_number: string, grade: string|null, year: int|null, brand: string|null, set_name: string|null, card_number: string|null, player: string|null, variety: string|null, sport: string|null}
     */
    private static function fromCache(array $cached): array
    {
        $text = fn (string $key): ?string => isset($cached[$key]) && is_scalar($cached[$key]) ? (string) $cached[$key] : null;

        return [
            'grader' => $text('grader') ?? 'PSA',
            'cert_number' => $text('cert_number') ?? '',
            'grade' => $text('grade'),
            'year' => isset($cached['year']) && is_numeric($cached['year']) ? (int) $cached['year'] : null,
            'brand' => $text('brand'),
            'set_name' => $text('set_name'),
            'card_number' => $text('card_number'),
            'player' => $text('player'),
            'variety' => $text('variety'),
            'sport' => $text('sport'),
        ];
    }
}
