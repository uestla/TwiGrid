<?php

/**
 * This file is part of the TwiGrid component
 *
 * Copyright (c) 2013-2016 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
 * @link     https://github.com/uestla/twigrid
 */

namespace TwiGrid\Components;


class RowAction extends Action
{

	/** @var bool */
	protected $protected = TRUE;


	/**
	 * @param  bool $bool
	 * @return RowAction
	 */
	public function setProtected($bool = TRUE)
	{
		$this->protected = (bool) $bool;
		return $this;
	}


	/** @return bool */
	public function isProtected()
	{
		return $this->protected;
	}

}
