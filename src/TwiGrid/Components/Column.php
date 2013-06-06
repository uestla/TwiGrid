<?php

/**
 * This file is part of the TwiGrid component
 *
 * Copyright (c) 2013 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
 * @link     https://github.com/uestla/twigrid
 */

namespace TwiGrid\Components;

use Nette;


/**
 * @property-read string $label
 * @property-read bool $sortable
 * @property-read bool $orderedBy
 * @property-read bool $sortDir
 * @property-read int $sortIndex
 */
class Column extends Component
{

	/** @var string */
	protected $label;

	/** @var bool */
	protected $sortable = FALSE;

	/** @var bool */
	protected $orderedBy = FALSE;

	/** @var bool */
	protected $sortDir = self::ASC;

	/** @var int */
	protected $sortIndex;

	const ASC = FALSE;
	const DESC = TRUE;



	/** @param  string $label */
	function __construct($label)
	{
		parent::__construct();
		$this->label = (string) $label;
	}



	/** @return string */
	function getLabel()
	{
		return $this->getDataGrid()->translate($this->label);
	}



	/**
	 * @param  bool $bool
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
	 * @param  bool $bool
	 * @param  bool $sortDir
	 * @param  int $sortIndex
	 * @return Column
	 */
	function setOrderedBy($bool = TRUE, $sortDir = self::ASC, $sortIndex = 0)
	{
		if (!$this->sortable) {
			throw new Nette\InvalidStateException("Column '{$this->name}' is not sortable.");
		}

		$this->orderedBy = (bool) $bool;
		$this->sortDir = $bool && (bool) $sortDir;
		$this->sortIndex = (int) $sortIndex;
		return $this;
	}



	/** @return bool */
	function isOrderedBy()
	{
		return $this->orderedBy;
	}



	/** @return bool */
	function getSortDir()
	{
		return $this->sortDir;
	}



	/** @return int */
	function getSortIndex()
	{
		return $this->sortIndex;
	}

}
