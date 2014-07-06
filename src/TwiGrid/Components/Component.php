<?php

/**
 * This file is part of the TwiGrid component
 *
 * Copyright (c) 2013, 2014 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
 * @link     https://github.com/uestla/twigrid
 */

namespace TwiGrid\Components;

use Nette;
use TwiGrid\DataGrid;


/** @property-read DataGrid $dataGrid */
abstract class Component extends Nette\ComponentModel\Component
{

	/**
	 * @param  bool $need
	 * @return DataGrid
	 */
	final function getDataGrid($need = TRUE)
	{
		return $this->lookup('TwiGrid\DataGrid', $need);
	}

}
