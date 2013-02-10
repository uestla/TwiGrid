<?php

/**
 * This file is part of the TwiGrid component
 *
 * Copyright (c) 2013 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
 * @link     https://github.com/uestla/twigrid
 */


namespace TwiGrid;

use Nette;


class Record extends Nette\Object
{

	/** @var array */
	protected $primaryKey = NULL;

	/** @var Nette\Callback */
	protected $valueGetter = NULL;

	const PRIMARY_SEPARATOR = '|';



	/**
	 * @param  string|array
	 * @return DataGrid
	 */
	function setPrimaryKey($primaryKey)
	{
		$this->primaryKey = is_array($primaryKey) ? $primaryKey : func_get_args();
		return $this;
	}



	/** @return array */
	function getPrimaryKey()
	{
		return $this->primaryKey;
	}



	/**
	 * @param  mixed
	 * @return DataGrid
	 */
	function setValueGetter($callback)
	{
		$this->valueGetter = Nette\Callback::create($callback);

		return $this;
	}



	/** @return Nette\Callback */
	function getValueGetter()
	{
		if ($this->valueGetter === NULL) {
			$this->setValueGetter(function ($record, $column, $need) {
				if (!isset($record->$column)) {
					if ($need) {
						throw new Nette\InvalidArgumentException("The value of column '$column' not found in the record.");
					}

					return NULL;
				}

				return $record->$column;
			});
		}

		return $this->valueGetter;
	}



	/**
	 * @param  mixed
	 * @param  string
	 * @param  bool
	 * @return mixed
	 */
	function getValue($record, $column, $need = TRUE)
	{
		return $this->getValueGetter()->invokeArgs( array($record, $column, $need) );
	}



	/**
	 * @param  mixed
	 * @return string
	 */
	function primaryToString($record)
	{
		return implode( static::PRIMARY_SEPARATOR, $this->getPrimary($record) );
	}



	/**
	 * @param  string
	 * @return array|string
	 */
	function stringToPrimary($s)
	{
		$primaries = explode( static::PRIMARY_SEPARATOR, $s );
		return count($primaries) === 1 ? (string) $primaries[0] : array_combine( $this->primary, $primaries );
	}



	/**
	 * @param  mixed
	 * @return array
	 */
	protected function getPrimary($record)
	{
		$primaries = array();
		foreach ($this->primaryKey as $column) {
			$primaries[ $column ] = (string) $this->getValue($record, $column); // intentionally string conversion due to later comparison
		}

		return $primaries;
	}

}
