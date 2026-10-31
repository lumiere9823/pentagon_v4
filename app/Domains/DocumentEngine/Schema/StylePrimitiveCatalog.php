<?php

namespace App\Domains\DocumentEngine\Schema;

final class StylePrimitiveCatalog
{
    /** @var array<string, array{category: string, type: string, values: list<string>, responsive: bool}>|null */
    private static ?array $definitions = null;

    /**
     * @return array<string, array{category: string, type: string, values: list<string>, responsive: bool}>
     */
    public static function all(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        $path = resource_path('page-builder/style-primitives.json');
        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new \LogicException('Style primitive contract must decode to an object.');
        }

        $definitions = [];
        foreach ($decoded as $id => $definition) {
            if (
                ! is_string($id) || $id === '' ||
                ! is_array($definition) ||
                ! is_string($definition['category'] ?? null) ||
                ! is_string($definition['type'] ?? null) ||
                ! is_array($definition['values'] ?? null) ||
                ! is_bool($definition['responsive'] ?? null)
            ) {
                throw new \LogicException("Invalid style primitive contract for '{$id}'.");
            }

            $values = array_values($definition['values']);
            if ($values === [] || array_filter($values, 'is_string') !== $values) {
                throw new \LogicException("Style primitive '{$id}' must declare string values.");
            }

            $definitions[$id] = [
                'category' => $definition['category'],
                'type' => $definition['type'],
                'values' => $values,
                'responsive' => $definition['responsive'],
            ];
        }

        return self::$definitions = $definitions;
    }

    /** @return array{category: string, type: string, values: list<string>, responsive: bool} */
    public static function get(string $id): array
    {
        return self::all()[$id] ?? throw new \LogicException("Unknown style primitive '{$id}'.");
    }

    public static function isResponsive(string $id): bool
    {
        return self::get($id)['responsive'];
    }

    /** @param array<string, mixed> $schema */
    public static function assertCompatible(string $id, array $schema, string $property): void
    {
        $primitive = self::get($id);
        $type = $schema['type'] ?? 'string';
        $values = $schema['enum'] ?? null;

        if ($type !== $primitive['type'] || $values !== $primitive['values']) {
            throw new \LogicException("Style primitive '{$id}' is incompatible with {$property}.");
        }

        if (array_key_exists('default', $schema) && ! in_array($schema['default'], $primitive['values'], true)) {
            throw new \LogicException("Default for {$property} is outside style primitive '{$id}'.");
        }
    }
}
