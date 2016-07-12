<?php

/**
 * This file is part of the TwiGrid component
 *
 * Copyright (c) 2013-2016 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
 * @link     https://github.com/uestla/twigrid
 */

namespace TwiGrid;

use Nette\Forms\Controls\Checkbox;
use Nette\Forms\Controls\SubmitButton;
use Nette\Application\UI\Form as NForm;
use Nette\Utils\ArrayHash as NArrayHash;
use Nette\Forms\Container as NContainer;


class Form extends NForm
{

	/** @var RecordHandler */
	private $recordHandler;


	/** @param  RecordHandler $handler */
	public function __construct(RecordHandler $handler)
	{
		parent::__construct();
		$this->recordHandler = $handler;
	}


	/**
	 * @param  callable $factory
	 * @param  array $defaults
	 * @return Form
	 */
	public function addFilterCriteria(callable $factory, array $defaults)
	{
		if ($this->lazyCreateContainer('filters', 'criteria', $criteria, $factory)) {
			$criteria->setDefaults($defaults);
		}

		$this['filters']['buttons']['filter']->setValidationScope([$this['filters']['criteria']]);
		return $this;
	}


	/**
	 * @param  bool $hasFilters
	 * @return Form
	 */
	public function addFilterButtons($hasFilters)
	{
		if ($this->lazyCreateContainer('filters', 'buttons', $buttons)) {
			$buttons->addSubmit('filter', 'Filter');

			if ($hasFilters) {
				$buttons->addSubmit('reset', 'Cancel')->setValidationScope([]);
			}
		}

		return $this;
	}


	/** @return array|NULL */
	public function getFilterCriteria()
	{
		$this->validate();
		if ($this->isValid()) {
			return Helpers::filterEmpty($this['filters']['criteria']->getValues(TRUE));
		}

		return NULL;
	}


	/** @return Form */
	public function addGroupActionCheckboxes()
	{
		if ($this->lazyCreateContainer('actions', 'records', $records)) {
			$i = 0;
			foreach ($this->getParent()->getData() as $record) {
				$hash = $this->recordHandler->getPrimaryHash($record);
				$records[$hash] = $checkbox = new Checkbox;

				if ($i++ === 0) {
					$checkbox->addRule(__CLASS__ . '::validateCheckedCount', 'Choose at least one record.');
				}

			}
		}

		foreach ($this['actions']['buttons']->getComponents() as $button) {
			$button->setValidationScope([$this['actions']['records']]);
		}

		return $this;
	}


	/**
	 * @param  \ArrayIterator $actions
	 * @return Form
	 */
	public function addGroupActionButtons(\ArrayIterator $actions)
	{
		if ($this->lazyCreateContainer('actions', 'buttons', $buttons)) {
			foreach ($actions as $name => $action) {
				$buttons->addSubmit($name, $action->getLabel());
			}
		}

		return $this;
	}


	/** @return array|NULL */
	public function getCheckedRecords()
	{
		$this->addGroupActionCheckboxes();

		$this->validate();
		if ($this->isValid()) {
			return array_keys(array_filter($this['actions']['records']->getValues(TRUE)));
		}

		return NULL;
	}


	/**
	 * @param  array|\Traversable $data
	 * @param  callable $containerFactory
	 * @param  string|NULL $ieHash
	 * @return Form
	 */
	public function addInlineEditControls($data, callable $containerFactory, $ieHash)
	{
		if ($this->lazyCreateContainer('inline', 'buttons', $buttons)) {
			foreach ($data as $record) {
				if ($this->recordHandler->is($record, $ieHash)) {
					$this['inline']['values'] = $containerFactory($record);
					$buttons->addSubmit('edit', 'Edit')
							->setValidationScope([$this['inline']['values']]);

					$buttons->addSubmit('cancel', 'Cancel')->setValidationScope([]);

				} else {
					$hash = $this->recordHandler->getPrimaryHash($record);
					$button = new SubmitButton('Edit inline');
					$button->setValidationScope([]);
					$buttons[$hash] = $button;
				}

			}
		}

		return $this;
	}


	/** @return NArrayHash|NULL */
	public function getInlineValues()
	{
		$this->validate();
		return $this->isValid() ? $this['inline']['values']->getValues() : NULL;
	}


	/**
	 * @param  int $current
	 * @param  int $pageCount
	 * @return Form
	 */
	public function addPaginationControls($current, $pageCount)
	{
		if ($this->lazyCreateContainer('pagination', 'controls', $controls)) {
			$pages = range(1, $pageCount);

			$controls->addSelect('page', 'Page', array_combine($pages, $pages))
				->setRequired('Please select a page to go to.')
				->setDefaultValue($current);
		}

		if ($this->lazyCreateContainer('pagination', 'buttons', $buttons)) {
			$buttons->addSubmit('change', 'Change page')
				->setValidationScope([$this['pagination']['controls']]);
		}

		return $this;
	}


	/** @return int */
	public function getPage()
	{
		return (int) $this['pagination']['controls']['page']->getValue();
	}


	/**
	 * @param  string $parent
	 * @param  string $name
	 * @param  NContainer $container
	 * @param  callable $factory
	 * @return bool has the container been created?
	 */
	protected function lazyCreateContainer($parent, $name, NContainer & $container = NULL, callable $factory = NULL)
	{
		if (!isset($this[$parent])) {
			$this->addContainer($parent);
		}

		if (!isset($this[$parent][$name])) {
			if ($factory !== NULL) {
				$subcontainer = $factory();

				if (!$subcontainer instanceof NContainer) {
					throw new \RuntimeException("Container factory is expected to return " . NContainer::class . ", '"
							. (is_object($subcontainer) ? get_class($subcontainer) : gettype($subcontainer)) . "' given.");
				}

				$this[$parent][$name] = $subcontainer;

			} else {
				$this[$parent]->addContainer($name);
			}

			$created = TRUE;
		}

		$container = $this[$parent][$name];
		return isset($created);
	}


	/**
	 * @param  Checkbox $checkbox
	 * @return bool
	 */
	public static function validateCheckedCount(Checkbox $checkbox)
	{
		return $checkbox->getForm()->isSubmitted()->getParent()->lookupPath(NForm::class) !== 'actions-buttons'
				|| in_array(TRUE, $checkbox->getParent()->getValues(TRUE), TRUE);
	}

}
