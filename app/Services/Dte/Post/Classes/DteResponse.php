<?php

declare(strict_types=1);

namespace App\Services\Dte\Post\Classes;

use Illuminate\Contracts\Support\Arrayable;

final class DteResponse implements Arrayable
{
    public function __construct(
        public readonly bool $accepted,
        public readonly ?int $document_id = null,
        public readonly ?int $folio = null,
        public readonly ?string $xml = null,
        public readonly ?string $message = null,
        public readonly ?array $warnings = null,
        public readonly ?array $errors = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'accepted' => $this->accepted,
            'document_id' => $this->document_id,
            'folio' => $this->folio,
            'xml' => $this->xml,
            'message' => $this->message,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
