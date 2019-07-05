<?php

declare(strict_types = 1);

/**
 * This file is part of the TwiGrid component
 *
 * @license  MIT
 * @author   Petr Kessler (https://kesspess.cz)
 * @link     https://github.com/uestla/twigrid
 */

namespace TwiGrid;


/** @internal */
class RecordHandler
{

	/** @var array|null */
	private $primaryKey;

	/** @var callable|null */
	private $valueGetter;


	const PRIMARY_SEPARATOR = '|';


	public function setPrimaryKey(array $keys): self
	{
		$this->primaryKey = $keys;
		return $this;
	}


	public function getPrimaryKey(): array
	{
		return $this->primaryKey;
	}


	public function setValueGetter(callable $callback): self
	{
		$this->valueGetter = $callback;
		return $this;
	}


	public function getValueGetter(): callable
	{
		if ($this->valueGetter === null) {
			$this->valueGetter = function ($record, $column, $need) {
				if (!isset($record->$column)) {
					if ($need) {
						throw new \InvalidArgumentException("Field '$column' not found in record of type "
							. (is_object($record) ? get_class($record) : gettype($record)) . ".");
					}

					return null;
				}

				return $record->$column;
			};
		}

		return $this->valueGetter;
	}


	/**
	 * @param  mixed $record
	 * @param  string $column
	 * @param  bool $need
	 * @return mixed
	 */
	public function getValue($record, string $column, bool $need = true)
	{
		$getter = $this->getValueGetter();
		return $getter($record, $column, $need);
	}


	/**
	 * @param  mixed $record
	 * @return array
	 */
	public function getPrimary($record): array
	{
		$primaries = [];
		foreach ($this->primaryKey as $column) {
			$primaries[$column] = (string) $this->getValue($record, $column);
		}

		return $primaries;
	}


	/**
	 * @param  mixed $record
	 * @return string
	 */
	public function getPrimaryHash($record): string
	{
		return substr(sha1(implode(static::PRIMARY_SEPARATOR, $this->getPrimary($record))), 0, 8);
	}


	/**
	 * @param  string $primary
	 * @param  array|\Traversable $data
	 * @return mixed|null
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


	/**
	 * @param  mixed $record
	 * @param  string|null $primary
	 * @return bool
	 */
	public function is($record, ?string $primary): bool
	{
		return $this->getPrimaryHash($record) === $primary;
	}

}
