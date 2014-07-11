<?php

/**
 * This file is part of the TwiGrid component
 *
 * Copyright (c) 2013, 2014 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
 * @link     https://github.com/uestla/twigrid
 */

namespace TwiGrid;

use Nette;
use Nette\Utils\Callback as NCallback;


/**
 * @property array $primaryKey
 * @property \Closure $valueGetter
 */
class Record extends Nette\Object
{

	/** @var array */
	private $primaryKey = NULL;

	/** @var \Closure */
	private $valueGetter = NULL;


	const PRIMARY_SEPARATOR = '|';


	/**
	 * @param  string|array $key
	 * @return DataGrid
	 */
	function setPrimaryKey($key)
	{
		$this->primaryKey = is_array($key) ? $key : func_get_args();
		return $this;
	}


	/** @return array */
	function getPrimaryKey()
	{
		return $this->primaryKey;
	}


	/**
	 * @param  mixed $callback
	 * @return DataGrid
	 */
	function setValueGetter($callback)
	{
		$this->valueGetter = NCallback::closure($callback);
		return $this;
	}


	/** @return \Closure */
	function getValueGetter()
	{
		$this->valueGetter === NULL && $this->setValueGetter(function ($record, $column, $need) {
			if (!isset($record->$column)) {
				if ($need) {
					throw new Nette\InvalidArgumentException("The value of column '$column' not found in the record.");
				}

				return NULL;
			}

			return $record->$column;
		});

		return $this->valueGetter;
	}


	/**
	 * @param  mixed $record
	 * @param  string $column
	 * @param  bool $need
	 * @return mixed
	 */
	function getValue($record, $column, $need = TRUE)
	{
		return NCallback::invoke($this->getValueGetter(), $record, $column, $need);
	}


	/**
	 * @param  mixed $record
	 * @return string
	 */
	function primaryToString($record)
	{
		return implode(static::PRIMARY_SEPARATOR, $this->getPrimary($record));
	}


	/**
	 * @param  mixed $record
	 * @param  array|string $primary
	 * @return bool
	 */
	function is($record, $primary)
	{
		return $this->primaryToString($record) === $primary;
	}


	/**
	 * @param  string $s
	 * @return array|string
	 */
	function stringToPrimary($s)
	{
		$primaries = explode(static::PRIMARY_SEPARATOR, $s);
		return count($primaries) === 1 ? (string) $primaries[0] : array_combine($this->primary, $primaries);
	}


	/**
	 * @param  mixed $record
	 * @return array
	 */
	protected function getPrimary($record)
	{
		$primaries = array();
		foreach ($this->primaryKey as $column) {
			$primaries[$column] = (string) $this->getValue($record, $column); // intentional string conversion due to later comparison
		}

		return $primaries;
	}

}
