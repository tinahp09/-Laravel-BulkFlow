<?php

declare(strict_types=1);

namespace BulkFlow\Import\Pipeline;

final class HeaderResolver
{
    /**
     * @param  list<string>  $headers
     * @param  array<string, string>  $mapping  source heading => destination attribute
     * @return array<string, string> destination attribute => source heading
     */
    public function resolve(array $headers, array $mapping): array
    {
        $available = [];

        foreach ($headers as $header) {
            $available[$this->normalize($header)] = trim($header);
        }

        $resolved = [];

        foreach ($mapping as $source => $destination) {
            $normalized = $this->normalize($source);

            if (! array_key_exists($normalized, $available)) {
                throw MissingSourceColumn::named($source);
            }

            $resolved[$destination] = $available[$normalized];
        }

        return $resolved;
    }

    private function normalize(string $heading): string
    {
        return mb_strtolower(trim($heading));
    }
}
