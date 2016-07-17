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
	private $primaryKeys = NULL;

	/** @var callable */
	private $valueGetter = NULL;


	const PRIMARY_SEPARATOR = '|';


	/**
	 * @param  string[] $keys
	 * @return DataGrid
	 */
	public function setPrimaryKeys(array $keys)
	{
		$this->primaryKeys = $keys;
		return $this;
	}


	/** @return array */
	public function getPrimaryKeys()
	{
		return $this->primaryKeys;
	}


	/** @return callable */
	public function getValueGetter()
	{
		if ($this->valueGetter === NULL) {
			$this->setPropertyValueGetter();
		}

		return $this->valueGetter;
	}


	/** @return RecordHandler */
	public function setPropertyValueGetter()
	{
		$this->valueGetter = function ($record, $column) {
			return $record->$column;
		};

		return $this;
	}


	/** @return RecordHandler */
	public function setGetterValueGetter()
	{
		$this->valueGetter = function ($record, $column) {
			return $record->{'get' . ucfirst($column)}();
		};

		return $this;
	}


	/**
	 * @param  callable $callback
	 * @return RecordHandler
	 */
	public function setCustomValueGetter(callable $callback)
	{
		$this->valueGetter = $callback;
		return $this;
	}


	/**
	 * @param  mixed $record
	 * @param  string $column
	 * @return mixed
	 */
	public function getValue($record, $column)
	{
		$getter = $this->getValueGetter();
		return $getter($record, $column);
	}


	/**
	 * @param  mixed $record
	 * @return array
	 */
	public function getPrimary($record)
	{
		$primaries = [];
		foreach ($this->primaryKeys as $column) {
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
