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


/**
 * @template T
 * @template CT
 * @extends Action<T, CT>
 */
class RowAction extends Action
{

	/** @var bool */
	protected $protected = true;


	/** @return RowAction<T, CT> */
	public function setProtected(bool $bool = true): self
	{
		$this->protected = $bool;
		return $this;
	}


	public function isProtected(): bool
	{
		return $this->protected;
	}

}
