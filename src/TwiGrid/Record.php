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


class Record
{

	/** @var array */
	private $primaryKey = NULL;

	/** @var callable */
	private $valueGetter = NULL;


	const PRIMARY_SEPARATOR = '|';


	/**
	 * @param  string|string[] $key
	 * @return DataGrid
	 */
	public function setPrimaryKey($key)
	{
		$this->primaryKey = is_array($key) ? $key : func_get_args();
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
	 * @return string
	 */
	public function primaryToString($record)
	{
		return implode(static::PRIMARY_SEPARATOR, $this->getPrimary($record));
	}


	/**
	 * @param  mixed $record
	 * @param  array|string $primary
	 * @return bool
	 */
	public function is($record, $primary)
	{
		return $this->primaryToString($record) === $primary;
	}


	/**
	 * @param  string $s
	 * @return array|string
	 */
	public function stringToPrimary($s)
	{
		$primaries = explode(static::PRIMARY_SEPARATOR, $s);

		if (count($primaries) === 1) {
			return $primaries[0];
		}

		return array_combine($this->primary, $primaries);
	}


	/**
	 * @param  mixed $record
	 * @return array
	 */
	protected function getPrimary($record)
	{
		$primaries = [];
		foreach ($this->primaryKey as $column) {
			$primaries[$column] = (string) $this->getValue($record, $column); // intentional string conversion due to later comparison
		}

		return $primaries;
	}

}
