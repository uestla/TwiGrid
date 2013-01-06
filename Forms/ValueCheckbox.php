<?php

namespace TwiGrid\Forms;


class ValueCheckbox extends Nette\Forms\Controls\Checkbox
{
	/** @var mixed */
	protected $htmlValue = NULL;



	/**
	 * @param  mixed
	 * @return ValueCheckbox
	 */
	function setHtmlValue($value)
	{
		$this->htmlValue = $value;
		return $this;
	}



	/** @return mixed */
	function getHtmlValue()
	{
		return $this->htmlValue;
	}
}
