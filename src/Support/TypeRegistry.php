<?php

namespace KraenzleRitter\EntityRelations\Support;

final class TypeRegistry
{
    /** @var array<string, RelationType> */
    private array $types = [];

    /** @var array<string, string> inverse name => canonical name */
    private array $inverses = [];

    /**
     * @param  array<string, array<string, mixed>>  $types
     * @param  array<string, string>  $aliases
     */
    public function __construct(array $types, private array $aliases = [])
    {
        foreach ($types as $name => $config) {
            $type = RelationType::fromConfig($name, $config);
            $this->types[$name] = $type;
            if ($type->inverse !== null && ! $type->symmetric) {
                $this->inverses[$type->inverse] = $name;
            }
        }
    }

    public function get(string $name): ?RelationType
    {
        return $this->types[$name] ?? null;
    }

    /** @return array<string, RelationType> */
    public function all(): array
    {
        return $this->types;
    }

    /** @return list<RelationType> */
    public function hierarchical(): array
    {
        return array_values(array_filter($this->types, fn (RelationType $t) => $t->hierarchical));
    }

    /**
     * Resolves a name as written (canonical, inverse or alias) to its type.
     * `reversed` is true when the name is the inverse, i.e. source and target
     * have to be swapped to store it under the canonical name.
     */
    public function resolve(string $name): ?ResolvedName
    {
        $name = $this->aliases[$name] ?? $name;

        if (isset($this->types[$name])) {
            return new ResolvedName($this->types[$name], false);
        }

        if (isset($this->inverses[$name])) {
            return new ResolvedName($this->types[$this->inverses[$name]], true);
        }

        return null;
    }

    /**
     * Names the user may choose for a relation from $source to $target,
     * as name => label. Includes inverse names, so that a relation can be
     * entered from either end.
     *
     * @return array<string, string>
     */
    public function options(string $source, string $target, string $locale, ?string $fallback = null): array
    {
        $options = [];

        foreach ($this->types as $type) {
            if ($type->allows($source, $target)) {
                $options[$type->name] = $type->label($locale, false, $fallback);
            }
            if ($type->symmetric || $type->inverse === null) {
                continue;
            }
            if ($type->allows($target, $source) && ! isset($options[$type->inverse])) {
                $options[$type->inverse] = $type->label($locale, true, $fallback);
            }
        }

        return $options;
    }

    /**
     * Entity keys that may be the target of a relation from $source.
     *
     * @param  list<string>  $entities
     * @return list<string>
     */
    public function targets(string $source, array $entities): array
    {
        return array_values(array_filter(
            $entities,
            fn (string $target) => $this->options($source, $target, 'en') !== [],
        ));
    }
}
