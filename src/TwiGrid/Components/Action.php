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


	public function __construct(string $label, callable $callback)
	{
		parent::__construct();

		$this->label = $label;
		$this->callback = $callback;
	}


	public function getLabel(): string
	{
		return $this->translate($this->label);
	}


	public function getCallback(): callable
	{
		return $this->callback;
	}


	public function setConfirmation(string $confirmation = NULL): self
	{
		$this->confirmation = strlen($confirmation) ? (string) $confirmation : NULL;
		return $this;
	}


	public function getConfirmation(): ?string
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
