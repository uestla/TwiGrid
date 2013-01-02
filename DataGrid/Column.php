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


class Column extends Nette\ComponentModel\Component
{
	/** @var string */
	protected $label;

	/** @var bool */
	protected $sortable = FALSE;

	/** @var bool */
	protected $orderedBy = FALSE;

	/** @var bool */
	protected $orderedDesc = FALSE;



	/** @param  string */
	function __construct($label)
	{
		parent::__construct();

		$this->monitor('TwiGrid\\DataGrid');
		$this->label = (string) $label;
	}



	/** @return string */
	function getLabel()
	{
		return $this->label;
	}



	/**
	 * @param  bool
	 * @return Column
	 */
	function setSortable($bool = TRUE)
	{
		$this->sortable = (bool) $bool;
		return $this;
	}



	/** @return bool */
	function isSortable()
	{
		return $this->sortable;
	}



	/**
	 * @param  bool
	 * @param  bool
	 * @return Column
	 */
	function setOrderedBy($bool = TRUE, $orderedDesc = FALSE)
	{
		if (!$this->sortable) {
			throw new Nette\InvalidStateException("Column '{$this->name}' is not sortable.");
		}

		$this->orderedBy = (bool) $bool;
		$this->orderedDesc = $bool && (bool) $orderedDesc;
		return $this;
	}



	/** @return bool */
	function isOrderedBy()
	{
		return $this->orderedBy;
	}



	/** @return bool */
	function isOrderedDesc()
	{
		return $this->orderedDesc;
	}
}
