<?php

/**
 * This file is part of the TwiGrid component
 *
 * @license  MIT
 * @author   Petr Kessler (https://kesspess.cz)
 * @link     https://github.com/uestla/twigrid
 */

namespace TwiGrid\Components;

use Nette\Utils\Callback as NCallback;


class Action extends Component
{

	/** @var string */
	private $label;

	/** @var callable */
	private $callback;

	/** @var string|NULL */
	private $confirmation = NULL;


	/**
	 * @param  string $label
	 * @param  callable $callback
	 */
	public function __construct($label, callable $callback)
	{
		parent::__construct();

		$this->label = $label;
		$this->callback = $callback;
	}


	/** @return string */
	public function getLabel()
	{
		return $this->translate($this->label);
	}


	/** @return callable */
	public function getCallback()
	{
		return $this->callback;
	}


	/**
	 * @param  string $confirmation
	 * @return Action
	 */
	public function setConfirmation($confirmation = NULL)
	{
		$this->confirmation = strlen($confirmation) ? (string) $confirmation : NULL;
		return $this;
	}


	/** @return string|NULL */
	public function getConfirmation()
	{
		if ($this->confirmation === NULL) {
			return NULL;
		}

		return $this->translate($this->confirmation);
	}


	/**
	 * @param  mixed $record
	 * @return mixed
	 */
	public function invoke($record)
	{
		return NCallback::invoke($this->callback, $record);
	}

}
