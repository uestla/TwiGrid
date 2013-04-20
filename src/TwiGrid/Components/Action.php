<?php

/**
 * This file is part of the TwiGrid component
 *
 * Copyright (c) 2013 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
 * @link     https://github.com/uestla/twigrid
 */

namespace TwiGrid\Components;

use Nette;


/**
 * @property-read string $label
 * @property-read string $callback
 * @property string $confirmation
 * @property-read DataGrid $grid
 */
class Action extends DataGridComponent
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
		return $this->getDataGrid()->translate($this->label);
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
		return $this->confirmation === NULL ? NULL : $this->getDataGrid()->translate($this->confirmation);
	}

}
