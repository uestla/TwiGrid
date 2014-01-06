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


class Action extends Component
{

	/** @var string */
	protected $label;

	/** @var \Closure */
	protected $callback;

	/** @var string */
	protected $confirmation = NULL;



	/**
	 * @param  string $label
	 * @param  \Closure $callback
	 */
	function __construct($label, \Closure $callback)
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



	/** @return \Closure */
	function getCallback()
	{
		return $this->callback;
	}



	/**
	 * @param  string $confirmation
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
