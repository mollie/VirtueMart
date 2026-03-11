<?php

namespace Mollie\Payment\Infrastructure\DTO;

class Response
{
    public function __construct(
        public readonly string $type,
        public readonly array $data = [],
        public readonly ?string $error = null
    ) {}

    public function isSuccess(): bool
    {
        return $this->type === 'success';
    }

    public function isError(): bool
    {
        return $this->type === 'error';
    }

    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
        ];

        if (!empty($this->data)) {
            $result = array_merge($result, $this->data);
        }

        if ($this->error !== null) {
            $result['error'] = $this->error;
        }

        return $result;
    }

    public static function success(array $data = []): self
    {
        return new self('success', $data);
    }

    public static function error(string $message, string $type = 'error'): self
    {
        return new self($type, [], $message);
    }

    public static function fromArray(array $data): self
    {
        $type = $data['type'] ?? 'unknown';
        $error = $data['error'] ?? null;

        unset($data['type'], $data['error']);

        return new self($type, $data, $error);
    }
}