<?php

/**
 * This file is part of the TwiGrid component
 *
 * @license  MIT
 * @author   Petr Kessler (https://kesspess.cz)
 * @link     https://github.com/uestla/twigrid
 */

namespace TwiGrid\Components;

use TwiGrid\DataGrid;
use Nette\ComponentModel\IComponent;
use Nette\ComponentModel\Component as NComponent;


abstract class Component extends NComponent
{

	/**
	 * @param  bool $need
	 * @return DataGrid|IComponent
	 *
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
