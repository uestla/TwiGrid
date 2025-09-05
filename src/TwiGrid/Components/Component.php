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

use TwiGrid\DataGrid;


/** @template T */
abstract class Component extends \Nette\ComponentModel\Component
{

	/** @return DataGrid<T> */
	final public function getDataGrid(bool $need = true): DataGrid
	{
		/** @var DataGrid<T> $datagrid */
		$datagrid = $this->lookup(DataGrid::class, $need);

		return $datagrid;
	}


	protected function translate(string $s): string
	{
		return $this->getDataGrid()->translate($s);
	}

}
