<?php

namespace TwiGrid\Components;

use Nette\Localization\ITranslator as NITranslator;


class Translator implements NITranslator
{

	/** @var array */
	private $dictionary = [
		'twigrid.filters.filter' => 'Filter',
		'twigrid.filters.cancel' => 'Cancel',
		'twigrid.data.no_data' => 'No data.',

		'twigrid.inline.edit' => 'Edit inline',
		'twigrid.inline.edit_confirm' => 'Edit',
		'twigrid.inline.cancel' => 'Cancel',

		'twigrid.group_actions.checked' => 'Checked:',
		'twigrid.group_actions.checked_count_message' => 'Please choose at least one record.',

		'twigrid.pagination.previous' => 'Previous',
		'twigrid.pagination.next' => 'Next',
		'twigrid.pagination.total' => '%d items',
	];


	/**
	 * @param  string $message
	 * @param  int $count
	 * @return string
	 */
	public function translate($message, $count = NULL)
	{
		if (isset($this->dictionary[$message])) {
			$s = $this->dictionary[$message];

		} else {
			$s = $message;
		}

		return sprintf($s, $count);
	}

}
