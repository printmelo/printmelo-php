<?php

declare(strict_types=1);

namespace Printmelo\Resources;

/**
 * An object from the API. Read its fields as properties (`$job->state`) or as an array (`$job['state']`).
 * Fields the API adds later are available too, without an SDK update.
 *
 * @implements \ArrayAccess<string, mixed>
 */
abstract class Resource implements \ArrayAccess, \JsonSerializable
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    final public function __construct(protected array $attributes)
    {
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<static>
     */
    public static function collect(array $items): array
    {
        return array_map(static fn (array $item) => new static($item), array_values($items));
    }

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Printmelo resources are read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Printmelo resources are read-only.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->attributes;
    }

    /**
     * A date field as an immutable date, or null when it is empty.
     */
    public function date(string $field): ?\DateTimeImmutable
    {
        $value = $this->attributes[$field] ?? null;

        return is_string($value) && $value !== '' ? new \DateTimeImmutable($value) : null;
    }
}
