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
use Nette\Callback;
use Nette\Application\UI;
use Nette\Localization\ITranslator;
use Nette\Templating\IFileTemplate;
use Nette\Forms\Controls\SubmitButton;


class DataGrid extends UI\Control
{
	/** @persistent bool */
	public $poluted = FALSE;



	// === sorting ===========

	/** @persistent */
	public $orderBy = NULL;

	/** @persistent */
	public $orderDesc = FALSE;

	/** @var array */
	protected $defaultOrderBy = NULL;



	// === timeline ===========

	/** @persistent int */
	public $page = 1;

	/** @var bool */
	protected $timelineBehavior = FALSE;



	// === filtering ===========

	/** @persistent array */
	public $filters = array();

	/** @var array */
	protected $defaultFilters = NULL;

	/** @var Callback */
	protected $filterContainerFactory = NULL;



	// === data ===========

	/** @var string|array */
	protected $primaryKey = NULL;

	/** @var Callback */
	protected $dataLoader = NULL;

	/** @var array|\Traversable */
	protected $data = NULL;

	/** @var int|NULL */
	protected $countAll = NULL;



	// === actions ===========

	/** @var array */
	protected $rowActions = NULL;

	/** @var array */
	protected $groupActions = NULL;

	/** @var Nette\Http\Session */
	protected $sessionContainer;

	/** @var Nette\Http\SessionSection */
	protected $session;



	// === l10n ===========

	/** @var ITranslator */
	protected $translator = NULL;

	/** @var Callback */
	protected $translationCb = NULL;



	// === rendering ===========

	/** @var string|IFileTemplate */
	protected $templateFile = NULL;



	// === constants ===========

	const PRIMARY_SEPARATOR = '-';



	// === LIFE CYCLE ======================================================

	/**
	 * @param  Nette\Http\Session
	 */
	function __construct(Nette\Http\Session $s)
	{
		parent::__construct();
		$this->sessionContainer = $s;
	}



	/**
	 * @param  Nette\ComponentModel\IComponent
	 * @return void
	 */
	protected function attached($presenter)
	{
		parent::attached($presenter);
		$this->invalidateControl();
		$this->session = $this->sessionContainer->getSection( __CLASS__ . '-' . $this->name );
		$this->session->setExpiration('+ 5 minutes', 'csrfToken');
	}



	/**
	 * @param  array
	 * @return void
	 */
	function loadState(array $params)
	{
		$columns = $this->getColumns();
		if (reset($columns) === FALSE) {
			throw new Nette\InvalidStateException("No columns set.");
		}

		parent::loadState($params);

		isset( $params['page'] ) && $this->setPage( $params['page'] );
		!$this->isInDefaultState() && ( $this->poluted = TRUE );

		if (!$this->poluted) {
			$this->defaultOrderBy !== NULL && ( $this->orderBy = $this->defaultOrderBy[0] ) && ( $this->orderDesc = $this->defaultOrderBy[1] );
			$this->defaultFilters !== NULL && $this->setFilters( $this->defaultFilters, FALSE );
		}

		$this->orderBy !== NULL && $this[ $this->orderBy ]->setOrderedBy( TRUE, $this->orderDesc );
	}



	/** @return bool */
	protected function isInDefaultState()
	{
		foreach ($this->reflection->getPersistentParams() as $name => $meta) {
			if ($this->$name !== $meta['def']) {
				return FALSE;
			}
		}

		return TRUE;
	}



	/** @return void */
	function handleSort()
	{
		$this->refreshState();
	}



	/**
	 * @param  bool
	 * @return bool
	 */
	protected function refreshState()
	{
		!$this->presenter->isAjax() && $this->redirect('this');
	}



	// === L10N ======================================================

	/**
	 * @param  ITranslator
	 * @return DataGrid
	 */
	function setTranslator(ITranslator $translator)
	{
		$this->translator = $translator;
		$this->translationCb = Callback::create( $translator, 'translate' );
		return $this;
	}



	/**
	 * @param  string
	 * @return string
	 */
	function translate($s)
	{
		return $this->translator === NULL ? $s : $this->translationCb->invokeArgs( func_get_args() );
	}



	// === COLUMNS ======================================================

	/**
	 * @param  string
	 * @param  string
	 * @return Column
	 */
	function addColumn($name, $label)
	{
		return $this[$name] = new Column( $this->translate( $label ) );
	}



	/** @return array */
	function getColumns()
	{
		return iterator_to_array( $this->getComponents(FALSE, 'TwiGrid\\Column') );
	}



	/** @return array */
	function getColumnNames()
	{
		$names = array_keys( $this->getColumns() );
		return reset( $names ) !== FALSE ? array_combine( $names, $names ) : array();
	}



	// === ACTIONS ======================================================

	/**
	 * @param  string
	 * @param  string
	 * @param  mixed
	 * @param  string|NULL
	 */
	function addRowAction($name, $label, $callback, $confirmation = NULL)
	{
		$this->rowActions === NULL && ( $this->rowActions = array() );
		$this->rowActions[$name] = array(
			'label' => $this->translate( (string) $label ),
			'callback' => Callback::create($callback),
			'confirmation' => $confirmation === NULL ? $confirmation : $this->translate( $confirmation ),
		);
		return $this;
	}



	/**
	 * @param  string
	 * @param  string
	 * @param  string
	 */
	function handleRowAction($action, $primary, $token)
	{
		if ($token === $this->session->csrfToken) {
			unset($this->session->csrfToken);
			$this->rowActions[$action]['callback']->invokeArgs( array( $this->stringToPrimaries( $primary ) ) );

		} else {
			$this->flashMessage('Security token not match.', 'error');
			$this->redirect('this');
		}
	}



	/**
	 * @param  string
	 * @param  string
	 * @param  mixed
	 * @param  string|NULL
	 * @return DataGrid
	 */
	function addGroupAction($name, $label, $callback, $confirmation = NULL)
	{
		$this->groupActions === NULL && ( $this->groupActions = array() );
		$this->groupActions[$name] = array(
			'label' => $this->translate( (string) $label ),
			'callback' => Callback::create( $callback ),
			'confirmation' => $confirmation === NULL ? $confirmation : $this->translate( $confirmation ),
		);
		return $this;
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

		$this->groupActions[ $button->name ]['callback']->invokeArgs( array( $primaries ) );
		$this->invalidateCache();
	}



	// === TIMELINE BEHAVIOR ======================================================

	/**
	 * @param  bool
	 * @return DataGrid
	 */
	function setTimelineBehavior($bool = TRUE)
	{
		$this->timelineBehavior = (bool) $bool;
		return $this;
	}



	/**
	 * @param  int
	 * @return void
	 */
	function handleChangePage($page)
	{
		$tmp = $this->page;
		$this->setPage($page);
		$this->page !== $tmp && $this->invalidateCache();
		$this->refreshState();
	}



	/**
	 * @param  int
	 * @return DataGrid
	 */
	protected function setPage($page)
	{
		$this->page = $this->timelineBehavior ? ($page === 0 ? 1 : max(-1, (int) $page)) : 1;
		return $this;
	}



	// === DATA LOADING ======================================================

	/**
	 * @param  mixed
	 * @return DataGrid
	 */
	function setDataLoader($loader)
	{
		$this->dataLoader = Callback::create( $loader );
		return $this;
	}



	/**
	 * @param  int
	 * @return DataGrid
	 */
	function setCountAll($count)
	{
		$this->countAll = max(0, (int) $count);
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

		$this->data = $this->dataLoader->invokeArgs( array( $this, array_merge( $this->primaryKey, $this->getColumnNames() ), $orderBy, $this->filters, $this->page ) );
	}



	/** @return bool */
	protected function invalidateCache()
	{
		unset($this['form']); $this->data = NULL;
		return TRUE;
	}



	// === FORM BUILDING ======================================================

	/** @return UI\Form */
	protected function createComponentForm()
	{
		$form = new UI\Form;
		$this->translator !== NULL && $form->setTranslator( $this->translator );

		if ($this->filterContainerFactory !== NULL) {
			$filters = $form->addContainer('filters');
			$filters['criteria'] = $this->filterContainerFactory->invoke();

			$buttons = $filters->addContainer('buttons');
			$buttons->addSubmit( 'filter', 'Filter' )->onClick[] = $this->onFilterButtonClick;

			reset($this->filters) !== FALSE
					&& $filters['criteria']->setDefaults( $this->filters )
					&& ( $buttons->addSubmit( 'reset', 'Cancel' )
							->setValidationScope(FALSE)
							->onClick[] = $this->onResetButtonClick );
		}

		if ($this->groupActions !== NULL) {
			$actions = $form->addContainer('actions');

			// records checkboxes
			$records = $actions->addContainer('records');
			$first = TRUE;
			foreach ($this->getData() as $record) {
				$checkbox = $records->addCheckbox( $this->primariesToString($record) );
				$first && $checkbox->addRule( __CLASS__ . '::validateCheckedCount', 'Choose at least one record!' )
						&& ( $first = FALSE );
			}

			// action buttons
			$buttons = $actions->addContainer('buttons');
			foreach ($this->groupActions as $name => $action) {
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



	/** @return \ArrayIterator|NULL */
	protected function getFilterButtons()
	{
		return isset($this['form']['filters']) ? $this['form']['filters']['buttons']->components : NULL;
	}



	// === SORTING ======================================================

	/**
	 * @param  string
	 * @param  bool
	 * @return DataGrid
	 */
	function setDefaultOrderBy($column, $desc = FALSE)
	{
		$this->defaultOrderBy = array( (string) $column, (bool) $desc );
		return $this;
	}



	// === FILTERING ======================================================

	/**
	 * @param  mixed
	 * @return DataGrid
	 */
	function setFilterContainerFactory($factory)
	{
		$this->filterContainerFactory = Callback::create( $factory );
		return $this;
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
	 * @return array
	 */
	protected function filterEmpty(array $a)
	{
		$ret = array();
		foreach ($a as $k => $v) {
			if (is_array($v)) { // recursive
				$tmp = $this->filterEmpty($v);
				if (reset($tmp) !== FALSE) {
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



	/**
	 * @param  array
	 * @return DataGrid
	 */
	function setDefaultFilters(array $filters)
	{
		if ($this->filterContainerFactory === NULL) {
			throw new Nette\InvalidStateException("Filter factory not set.");
		}

		$this->defaultFilters = $filters;
		return $this;
	}



	/**
	 * @param  array
	 * @param  bool
	 * @return DataGrid
	 */
	protected function setFilters(array $filters, $refresh = TRUE)
	{
		$this->filters !== $filters && ( ( $this->filters = $filters ) || TRUE ) && ( $this->page = 1 ) && $refresh && $this->invalidateCache();
		$refresh && $this->refreshState();
		return $this;
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
		$template->registerHelper('translate', $this->translate);
		$template->registerHelper('primariesToString', $this->primariesToString);

		$template->defaultTemplatePath = __DIR__ . '/DataGrid.latte';
		$this->templateFile === NULL && ( $this->templateFile = $template->defaultTemplatePath );
		!($this->templateFile instanceof Nette\Templating\IFileTemplate) && $template->setFile( $this->templateFile );

		$template->form = $template->_form = $this['form'];
		$template->columns = $this->getColumns();
		$template->filterButtons = $this->getFilterButtons();
		$template->isFiltered = reset($this->filters) !== FALSE;
		$template->dataCount = count( $template->data = $this->getData() );
		$template->countAll = $this->countAll;
		$template->rowActions = $this->rowActions;
		$template->csrfToken = $this->rowActions !== NULL
				? ( isset($this->session->csrfToken) ? $this->session->csrfToken : ( $this->session->csrfToken = Nette\Utils\Strings::random(16) ) )
				: ( $this->session->__unset('csrfToken') || NULL );
		$template->groupActions = $this->groupActions;
		$template->isTimelined = $this->timelineBehavior;
		$template->page = $this->page;
		$template->renderFirstColumn = $template->dataCount && $this->groupActions !== NULL;
		$template->renderFilterRow = $template->filterButtons !== NULL && ( $template->isFiltered || $template->dataCount );
		$template->renderLastColumn = $template->renderFilterRow || $this->rowActions !== NULL;
		$template->renderFooter = $template->dataCount && ( $this->groupActions !== NULL || $this->timelineBehavior ) && ( $this->groupActions !== NULL || $this->countAll === NULL || $template->dataCount < $this->countAll || $this->page !== 1 ); // see http://www.wolframalpha.com/input/?i=%21%28+%28+%28%21a+%26%26+%21b%29+%7C%7C+%21c+%29+%7C%7C+%28%21a+%26%26+b+%26%26+d+%26%26+e+%26%26+f%29+%29
		$template->columnCount = count($template->columns) + ( $template->renderFirstColumn ? 1 : 0 ) + ( $template->renderLastColumn ? 1 : 0 );
		$template->render();
	}
}
