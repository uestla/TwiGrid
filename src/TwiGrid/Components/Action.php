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


class Action extends Component
{

	/** @var string */
	private $label;

	/** @var callable */
	private $callback;

	/** @var string|null */
	private $confirmation;


	public function __construct(string $label, callable $callback)
	{
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


	public function setConfirmation(string $confirmation = null): self
	{
		$this->confirmation = $confirmation === '' ? null : $confirmation;
		return $this;
	}


	public function getConfirmation(): ?string
	{
		if ($this->confirmation === null) {
			return null;
		}

		return $this->translate($this->confirmation);
	}


	public function hasConfirmation(): bool
	{
		return $this->confirmation !== null;
	}


	/**
	 * @param  mixed $record
	 * @return mixed
	 */
	public function invoke($record)
	{
		return call_user_func($this->callback, $record);
	}

}
