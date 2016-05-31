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


class Action extends Component
{

	/** @var string */
	private $label;

	/** @var callable */
	private $callback;

	/** @var string */
	private $confirmation = NULL;


	/**
	 * @param  string $label
	 * @param  callable $callback
	 */
	public function __construct($label, callable $callback)
	{
		parent::__construct();

		$this->label = (string) $label;
		$this->callback = $callback;
	}


	/** @return string */
	public function getLabel()
	{
		return $this->getDataGrid()->translate($this->label);
	}


	/** @return callable */
	public function getCallback()
	{
		return $this->callback;
	}


	/**
	 * @param  string $confirmation
	 * @return RowAction
	 */
	public function setConfirmation($confirmation = NULL)
	{
		$this->confirmation = $confirmation === NULL ? NULL : (string) $confirmation;
		return $this;
	}


	/** @return string|NULL */
	public function getConfirmation()
	{
		return $this->confirmation === NULL ? NULL : $this->getDataGrid()->translate($this->confirmation);
	}

}
