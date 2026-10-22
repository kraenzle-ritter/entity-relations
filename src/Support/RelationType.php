<?php

namespace KraenzleRitter\EntityRelations\Support;

final readonly class RelationType
{
    /**
     * @param  list<string>  $pairs  "source:target", either side may be "*"
     * @param  array<string, string|array{0: string, 1: string}>  $labels  locale => label, or [forward, inverse]
     * @param  array<string, string>  $mappings  vocabulary prefix => property, e.g. ['skos' => 'skos:broader']
     */
    public function __construct(
        public string $name,
        public ?string $inverse = null,
        public bool $symmetric = false,
        public bool $hierarchical = false,
        public bool $temporal = false,
        public array $pairs = [],
        public array $labels = [],
        public array $mappings = [],
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $name, array $config): self
    {
        $mappings = [];
        foreach (['skos', 'rico'] as $vocabulary) {
            if (! empty($config[$vocabulary])) {
                $mappings[$vocabulary] = $config[$vocabulary];
            }
        }

        return new self(
            name: $name,
            inverse: $config['inverse'] ?? null,
            symmetric: (bool) ($config['symmetric'] ?? false),
            hierarchical: (bool) ($config['hierarchical'] ?? false),
            temporal: (bool) ($config['temporal'] ?? false),
            pairs: array_values($config['pairs'] ?? []),
            labels: $config['labels'] ?? [],
            mappings: $mappings,
        );
    }

    public function allows(string $source, string $target): bool
    {
        foreach ($this->pairs as $pair) {
            [$s, $t] = array_pad(explode(':', $pair, 2), 2, '*');
            if (($s === '*' || $s === $source) && ($t === '*' || $t === $target)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Label as seen from the source (forward) or from the target (inverse).
     * Falls back to the fallback locale, then to the first label, then to the name.
     */
    public function label(string $locale, bool $inverse = false, ?string $fallback = null): string
    {
        $label = $this->labels[$locale]
            ?? ($fallback !== null ? ($this->labels[$fallback] ?? null) : null)
            ?? ($this->labels === [] ? null : $this->labels[array_key_first($this->labels)]);

        if ($label === null) {
            return $inverse && ! $this->symmetric ? ($this->inverse ?? $this->name) : $this->name;
        }

        if (is_array($label)) {
            return $inverse && ! $this->symmetric ? $label[1] : $label[0];
        }

        return $label;
    }

    public function mapping(string $vocabulary): ?string
    {
        return $this->mappings[$vocabulary] ?? null;
    }
}
