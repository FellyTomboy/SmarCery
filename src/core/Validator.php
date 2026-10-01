<?php
declare(strict_types=1);

namespace App\Core;

class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    public function required(string $field, ?string $label = null): self
    {
        $value = $_POST[$field] ?? '';
        if (trim((string)$value) === '') {
            $this->errors[$field] = ($label ?? self::humanize($field)) . ' wajib diisi.';
        }
        return $this;
    }

    public function email(string $field): self
    {
        $value = trim((string)($_POST[$field] ?? ''));
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Format email tidak valid.';
        }
        return $this;
    }

    public function minLength(string $field, int $min, ?string $label = null): self
    {
        $value = (string)($_POST[$field] ?? '');
        if (strlen($value) < $min) {
            $this->errors[$field] = ($label ?? self::humanize($field)) . " minimal {$min} karakter.";
        }
        return $this;
    }

    public function matches(string $field, string $other, string $message = 'Tidak cocok.'): self
    {
        $a = (string)($_POST[$field] ?? '');
        $b = (string)($_POST[$other] ?? '');
        if ($a !== $b) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function integerIn(string $field, array $allowed): self
    {
        $value = $_POST[$field] ?? null;
        if ($value !== null && !in_array((int)$value, $allowed, true)) {
            $this->errors[$field] = 'Nilai tidak valid.';
        }
        return $this;
    }

    public function ok(): bool
    {
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors[array_key_first($this->errors)] ?? null;
    }

    private static function humanize(string $field): string
    {
        return ucfirst(str_replace('_', ' ', $field));
    }
}