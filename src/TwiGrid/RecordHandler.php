<?php

declare(strict_types = 1);

/**
 * This file is part of the TwiGrid component
 *
 * @license  MIT
 * @author   Petr Kessler (https://kesspess.cz)
 * @link     https://github.com/uestla/TwiGrid
 */

namespace TwiGrid;


/**
 * @template T
 * @internal
 */
class RecordHandler
{

	/** @var string[]|null */
	private $primaryKey;

	/** @var callable(T, string, bool): mixed|null */
	private $valueGetter;


	/** @var string */
	const PRIMARY_SEPARATOR = '|';


	/**
	 * @param  string[] $keys
	 * @return self<T>
	 */
	public function setPrimaryKey(array $keys): self
	{
		$this->primaryKey = $keys;
		return $this;
	}


	/** @return string[]|null */
	public function getPrimaryKey(): ?array
	{
		return $this->primaryKey;
	}


	/**
	 * @param  callable(T, string, bool): mixed|null $callback
	 * @return self<T>
	 */
	public function setValueGetter(?callable $callback): self
	{
		$this->valueGetter = $callback;
		return $this;
	}


	/** @return callable(T, string, bool): mixed */
	public function getValueGetter(): callable
	{
		if ($this->valueGetter === null) {
			$this->valueGetter = static function ($record, $column, $need) {
				/** @var T $record */
				/** @var string $column */
				/** @var bool $need */

				if (is_array($record) && !array_key_exists($column, $record)
						|| (is_object($record) && !isset($record->$column))) {
					if ($need) {
						throw new \InvalidArgumentException("Field '$column' not found in record of type "
							. (is_object($record) ? get_class($record) : gettype($record)) . ".");
					}

					return null;
				}

				return is_array($record) ? ($record[$column] ?? null) : $record->$column;
			};
		}

		return $this->valueGetter;
	}


	/**
	 * @param  T $record
	 * @return mixed
	 */
	public function getValue($record, string $column, bool $need = true)
	{
		$getter = $this->getValueGetter();
		return $getter($record, $column, $need);
	}


	/**
	 * @param  T $record
	 * @return array<string, int|string>
	 */
	public function getPrimary($record): array
	{
		if ($this->primaryKey === null) {
			throw new \LogicException('Primary key not set.');
		}

		$primaries = [];
		foreach ($this->primaryKey as $column) {
			$value = $this->getValue($record, $column);
			assert(is_int($value) || is_string($value));

			$primaries[$column] = $value;
		}

		return $primaries;
	}


	/** @param  T $record */
	public function getPrimaryHash($record): string
	{
		return substr(sha1(implode(static::PRIMARY_SEPARATOR, $this->getPrimary($record))), 0, 8);
	}


	/**
	 * @param  iterable<T> $data
	 * @return T|null
	 */
	public function findIn(string $primary, $data)
	{
		foreach ($data as $record) {
			if ($this->is($record, $primary)) {
				return $record;
			}
		}

		return null;
	}


	/** @param  T $record */
	public function is($record, ?string $primary): bool
	{
		return $this->getPrimaryHash($record) === $primary;
	}

}
