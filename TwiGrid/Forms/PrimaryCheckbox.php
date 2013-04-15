<?php

/**
 * This file is part of the TwiGrid component
 *
 * Copyright (c) 2013 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
 * @link     https://github.com/uestla/twigrid
 */

namespace TwiGrid\Forms;

use Nette;


class PrimaryCheckbox extends Nette\Forms\Controls\Checkbox
{

	/** @var string|NULL */
	protected $primary = NULL;



	/**
	 * @param  string
	 * @return PrimaryCheckbox
	 */
	function setPrimary($value)
	{
		$this->primary = (string) $value;
		return $this;
	}



	/** @return string|NULL */
	function getPrimary()
	{
		return $this->primary;
	}

}
