<?php

/**
 * This file is part of the TwiGrid component
 *
 * Copyright (c) 2013 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
 * @link     https://github.com/uestla/twigrid
 */


namespace TwiGrid;

use Nette;
use Nette\Application\UI;
use Nette\Templating\IFileTemplate;
use Nette\Forms\Controls\SubmitButton;


class DataGrid extends UI\Control
{
	// === ordering ===========

	/** @persistent */
	public $orderBy = NULL;

	/** @persistent */
	public $orderDesc = FALSE;



	// === filtering ===========

	/** @persistent array */
	public $filters = array();

	/** @var Nette\Callback */
	protected $filterContainerFactory = NULL;



	// === data ===========

	/** @var string|array */
	protected $primaryKey = NULL;

	/** @var Nette\Callback */
	protected $dataLoader = NULL;

	/** @var array|\Traversable */
	protected $data = NULL;



	// === actions ===========

	/** @var array */
	protected $actions = NULL;



	// === rendering ===========

	/** @var string|IFileTemplate */
	protected $templateFile = NULL;



	// === constants ===========

	const PRIMARY_SEPARATOR = '-';



	// === COLUMNS ======================================================

	/**
	 * @param  string
	 * @param  string
	 * @return Column
	 */
	function addColumn($name, $label)
	{
		return $this[$name] = new Column($label);
	}



	/** @return array */
	function getColumns()
	{
		return iterator_to_array( $this->getComponents(FALSE, 'TwiGrid\\Column') );
	}



	/** @return array */
	function getColumnNames()
	{
		return array_keys( $this->getColumns() );
	}



	// === ACTIONS ======================================================

	/**
	 * @param  string
	 * @param  string
	 * @param  mixed
	 * @param  string|NULL
	 * @return DataGrid
	 */
	function addGroupAction($name, $label, $callback, $confirmation = NULL)
	{
		$this->actions === NULL && ( $this->actions = array() );
		$this->actions[$name] = array(
			'label' => (string) $label,
			'callback' => Nette\Callback::create( $callback ),
			'confirmation' => $confirmation,
		);
		return $this;
	}



	// === LIFE CYCLE ======================================================

	/**
	 * @param  Nette\ComponentModel\IComponent
	 * @return void
	 */
	protected function attached($presenter)
	{
		parent::attached($presenter);
		$this->invalidateControl();
	}



	/**
	 * @param  array
	 * @return void
	 */
	function loadState(array $params)
	{
		parent::loadState($params);

		if ($this->orderBy !== NULL) {
			$this[ $this->orderBy ]->setOrderedBy( TRUE, $this->orderDesc );
		}
	}



	/** @return void */
	function handleSort()
	{
		$this->refreshState();
	}



	/** @return void */
	protected function refreshState()
	{
		!$this->presenter->isAjax() && $this->redirect('this');
	}



	// === DATA LOADING ======================================================

	/**
	 * @param  mixed
	 * @return DataGrid
	 */
	function setDataLoader($loader)
	{
		$this->dataLoader = Nette\Callback::create( $loader );
		return $this;
	}



	/**
	 * @param  string|array
	 * @return DataGrid
	 */
	function setPrimaryKey($primary)
	{
		$this->primaryKey = (array) $primary;
		return $this;
	}



	/** @return array|\Traversable */
	protected function getData()
	{
		$this->data === NULL && $this->loadData();
		return $this->data;
	}



	/** @return void */
	protected function loadData()
	{
		if ($this->dataLoader === NULL) {
			throw new Nette\InvalidStateException("Data loader not set.");
		}

		if ($this->primaryKey === NULL) {
			throw new Nette\InvalidStateException("Primary key not set.");
		}

		$orderBy = array();
		if ($this->orderBy !== NULL) {
			$orderBy[ $this->orderBy ] = $this->orderDesc;

			foreach ($this->primaryKey as $column) {
				$orderBy[ $column ] = $this->orderDesc;
			}
		}

		$this->data = $this->dataLoader->invokeArgs( array( array_merge( $this->primaryKey, $this->getColumnNames() ), $orderBy, $this->filters ) );
	}



	/** @return void */
	protected function invalidateCache()
	{
		unset($this['form']); $this->data = NULL;
	}



	// === FORM BUILDING ======================================================

	/**
	 * @param  mixed
	 * @return DataGrid
	 */
	function setFilterContainerFactory($factory)
	{
		$this->filterContainerFactory = Nette\Callback::create( $factory );
		return $this;
	}



	/** @return UI\Form */
	protected function createComponentForm()
	{
		$form = new UI\Form;

		if ($this->filterContainerFactory !== NULL) {
			$filters = $form->addContainer('filters');
			$filters['criteria'] = $this->filterContainerFactory->invoke();

			$buttons = $filters->addContainer('buttons');
			$buttons->addSubmit('filter', 'Filtrovat')->onClick[] = $this->onFilterButtonClick;

			count($this->filters)
					&& $filters['criteria']->setDefaults( $this->filters )
					&& ( $buttons->addSubmit('reset', 'Zrušit')->onClick[] = $this->onResetButtonClick );
		}

		if ($this->actions !== NULL) {
			$actions = $form->addContainer('actions');

			// records checkboxes
			$records = $actions->addContainer('records');
			$first = TRUE;
			foreach ($this->getData() as $record) {
				$checkbox = $records->addCheckbox( $this->primariesToString($record) );
				$first && $checkbox->addRule( __CLASS__ . '::validateCheckedCount', 'Zvolte alespoň jeden záznam!' )
						&& ( $first = FALSE );
			}

			// action buttons
			$buttons = $actions->addContainer('buttons');
			foreach ($this->actions as $name => $action) {
				$buttons->addSubmit($name, $action['label'])
					->onClick[] = $this->onActionButtonClick;
			}
		}

		$form->addProtection();
		return $form;
	}



	/**
	 * @param  Nette\Forms\Controls\Checkbox
	 * @return bool
	 */
	static function validateCheckedCount(Nette\Forms\Controls\Checkbox $checkbox)
	{
		if ($checkbox->form->submitted->parent->lookupPath('Nette\\Forms\\Form') === 'actions-buttons') {
			return in_array(TRUE, $checkbox->parent->getValues(TRUE), TRUE);
		}

		return TRUE;
	}



	/**
	 * @param  SubmitButton
	 * @return void
	 */
	function onActionButtonClick(SubmitButton $button)
	{
		$form = $button->form;
		$values = $form['actions-records']->values;


		// get the primary keys
		$primaries = array();
		foreach ($values as $name => $checked) {
			if ($checked) {
				$primaries[] = $this->stringToPrimaries($name);
			}
		}

		$this->actions[ $button->name ]['callback']->invokeArgs( array( $primaries ) );
		$this->invalidateCache();
	}



	/**
	 * @param  SubmitButton
	 * @return void
	 */
	function onFilterButtonClick(SubmitButton $button)
	{
		$form = $button->form;
		$this->setFilters( $this->filterEmpty( $form['filters']['criteria']->getValues(TRUE) ) );
	}



	/**
	 * @param  array
	 * @return void
	 */
	function setFilters(array $filters)
	{
		$this->filters = $filters;
		$this->refreshState();
		$this->invalidateCache();
	}



	/**
	 * @param  array
	 * @return array
	 */
	protected function filterEmpty(array $a)
	{
		$ret = array();
		foreach ($a as $k => $v) {
			if (is_array($v)) {
				$tmp = $this->filterEmpty($v);
				if (count($tmp)) {
					$ret[$k] = $tmp;
				}

			} else {
				if (strlen($v)) {
					$ret[$k] = $v;
				}
			}
		}

		return $ret;
	}



	/**
	 * @param  SubmitButton
	 * @return void
	 */
	function onResetButtonClick(SubmitButton $button)
	{
		$this->setFilters( array() );
	}



	/** @return \ArrayIterator|NULL */
	protected function getFilterButtons()
	{
		return isset($this['form']['filters']) ? $this['form']['filters']['buttons']->components : NULL;
	}



	// === RENDERING ======================================================

	/**
	 * @param  array|\ArrayAccess
	 * @return string
	 */
	function primariesToString($record)
	{
		$primaries = array();
		foreach ($this->primaryKey as $column) {
			$primaries[] = $record[ $column ];
		}

		return implode( static::PRIMARY_SEPARATOR, $primaries );
	}



	/**
	 * @param  string
	 * @return array|string
	 */
	function stringToPrimaries($s)
	{
		$primaries = explode( static::PRIMARY_SEPARATOR, $s );
		return count($primaries) === 1 ? (string) $primaries[0] : $primaries;
	}



	/**
	 * @param  string|IFileTemplate
	 * @return DataGrid
	 */
	function setTemplateFile($templateFile)
	{
		if ( !is_string($templateFile) && !($templateFile instanceof IFileTemplate) ) {
			throw new Nette\InvalidArgumentException('String or Nette\Templating\IFileTemplate expected, "' . gettype($templateFile) . '" given.');
		}

		$this->templateFile = $templateFile;
		return $this;
	}



	/** @return void */
	function render()
	{
		$template = $this->createTemplate();

		$this->templateFile === NULL && ( $this->templateFile = __DIR__ . '/DataGrid.latte' );
		!($this->templateFile instanceof Nette\Templating\IFileTemplate) && $template->setFile( $this->templateFile );

		$template->registerHelper('primariesToString', $this->primariesToString);

		$template->form = $template->_form = $this['form'];
		$template->columns = $this->getColumns();
		$template->columnCount = count($template->columns) + (isset($template->form['filters']) ? 1 : 0) + ($this->actions !== NULL ? 1 : 0);
		$template->filterButtons = $this->getFilterButtons();
		$template->isFiltered = (bool) count($this->filters);
		$template->dataCount = count( $template->data = $this->getData() );
		$template->actions = $this->actions;
		$template->render();
	}
}