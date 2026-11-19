<?php

declare(strict_types=1);

namespace Src\Shared\Http;

use JsonSerializable;

/**
 * Class BaseRes
 *
 * Canonical envelope for every HTTP JSON payload produced by the application.
 *
 * It implements \JsonSerializable directly (NOT Laravel's JsonResource) so the
 * wire format is explicit, immutable and framework-agnostic, per Critical
 * Rule #6 (Custom API Resources).
 *
 * Null members are stripped from the output, so a success response stays lean:
 * {"status":"success","message":"...","data":{...}}
 *
 * @see \Features\Shared\Http\BaseRes Thin subclass exposed to the Features layer.
 */
readonly class BaseRes implements JsonSerializable
{
    public const string STATUS_SUCCESS = 'success';

    public const string STATUS_FAIL = 'failed';

    public const string STATUS_ERROR = 'error';

    /**
     * @param  array<string, mixed>|null  $errors
     */
    final public function __construct(
        public string $status = self::STATUS_SUCCESS,
        public ?string $message = null,
        public mixed $data = null,
        public ?array $errors = null,
        public ?string $responseCode = null,
    ) {}

    /**
     * Build a successful envelope.
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        ?string $responseCode = null,
    ): static {
        return new static(
            status: self::STATUS_SUCCESS,
            message: $message,
            data: $data,
            errors: null,
            responseCode: $responseCode,
        );
    }

    /**
     * Build a failure envelope (validation errors, domain rejections, ...).
     *
     * @param  array<string, mixed>|null  $errors
     */
    public static function error(
        ?string $message = null,
        string $status = self::STATUS_FAIL,
        ?string $responseCode = null,
        ?array $errors = null,
        mixed $data = null,
    ): static {
        return new static(
            status: $status,
            message: $message,
            data: $data,
            errors: $errors,
            responseCode: $responseCode,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(
            [
                'status' => $this->status,
                'response_code' => $this->responseCode,
                'message' => $this->message,
                'data' => $this->data,
                'errors' => $this->errors,
            ],
            static fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
