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

use TwiGrid\DataGrid;
use Nette\ComponentModel\Component as NComponent;


abstract class Component extends NComponent
{

	/**
	 * @param  string $s
	 * @param  int $count
	 * @return string
	 */
	protected function translate($s, $count = NULL)
	{
		return $this->lookup(DataGrid::class)->translate($s, $count);
	}

}
