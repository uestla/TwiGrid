<?php

namespace TwiGrid\Forms;

use Nette;


class PrimaryCheckbox extends Nette\Forms\Controls\Checkbox
{
	/** @var string|NULL */
	protected $primary = NULL;



	/**
	 * @param  string
	 * @return PrimaryCheckbox
	 */
	function setPrimary($value)
	{
		$this->primary = (string) $value;
		return $this;
	}



	/** @return string|NULL */
	function getPrimary()
	{
		return $this->primary;
	}
}
