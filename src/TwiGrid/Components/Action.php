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


/**
 * @template T
 * @template CT
 * @extends Component<T>
 */
class Action extends Component
{

	/** @var string */
	private $label;

	/** @var callable(CT): void */
	private $callback;

	/** @var string|null */
	private $confirmation;


	/** @param  callable(CT): void $callback */
	public function __construct(string $label, callable $callback)
	{
		$this->label = $label;
		$this->callback = $callback;
	}


	public function getLabel(): string
	{
		return $this->translate($this->label);
	}


	/** @return  callable(CT): void */
	public function getCallback(): callable
	{
		return $this->callback;
	}


	/** @return self<T, CT> */
	public function setConfirmation(?string $confirmation = null): self
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
	 * @param  CT $argument
	 * @return void
	 */
	public function invoke($argument)
	{
		call_user_func($this->callback, $argument);
	}

}
