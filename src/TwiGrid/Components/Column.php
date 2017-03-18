<?php

/**
 * This file is part of the TwiGrid component
 *
 * @license  MIT
 * @author   Petr Kessler (https://kesspess.cz)
 * @link     https://github.com/uestla/twigrid
 */

namespace TwiGrid\Components;


class Column extends Component
{

	/** @var string */
	private $label;

	/** @var bool */
	private $sortable = FALSE;

	/** @var bool */
	private $sortedBy = FALSE;

	/** @var bool */
	private $sortDir = self::ASC;

	/** @var int */
	private $sortIndex;


	const ASC = FALSE;
	const DESC = TRUE;


	/** @param  string $label */
	public function __construct($label)
	{
		parent::__construct();

		$this->label = $label;
	}


	/** @return string */
	public function getLabel()
	{
		return $this->translate($this->label);
	}


	/**
	 * @param  bool $bool
	 * @return Column
	 */
	public function setSortable($bool = TRUE)
	{
		$this->sortable = (bool) $bool;
		return $this;
	}


	/** @return bool */
	public function isSortable()
	{
		return $this->sortable;
	}


	/**
	 * @param  bool $bool
	 * @param  bool $sortDir
	 * @param  int $sortIndex
	 * @return Column
	 */
	public function setSortedBy($bool = TRUE, $sortDir = self::ASC, $sortIndex = 0)
	{
		if (!$this->sortable) {
			throw new \RuntimeException("Column '{$this->getName()}' is not sortable.");
		}

		$this->sortIndex = $sortIndex;
		$this->sortedBy = (bool) $bool;
		$this->sortDir = $bool && $sortDir;
		return $this;
	}


	/** @return bool */
	public function isSortedBy()
	{
		return $this->sortedBy;
	}


	/** @return bool */
	public function getSortDir()
	{
		return $this->sortDir;
	}


	/** @return int */
	public function getSortIndex()
	{
		return $this->sortIndex;
	}

}
