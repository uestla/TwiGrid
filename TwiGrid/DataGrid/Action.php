<?php

/**
 * This file is part of the TwiGrid component
 *
 * Copyright (c) 2013 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
 * @link     https://github.com/uestla/twigrid
 */

namespace TwiGrid;

use Nette;


/**
 * @property-read string $label
 * @property-read string $callback
 * @property string $confirmation
 * @property-read DataGrid $grid
 */
class Action extends Nette\ComponentModel\Component
{

	/** @var string */
	protected $label;

	/** @var Nette\Callback */
	protected $callback;

	/** @var string */
	protected $confirmation = NULL;



	/**
	 * @param  string
	 * @param  Nette\Callback
	 * @param  mixed
	 */
	function __construct($label, Nette\Callback $callback)
	{
		parent::__construct();
		$this->label = (string) $label;
		$this->callback = $callback;
	}



	/** @return string */
	function getLabel()
	{
		return $this->getGrid()->translate($this->label);
	}



	/** @return Nette\Callback */
	function getCallback()
	{
		return $this->callback;
	}



	/**
	 * @param  string
	 * @return RowAction
	 */
	function setConfirmation($confirmation = NULL)
	{
		$this->confirmation = $confirmation === NULL ? NULL : (string) $confirmation;
		return $this;
	}



	/** @return string|NULL */
	function getConfirmation()
	{
		return $this->confirmation === NULL ? NULL : $this->getGrid()->translate($this->confirmation);
	}



	/**
	 * @param  bool
	 * @return DataGrid
	 */
	function getGrid($need = TRUE)
	{
		return $this->lookup('TwiGrid\DataGrid', $need);
	}

}
