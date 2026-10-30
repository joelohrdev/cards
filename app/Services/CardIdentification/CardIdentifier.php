<?php

namespace App\Services\CardIdentification;

use App\Enums\Grader;
use App\Enums\Sport;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * Asks Claude (Anthropic Messages API) what sports card is in a photo.
 *
 * Sends the image as a base64 content block and constrains the reply with structured outputs
 * (`output_config.format` of type `json_schema`), then validates and normalizes every field
 * server-side anyway, since a refusal or truncated reply can still break the schema.
 *
 * @see https://platform.claude.com/docs/en/build-with-claude/structured-outputs
 * @see https://platform.claude.com/docs/en/build-with-claude/vision
 */
class CardIdentifier
{
    private const string ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const string API_VERSION = '2026-10-21';

    /**
     * Models that accept the server-side refusal fallback (`fallbacks: "default"`).
     *
     * @var list<string>
     */
    private const array FALLBACK_MODELS = ['claude-fable-5-1', 'claude-opus-5-5', 'claude-opus-5', 'claude-sonnet-5-5'];

    private const string PROMPT = <<<'TEXT'
        Identify the trading card in this photo for a collector's inventory.

        Read what is printed on the card (front or back) and on any grading slab label. Fill each
        field only with what you can see or are confident about from the card's design; use null
        when unsure rather than guessing.

        - year: the set's year (for a season like 2023-24, the first year).
        - manufacturer: the company, e.g. "Topps", "Panini", "Upper Deck".
        - set_name: the product without year or manufacturer, e.g. "Chrome", "Prizm", "Series 1".
        - number: the card number as printed, without "#".
        - name: the player or subject named on the card.
        - parallel: the parallel or refractor name if it isn't the base card, e.g. "Silver", "Gold Refractor".
        - serial_number / print_run: from a stamp like "12/99" (serial 12, print run 99).
        - is_graded, grader, grade, cert_number: from a grading slab label if the card is slabbed.
        - confidence: how sure you are of the card's identity overall.
        TEXT;

    public function isConfigured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * Identify the card in a photo.
     *
     * @return array{sport: string|null, year: int|null, manufacturer: string|null, set_name: string|null, number: string|null, name: string|null, team: string|null, parallel: string|null, serial_number: int|null, print_run: int|null, is_rookie: bool, is_autograph: bool, is_memorabilia: bool, is_graded: bool, grader: string|null, grade: string|null, cert_number: string|null, confidence: string}
     *
     * @throws CardIdentificationFailed
     */
    public function identify(UploadedFile $photo): array
    {
        $model = (string) config('services.anthropic.model');
        $useFallbacks = in_array($model, self::FALLBACK_MODELS, true);

        $body = [
            'model' => $model,
            'max_tokens' => 4096,
            'output_config' => [
                'effort' => 'low',
                'format' => ['type' => 'json_schema', 'schema' => self::schema()],
            ],
            'messages' => [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => $photo->getMimeType(),
                            'data' => base64_encode((string) $photo->get()),
                        ],
                    ],
                    ['type' => 'text', 'text' => self::PROMPT],
                ],
            ]],
        ];

        if ($useFallbacks) {
            $body['fallbacks'] = 'default';
        }

        try {
            $response = Http::withHeaders(array_filter([
                'x-api-key' => (string) config('services.anthropic.key'),
                'anthropic-version' => self::API_VERSION,
                'anthropic-beta' => $useFallbacks ? 'server-side-fallback-2026-07-01' : null,
            ]))
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(60)
                ->post(self::ENDPOINT, $body);
        } catch (ConnectionException $exception) {
            throw new CardIdentificationFailed('Could not reach the Anthropic API.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new CardIdentificationFailed("Anthropic API responded with HTTP {$response->status()}: ".$response->json('error.message', ''));
        }

        $stopReason = $response->json('stop_reason');

        if ($stopReason === 'refusal' || $stopReason === 'max_tokens') {
            throw new CardIdentificationFailed("The model stopped early ({$stopReason}).");
        }

        $content = $response->json('content');

        $text = collect(is_array($content) ? $content : [])
            ->filter(fn (mixed $block): bool => is_array($block) && ($block['type'] ?? null) === 'text')
            ->map(fn (array $block): string => (string) ($block['text'] ?? ''))
            ->implode('');

        return self::normalize($text);
    }

    /**
     * Validate the model's JSON and coerce every field into the suggestion shape.
     *
     * @return array{sport: string|null, year: int|null, manufacturer: string|null, set_name: string|null, number: string|null, name: string|null, team: string|null, parallel: string|null, serial_number: int|null, print_run: int|null, is_rookie: bool, is_autograph: bool, is_memorabilia: bool, is_graded: bool, grader: string|null, grade: string|null, cert_number: string|null, confidence: string}
     *
     * @throws CardIdentificationFailed
     */
    public static function normalize(string $json): array
    {
        try {
            $data = json_decode(trim($json), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new CardIdentificationFailed('The model did not return valid JSON.', previous: $exception);
        }

        if (! is_array($data) || array_is_list($data)) {
            throw new CardIdentificationFailed('The model did not return a JSON object.');
        }

        $text = function (string $key, int $max = 120) use ($data): ?string {
            $value = $data[$key] ?? null;

            if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
                return null;
            }

            $value = trim((string) $value);

            return $value === '' || strtolower($value) === 'null' ? null : mb_substr($value, 0, $max);
        };

        $integer = function (string $key, int $min, int $max) use ($data): ?int {
            $value = $data[$key] ?? null;

            if (is_string($value) && ctype_digit(trim($value))) {
                $value = (int) trim($value);
            }

            return is_int($value) && $value >= $min && $value <= $max ? $value : null;
        };

        $boolean = fn (string $key): bool => ($data[$key] ?? false) === true;

        $sport = $text('sport');
        $grader = $text('grader');
        $isGraded = $boolean('is_graded');
        $confidence = $text('confidence');
        $printRun = $integer('print_run', 1, 100000);
        $serialNumber = $integer('serial_number', 1, 100000);

        if ($serialNumber !== null && $printRun !== null && $serialNumber > $printRun) {
            $serialNumber = null;
        }

        $matchedGrader = $grader === null ? null : collect(Grader::cases())
            ->first(fn (Grader $case): bool => strcasecmp($case->value, $grader) === 0);

        return [
            'sport' => $sport === null ? null : Sport::tryFrom(strtolower($sport))?->value,
            'year' => $integer('year', 1860, (int) now()->year + 1),
            'manufacturer' => $text('manufacturer'),
            'set_name' => $text('set_name'),
            'number' => ($number = $text('number', 20)) === null ? null : ltrim($number, '#'),
            'name' => $text('name'),
            'team' => $text('team'),
            'parallel' => $text('parallel'),
            'serial_number' => $serialNumber,
            'print_run' => $printRun,
            'is_rookie' => $boolean('is_rookie'),
            'is_autograph' => $boolean('is_autograph'),
            'is_memorabilia' => $boolean('is_memorabilia'),
            'is_graded' => $isGraded,
            'grader' => $isGraded ? ($matchedGrader->value ?? ($grader === null ? null : Grader::Other->value)) : null,
            'grade' => $isGraded ? $text('grade', 20) : null,
            'cert_number' => $isGraded ? $text('cert_number', 30) : null,
            'confidence' => in_array($confidence, ['high', 'medium', 'low'], true) ? $confidence : 'low',
        ];
    }

    /**
     * JSON schema for the model's answer. Structured outputs require every property listed in
     * `required` and `additionalProperties: false`.
     *
     * @return array<string, mixed>
     */
    private static function schema(): array
    {
        $nullable = fn (array $schema): array => ['anyOf' => [$schema, ['type' => 'null']]];
        $string = $nullable(['type' => 'string']);
        $integer = $nullable(['type' => 'integer']);
        $boolean = ['type' => 'boolean'];

        $properties = [
            'sport' => $nullable(['type' => 'string', 'enum' => array_map(fn (Sport $sport): string => $sport->value, Sport::cases())]),
            'year' => $integer,
            'manufacturer' => $string,
            'set_name' => $string,
            'number' => $string,
            'name' => $string,
            'team' => $string,
            'parallel' => $string,
            'serial_number' => $integer,
            'print_run' => $integer,
            'is_rookie' => $boolean,
            'is_autograph' => $boolean,
            'is_memorabilia' => $boolean,
            'is_graded' => $boolean,
            'grader' => $nullable(['type' => 'string', 'enum' => array_map(fn (Grader $grader): string => $grader->value, Grader::cases())]),
            'grade' => $string,
            'cert_number' => $string,
            'confidence' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
        ];

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }
}
