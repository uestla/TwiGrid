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
	 * @param  bool $need
	 * @return DataGrid
	 */
	final public function getDataGrid($need = TRUE)
	{
		return $this->lookup(DataGrid::class, $need);
	}


	/**
	 * @param  string $s
	 * @return string
	 */
	protected function translate($s)
	{
		return $this->getDataGrid()->translate($s);
	}

}
