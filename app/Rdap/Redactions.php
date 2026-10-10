<?php

namespace App\Rdap;

/**
 * Collects RFC 9537 redaction entries while an RDAP response is built.
 */
final class Redactions
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $entries = [];

    /**
     * A member that was removed from the response.
     */
    public function removal(string $name, string $prePath): void
    {
        $this->entries[] = [
            'name' => ['type' => $name],
            'prePath' => $prePath,
            'pathLang' => 'jsonpath',
            'method' => 'removal',
            'reason' => ['description' => (string) config('rdap.redaction_reason')],
        ];
    }

    /**
     * A member that is still present but carries an empty value.
     */
    public function emptyValue(string $name, string $postPath): void
    {
        $this->entries[] = [
            'name' => ['type' => $name],
            'postPath' => $postPath,
            'pathLang' => 'jsonpath',
            'method' => 'emptyValue',
            'reason' => ['description' => (string) config('rdap.redaction_reason')],
        ];
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->entries;
    }
}
