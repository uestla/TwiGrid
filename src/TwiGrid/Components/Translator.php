<?php

declare(strict_types = 1);

namespace TwiGrid\Components;

use Nette\Localization\ITranslator;


class Translator implements ITranslator
{

	/** @var array<string, string> */
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
		'twigrid.pagination.page' => 'Page',
		'twigrid.pagination.page_required' => 'Please select a page to go to.',
		'twigrid.pagination.page_integer' => 'Page number must be an integer.',
		'twigrid.pagination.page_range' => 'Page number must be greater than 1 and lower or equal to number of pages.',
		'twigrid.pagination.change' => 'Change page',
	];


	/** @param  array<string, string> $dictionary */
	public function __construct(array $dictionary = [])
	{
		$this->addDictionary($dictionary);
	}


	/** @param  array<string, string> $dictionary */
	public function addDictionary(array $dictionary): self
	{
		$this->dictionary = $dictionary + $this->dictionary;
		return $this;
	}


	/**
	 * @param  string $message
	 * @param  string ...$parameters
	 */
	public function translate($message, ...$parameters): string
	{
		return sprintf($this->dictionary[$message] ?? $message, ...$parameters);
	}

}
