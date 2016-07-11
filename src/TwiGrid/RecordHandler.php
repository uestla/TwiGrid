<?php

/**
 * This file is part of the TwiGrid component
 *
 * Copyright (c) 2013-2016 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
 * @link     https://github.com/uestla/twigrid
 */

namespace TwiGrid;


class RecordHandler
{

	/** @var array */
	private $primaryKey = NULL;

	/** @var callable */
	private $valueGetter = NULL;


	const PRIMARY_SEPARATOR = '|';


	/**
	 * @param  string[] $keys
	 * @return DataGrid
	 */
	public function setPrimaryKeys(array $keys)
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
	 * @return DataGrid
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
			$primaries[$column] = (string) $this->getValue($record, $column); // intentional string conversion due to later comparison
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
	 * @param  string $hash
	 * @param  array|\Traversable $data
	 * @return mixed|NULL
	 */
	public function findIn($hash, $data)
	{
		foreach ($data as $record) {
			if (strcmp($hash, $this->getPrimaryHash($record)) === 0) {
				return $record;
			}
		}

		return NULL;
	}


	/**
	 * @param  mixed $record
	 * @param  string $hash
	 * @return bool
	 */
	public function is($record, $hash)
	{
		return strcmp($this->getPrimaryHash($record), $hash) === 0;
	}

}
