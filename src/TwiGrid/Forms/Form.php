<?php

/**
 * This file is part of the TwiGrid component
 *
 * Copyright (c) 2013, 2014 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
 * @link     https://github.com/uestla/twigrid
 */

namespace TwiGrid\Forms;

use Nette;
use TwiGrid\Record;
use TwiGrid\Helpers;
use Nette\Utils\Callback as NCallback;


class Form extends Nette\Application\UI\Form
{

	/**
	 * @param  callable $factory
	 * @param  array $defaults
	 * @return Form
	 */
	public function addFilterCriteria(callable $factory, array $defaults)
	{
		if (!$this->lazyCreateContainer('filters', 'criteria', $criteria, $factory)) {
			$criteria->setDefaults($defaults);
		}

		$this['filters']['buttons']['filter']->setValidationScope(array($this['filters']['criteria']));
		return $this;
	}


	/**
	 * @param  bool $hasFilters
	 * @return Form
	 */
	public function addFilterButtons($hasFilters)
	{
		if (!$this->lazyCreateContainer('filters', 'buttons', $buttons)) {
			$buttons->addSubmit('filter', 'Filter');
			$hasFilters && $buttons->addSubmit('reset', 'Cancel')->setValidationScope(array());
		}

		return $this;
	}


	/** @return array|NULL */
	public function getFilterCriteria()
	{
		$this->validate();
		return $this->isValid() ? Helpers::filterEmpty($this['filters']['criteria']->getValues(TRUE)) : NULL;
	}


	/**
	 * @param  callable $primaryToString
	 * @return Form
	 */
	public function addGroupActionCheckboxes(callable $primaryToString)
	{
		if (!$this->lazyCreateContainer('actions', 'records', $records)) {
			$i = 0;
			foreach ($this->getParent()->getData() as $record) {
				$records->addComponent($c = new PrimaryCheckbox, $i);
				$c->setPrimary(NCallback::invoke($primaryToString, $record));
				$i++ === 0 && $c->addRule(__CLASS__ . '::validateCheckedCount', 'Choose at least one record.');
			}
		}

		foreach ($this['actions']['buttons']->components as $button) {
			$button->setValidationScope(array($this['actions']['records']));
		}

		return $this;
	}


	/**
	 * @param  \ArrayIterator $actions
	 * @return Form
	 */
	public function addGroupActionButtons(\ArrayIterator $actions)
	{
		if (!$this->lazyCreateContainer('actions', 'buttons', $buttons)) {
			foreach ($actions as $name => $action) {
				$buttons->addSubmit($name, $action->getLabel());
			}
		}

		return $this;
	}


	/**
	 * @param  callable $primaryToString
	 * @return array|NULL
	 */
	public function getCheckedRecords(callable $primaryToString)
	{
		$this->addGroupActionCheckboxes($primaryToString);

		$this->validate();
		if ($this->isValid()) {
			$checked = array();
			foreach ($this['actions']['records']->components as $checkbox) {
				if ($checkbox->value) {
					$checked[] = $checkbox->getPrimary();
				}
			}

			return $checked;
		}

		return NULL;
	}


	/**
	 * @param  array|\Traversable $data
	 * @param  Record $record
	 * @param  callable $containerFactory
	 * @param  string|NULL $iePrimary
	 * @return Form
	 */
	public function addInlineEditControls($data, Record $record, callable $containerFactory, $iePrimary)
	{
		if (!$this->lazyCreateContainer('inline', 'buttons', $buttons)) {
			foreach ($data as $r) {
				if ($record->is($r, $iePrimary)) {
					$this['inline']['values'] = $containerFactory($r);
					$buttons->addSubmit('edit', 'Edit')
							->setValidationScope(array($this['inline']['values']));

					$buttons->addSubmit('cancel', 'Cancel')->setValidationScope(array());

				} else {
					$buttons->addComponent($ab = new PrimarySubmitButton('Edit inline'), $record->primaryToString($r));
					$ab->setPrimary($record->primaryToString($r))->setValidationScope(array());
				}

			}
		}

		return $this;
	}


	/** @return Nette\Utils\ArrayHash|NULL */
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
		if (!$this->lazyCreateContainer('pagination', 'controls', $controls)) {
			$pages = range(1, $pageCount);

			$controls->addSelect('page', 'Page', array_combine($pages, $pages))
				->setRequired('Please select a page to go to.')
				->setDefaultValue($current);
		}

		if (!$this->lazyCreateContainer('pagination', 'buttons', $buttons)) {
			$buttons->addSubmit('change', 'Change page')
				->setValidationScope(array($this['pagination']['controls']));
		}

		return $this;
	}


	/** @return int */
	public function getPage()
	{
		return (int) $this['pagination']['controls']['page']->value;
	}


	/**
	 * @param  string $parent
	 * @param  string $name
	 * @param  mixed $container
	 * @param  callable $factory
	 * @return bool does container already exist?
	 */
	protected function lazyCreateContainer($parent, $name, & $container = NULL, callable $factory = NULL)
	{
		!isset($this[$parent]) && $this->addContainer($parent);

		if (!isset($this[$parent][$name])) {
			if ($factory !== NULL) {
				$subc = NCallback::invoke($factory);
				if (!$subc instanceof Nette\Forms\Container) {
					$type = gettype($subc);
					throw new Nette\InvalidArgumentException("Filter factory is expected to return Nette\Forms\Container, '"
							. ($type === 'object' ? get_class($subc) : $type) . "' given.");
				}

				$this[$parent][$name] = $subc;

			} else {
				$this[$parent]->addContainer($name);
			}

			$created = TRUE;
		}

		$container = $this[$parent][$name];
		return !isset($created);
	}


	/**
	 * @param  PrimaryCheckbox $checkbox
	 * @return bool
	 */
	public static function validateCheckedCount(PrimaryCheckbox $checkbox)
	{
		return $checkbox->getForm()->isSubmitted()->getParent()->lookupPath('Nette\Forms\Form') !== 'actions-buttons'
				|| in_array(TRUE, $checkbox->getParent()->getValues(TRUE), TRUE);
	}

}
