<?php

namespace App\Models\Cast;

use CodeIgniter\DataCaster\Cast\BaseCast;
use CodeIgniter\DataCaster\Exceptions\CastException;
use JsonException;
use stdClass;

/**
 * Replaces CodeIgniter's built-in `json` cast handler on every Build model
 * (see BuildModel::$castHandlers), so `json-array` / `?json-array` fields go
 * through here.
 *
 * Reads are tolerant. Until October 2026 several writers json_encode()d a
 * value and handed the string to a field that already had a JSON cast, so the
 * cast encoded it a second time and the JSONB column holds a JSON *string*
 * whose text is the real document (`"{\"a\":1}"` instead of `{"a":1}`).
 * CodeIgniter's JsonCast::get() then returns that string where its signature
 * promises array|stdClass and the request dies with a TypeError (HTTP 500).
 * get() here unwraps such string-wrapped JSON (a few levels at most), and
 * passes legitimate JSON scalars through instead of throwing — build_settings
 * stores plain values such as "confirm".
 *
 * Writes encode normally: callers pass arrays/objects and the cast encodes
 * once. As a backstop, a `json-array` field handed a string that is itself a
 * JSON object/array is stored as that object/array (and logged) rather than
 * double-encoded again; such a string is never a legitimate value for a
 * field documented as an array.
 *
 * `php spark build:repair-json` rewrites rows that are already
 * double-encoded (JsonColumnRepairService).
 */
final class TolerantJsonCast extends BaseCast
{
    /** How many layers of string wrapping get() will peel off. */
    public const MAX_UNWRAP = 4;

    private const ENCODE_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    public static function get(mixed $value, array $params = [], ?object $helper = null): mixed
    {
        if ($value === null) {
            return null;
        }

        $associative = in_array('array', $params, true);

        // Already decoded (e.g. a row passed through twice): nothing to do.
        if (is_array($value) || $value instanceof stdClass) {
            return $value;
        }

        if (! is_string($value)) {
            self::invalidTypeValueError($value);
        }

        try {
            $decoded = json_decode($value, $associative, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw CastException::forInvalidJsonFormat($e->getCode());
        }

        return self::unwrap($decoded, $associative);
    }

    public static function set(mixed $value, array $params = [], ?object $helper = null): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && in_array('array', $params, true)) {
            $unwrapped = self::unwrap($value, false);
            if (is_array($unwrapped) || $unwrapped instanceof stdClass) {
                log_message('warning', 'A json-array field was given pre-encoded JSON; storing the decoded document. Pass the array instead of json_encode() output.');
                $value = $unwrapped;
            }
        }

        try {
            return json_encode($value, self::ENCODE_FLAGS);
        } catch (JsonException $e) {
            throw CastException::forInvalidJsonFormat($e->getCode());
        }
    }

    /**
     * Peels string layers off a decoded JSON value while the string is
     * itself JSON text (an object, an array or a quoted string).
     */
    public static function unwrap(mixed $decoded, bool $associative): mixed
    {
        for ($i = 0; $i < self::MAX_UNWRAP && is_string($decoded) && self::looksEncoded($decoded); $i++) {
            try {
                $decoded = json_decode($decoded, $associative, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                break;
            }
        }

        return $decoded;
    }

    /**
     * True when $text is shaped like JSON text that a writer encoded again:
     * `{...}`, `[...]` or `"..."`. Bare numbers, booleans and null are left
     * alone — a stored string "5" or "true" is ambiguous, and those are
     * legitimate setting values.
     */
    public static function looksEncoded(string $text): bool
    {
        $text = trim($text);
        if (strlen($text) < 2) {
            return false;
        }

        $first = $text[0];
        $last  = $text[strlen($text) - 1];

        return ($first === '{' && $last === '}')
            || ($first === '[' && $last === ']')
            || ($first === '"' && $last === '"');
    }
}
