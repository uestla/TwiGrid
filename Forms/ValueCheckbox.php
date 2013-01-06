<?php

namespace TwiGrid\Forms;

use Nette\Utils\Html as NHtml;
use Nette\Forms\Controls\Checkbox as NCheckbox;


class ValueCheckbox extends NCheckbox
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



	/** @return NHtml */
	function getControl()
	{
		return parent::getControl()->value( $this->htmlValue );
	}
}
