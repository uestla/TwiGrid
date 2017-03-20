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

	/** @return IComponent|DataGrid */
	final public function getDataGrid(bool $need = TRUE)
	{
		return $this->lookup(DataGrid::class, $need);
	}


	protected function translate(string $s): string
	{
		return $this->getDataGrid()->translate($s);
	}

}
