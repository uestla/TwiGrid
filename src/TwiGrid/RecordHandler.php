<?php

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

	/** @var array|NULL */
	private $primaryKey = NULL;

	/** @var callable|NULL */
	private $valueGetter = NULL;


	const PRIMARY_SEPARATOR = '|';


	/**
	 * @param  string[] $keys
	 * @return RecordHandler
	 */
	public function setPrimaryKey(array $keys)
	{
		$this->primaryKey = $keys;
		return $this;
	}


	/** @return array */
	public function getPrimaryKey()
	{
		return $this->primaryKey;
	}


	/**
	 * @param  callable $callback
	 * @return RecordHandler
	 */
	public function setValueGetter(callable $callback)
	{
		$this->valueGetter = $callback;
		return $this;
	}


	/** @return callable */
	public function getValueGetter()
	{
		if ($this->valueGetter === NULL) {
			$this->valueGetter = function ($record, $column, $need) {
				if (!isset($record->$column)) {
					if ($need) {
						throw new \InvalidArgumentException("Field '$column' not found in record of type "
							. (is_object($record) ? get_class($record) : gettype($record)) . ".");
					}

					return NULL;
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
	public function getValue($record, $column, $need = TRUE)
	{
		$getter = $this->getValueGetter();
		return $getter($record, $column, $need);
	}


	/**
	 * @param  mixed $record
	 * @return array
	 */
	public function getPrimary($record)
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
	public function getPrimaryHash($record)
	{
		return substr(sha1(implode(static::PRIMARY_SEPARATOR, $this->getPrimary($record))), 0, 8);
	}


	/**
	 * @param  string $primary
	 * @param  array|\Traversable $data
	 * @return mixed|NULL
	 */
	public function findIn($primary, $data)
	{
		foreach ($data as $record) {
			if ($this->is($record, $primary)) {
				return $record;
			}
		}

		return NULL;
	}


	/**
	 * @param  mixed $record
	 * @param  string $primary
	 * @return bool
	 */
	public function is($record, $primary)
	{
		return $this->getPrimaryHash($record) === $primary;
	}

}
