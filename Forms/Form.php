<?php

/**
 * This file is part of the TwiGrid component
 *
 * Copyright (c) 2013 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
 * @link     https://github.com/uestla/twigrid
 */


namespace TwiGrid\Forms;

use Nette;
use TwiGrid\Helpers;


class Form extends Nette\Application\UI\Form
{

	/** @var Nette\Callback|NULL */
	protected $filterFactory = NULL;



	/**
	 * @param  Nette\Callback
	 * @return Form
	 */
	function setFilterFactory(Nette\Callback $factory)
	{
		$this->filterFactory = $factory;
		return $this;
	}



	/** @return Form */
	function addFilterCriteria()
	{
		if (!$this->lazyCreateContainer('filters', 'criteria', $criteria, $this->filterFactory)) {
			$criteria->setDefaults( $this->parent->filters );
		}

		return $this;
	}



	/**
	 * @param  bool
	 * @return Form
	 */
	function addFilterButtons($hasFilters)
	{
		if (!$this->lazyCreateContainer('filters', 'buttons', $buttons)) {
			$buttons->addSubmit('filter', 'Filter')->setValidationScope(FALSE)->setAttribute('data-tw-validate', 'filters[criteria]');
			$hasFilters && $buttons->addSubmit('reset', 'Cancel')->setValidationScope(FALSE);
		}

		return $this;
	}



	/** @return array */
	function getFilterCriteria()
	{
		$this->addFilterCriteria();
		return $this['filters']['criteria']->isValid() ? Helpers::filterEmpty( $this['filters']['criteria']->getValues(TRUE) ) : NULL;
	}



	/**
	 * @param  Nette\Callback
	 * @return Form
	 */
	function addGroupActionCheckboxes(Nette\Callback $primaryToString)
	{
		if (!$this->lazyCreateContainer('actions', 'records', $records)) {
			$i = 0;
			foreach ($this->parent->data as $record) {
				$records->addComponent( $c = new PrimaryCheckbox, $i );
				$c->setPrimary( $primaryToString($record) );
				$i++ === 0 && $c->addRule( __CLASS__ . '::validateCheckedCount', 'Choose at least one record!' );
			}
		}

		return $this;
	}



	/**
	 * @param  array
	 * @return Form
	 */
	function addGroupActionButtons(array $actions)
	{
		if (!$this->lazyCreateContainer('actions', 'buttons', $buttons)) {
			foreach ($actions as $name => $action) {
				$buttons->addSubmit($name, $action['label'])->setValidationScope(FALSE)->setAttribute('data-tw-validate', 'actions[records]');
			}
		}

		return $this;
	}



	/**
	 * @param  Nette\Callback
	 * @param  Nette\Callback
	 * @return array
	 */
	function getCheckedRecords(Nette\Callback $primaryToString)
	{
		$this->addGroupActionCheckboxes( $primaryToString );
		if ($this['actions']['records']->isValid()) {
			$checked = array();
			foreach ($this['actions']['records']->components as $checkbox) {
				if ($checkbox->value) {
					$checked[] = $checkbox->primary;
				}
			}

			return $checked;
		}

		return NULL;
	}



	/**
	 * @param  Nette\Callback
	 * @param  Nette\Callback
	 * @param  Nette\Callback
	 * @param  string|NULL
	 * @return void
	 */
	function addInlineEditControls(Nette\Callback $dataLoader, Nette\Callback $primaryToString, Nette\Callback $containerFactory, $iePrimary)
	{
		if (!$this->lazyCreateContainer('inline', 'buttons', $buttons)) {
			$i = 0;
			foreach ($dataLoader() as $record) {
				$primaryString = $primaryToString( $record );
				if ($iePrimary === $primaryString) {
					$this['inline']['values'] = $containerFactory( $record );
					$buttons->addSubmit('edit', 'Edit')->setValidationScope(FALSE)->setAttribute('data-tw-validate', 'inline[values]');
					$buttons->addSubmit('cancel', 'Cancel')->setValidationScope(FALSE);

				} else {
					$buttons->addComponent( $ab = new PrimarySubmitButton('Edit inline'), $i );
					$ab->setPrimary( $primaryString )->setValidationScope(FALSE);
				}

				$i++;
			}
		}
	}



	/** @return array */
	function getInlineValues()
	{
		return $this['inline']['values']->isValid() ? $this['inline']['values']->getValues(TRUE) : NULL;
	}



	/**
	 * @param  string
	 * @param  string
	 * @param  mixed
	 * @param  Nette\Callback|NULL
	 * @return bool does container already exist?
	 */
	protected function lazyCreateContainer($parent, $name, & $container = NULL, Nette\Callback $factory = NULL)
	{
		!isset($this[$parent]) && $this->addContainer($parent);
		!isset($this[$parent][$name]) && ($factory !== NULL ? ($this[$parent][$name] = $factory->invoke()) : $this[$parent]->addContainer($name)) && ($created = TRUE);
		$container = $this[$parent][$name];
		return !isset($created);
	}



	/**
	 * @param  PrimaryCheckbox
	 * @return bool
	 */
	static function validateCheckedCount(PrimaryCheckbox $checkbox)
	{
		return $checkbox->form->submitted->parent->lookupPath('Nette\\Forms\\Form') !== 'actions-buttons'
				|| in_array(TRUE, $checkbox->parent->getValues(TRUE), TRUE);
	}

}
