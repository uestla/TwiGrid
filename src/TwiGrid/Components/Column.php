<?php declare(strict_types=1);

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


	public function __construct(string $label)
	{
		parent::__construct();

		$this->label = $label;
	}


	public function getLabel(): string
	{
		return $this->translate($this->label);
	}


	public function setSortable(bool $bool = TRUE): self
	{
		$this->sortable = $bool;
		return $this;
	}


	public function isSortable(): bool
	{
		return $this->sortable;
	}


	public function setSortedBy(bool $bool = TRUE, bool $sortDir = self::ASC, int $sortIndex = 0): self
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
