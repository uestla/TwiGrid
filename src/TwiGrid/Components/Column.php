<?php

declare(strict_types = 1);

/**
 * This file is part of the TwiGrid component
 *
 * @license  MIT
 * @author   Petr Kessler (https://kesspess.cz)
 * @link     https://github.com/uestla/TwiGrid
 */

namespace TwiGrid\Components;


class Column extends Component
{

	/** @var string */
	private $label;

	/** @var bool */
	private $sortable = false;

	/** @var bool */
	private $sortedBy = false;

	/** @var bool */
	private $sortDir = self::ASC;

	/** @var int */
	private $sortIndex;


	const ASC = false;
	const DESC = true;


	public function __construct(string $label)
	{
		$this->label = $label;
	}


	public function getLabel(): string
	{
		return $this->translate($this->label);
	}


	public function setSortable(bool $bool = true): self
	{
		$this->sortable = $bool;
		return $this;
	}


	public function isSortable(): bool
	{
		return $this->sortable;
	}


	public function setSortedBy(bool $bool = true, bool $sortDir = self::ASC, int $sortIndex = 0): self
	{
		if (!$this->sortable) {
			throw new \RuntimeException("Column '{$this->getName()}' is not sortable.");
		}

		$this->sortedBy = $bool;
		$this->sortIndex = $sortIndex;
		$this->sortDir = $bool && $sortDir;
		return $this;
	}


	public function isSortedBy(): bool
	{
		return $this->sortedBy;
	}


	public function getSortDir(): bool
	{
		return $this->sortDir;
	}


	public function getSortIndex(): int
	{
		return $this->sortIndex;
	}

}
