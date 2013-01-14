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
use Nette\Forms\ISubmitterControl;
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
	protected $timeline = FALSE;



	// === filtering ===========

	/** @persistent array */
	public $filters = array();

	/** @var array */
	protected $defaultFilters = NULL;

	/** @var Nette\Callback */
	protected $filterFactory = NULL;



	// === inline editing ===========

	/** @persistent string|NULL */
	public $inlineEditPrimary = NULL;

	/** @var Nette\Callback */
	protected $inlineEditContainerFactory = NULL;

	/** @var Nette\Callback */
	protected $inlineEditProcessCallback = NULL;



	// === data ===========

	/** @var Record */
	protected $record;

	/** @var Nette\Callback */
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

	/** @var Nette\Callback */
	protected $translationCb = NULL;



	// === rendering ===========

	/** @var string */
	protected $templateFile = NULL;



	// === constants ===========

	const INLINE_EDIT_ACTION = '__inline';



	// === LIFE CYCLE ======================================================

	/** @param  Nette\Http\Session */
	function __construct(Nette\Http\Session $s)
	{
		parent::__construct();
		$this->record = new Record;
		$this->sessionContainer = $s;
	}



	/**
	 * @param  Nette\ComponentModel\IComponent
	 * @return void
	 */
	protected function attached($presenter)
	{
		parent::attached($presenter);
		$this->session = $this->sessionContainer->getSection( __CLASS__ . '-' . $this->name );
		$this->session->setExpiration('+ 5 minutes', 'csrfToken');
		$this->record->valueGetter === NULL && $this->setValueGetter();
		!isset( $this->presenter->payload->twiGrids ) && ( $this->presenter->payload->twiGrids = $this->presenter->payload->twiGrids['forms'] = array() );
	}



	/**
	 * @param  array
	 * @return void
	 */
	function loadState(array $params)
	{
		parent::loadState($params);

		isset( $params['page'] ) && $this->setPage( $params['page'] );
		!$this->poluted && !$this->isInDefaultState() && ( $this->poluted = TRUE );

		if (!$this->poluted) {
			$this->defaultOrderBy !== NULL && ( $this->orderBy = $this->defaultOrderBy[0] ) && ( $this->orderDesc = $this->defaultOrderBy[1] );
			$this->defaultFilters !== NULL && $this->setFilters( $this->defaultFilters, FALSE );
		}

		$this->orderBy !== NULL && $this[ $this->orderBy ]->setOrderedBy( TRUE, $this->orderDesc );
		$this->validateState();
	}



	/** @return void */
	protected function validateState()
	{
		$columns = $this->getColumns();
		if (reset($columns) === FALSE) {
			throw new Nette\InvalidStateException("No columns set.");
		}

		if ($this->dataLoader === NULL) {
			throw new Nette\InvalidStateException("Data loader not set.");
		}

		if ($this->record->primaryKey === NULL) {
			throw new Nette\InvalidStateException("Primary key not set.");
		}

		if ($this->inlineEditPrimary !== NULL && $this->inlineEditContainerFactory === NULL) {
			throw new Nette\InvalidStateException("Inline editing not properly set.");
		}
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



	/**
	 * @param  bool
	 * @return TRUE
	 */
	protected function refreshState($cancelInlineEditing = TRUE)
	{
		$cancelInlineEditing && ( $this->inlineEditPrimary = NULL );
		!$this->presenter->isAjax() && $this->redirect('this');
		return TRUE;
	}



	// === L10N ======================================================

	/**
	 * @param  ITranslator
	 * @return DataGrid
	 */
	function setTranslator(ITranslator $translator)
	{
		$this->translator = $translator;
		$this->translationCb = Nette\Callback::create( $translator, 'translate' );
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
	function addColumn($name, $label = NULL)
	{
		return $this[$name] = new Column( $this->translate( $label === NULL ? $name : $label ) );
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
			'callback' => Nette\Callback::create($callback),
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
			$this->rowActions[$action]['callback']->invokeArgs( array( $this->record->stringToPrimary( $primary ) ) );
			$this->refreshState();
			$this->invalidate('body', 'footer');

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
			'callback' => Nette\Callback::create( $callback ),
			'confirmation' => $confirmation === NULL ? $confirmation : $this->translate( $confirmation ),
		);
		return $this;
	}



	/**
	 * @param  SubmitButton
	 * @return void
	 */
	function onGroupActionButtonClick(SubmitButton $button)
	{
		$checkboxes = $button->form['actions']['records'];
		if (!$checkboxes->valid) {
			return ;
		}

		$primaries = array();
		foreach ($checkboxes->components as $checkbox) {
			if ($checkbox->value) { // checked
				$primaries[] = $this->record->stringToPrimary( $checkbox->primary );
			}
		}

		$this->groupActions[ $button->name ]['callback']->invokeArgs( array( $primaries ) );
		$this->refreshState();
		$this->invalidate('body', 'footer');
	}



	// === TIMELINE BEHAVIOR ======================================================

	/**
	 * @param  bool
	 * @return DataGrid
	 */
	function setTimeline($bool = TRUE)
	{
		$this->timeline = (bool) $bool;
		return $this;
	}



	/**
	 * @param  int
	 * @return void
	 */
	function handleChangePage($no)
	{
		$tmp = $this->page;
		$this->setPage($no);
		$this->refreshState();
		$this->page !== $tmp && $this->invalidate();
	}



	/**
	 * @param  int
	 * @return DataGrid
	 */
	protected function setPage($page)
	{
		$this->page = $this->timeline ? max(1, (int) $page) : 1;
		return $this;
	}



	// === SORTING ======================================================

	/** @return void */
	function handleSort()
	{
		$this->refreshState();
		$this->invalidate();
	}



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
	function setFilterFactory($factory)
	{
		$this->filterFactory = Nette\Callback::create( $factory );
		return $this;
	}



	/**
	 * @param  SubmitButton
	 * @return void
	 */
	function onFilterButtonClick(SubmitButton $button)
	{
		$criteria = $button->form['filters']['criteria'];
		$criteria->valid && $this->setFilters( $this->filterEmpty( $criteria->getValues(TRUE) ) );
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
				if (!count($tmp)) {
					$ret[$k] = $tmp;
				}

			} elseif (strlen($v)) {
				$ret[$k] = $v;
			}
		}

		return $ret;
	}



	/**
	 * @param  SubmitButton
	 * @return void
	 */
	function onResetFiltersButtonClick(SubmitButton $button)
	{
		$this->poluted = TRUE;
		$this->setFilters( array() );
	}



	/**
	 * @param  array
	 * @return DataGrid
	 */
	function setDefaultFilters(array $filters)
	{
		if ($this->filterFactory === NULL) {
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
		( $diff = $this->filters !== $filters ) && ( ( $this->filters = $filters ) || TRUE ) && ( $this->page = 1 );
		$refresh && $this->refreshState() && ( $diff ? $this->invalidate() : $this->invalidate(FALSE, 'body', 'footer') );
		return $this;
	}



	// === DATA LOADING ======================================================

	/**
	 * @param  string|array
	 * @return DataGrid
	 */
	function setPrimaryKey($primaryKey)
	{
		$this->record->setPrimaryKey( $primaryKey );
		return $this;
	}



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
	 * @param  int
	 * @return DataGrid
	 */
	function setCountAll($count)
	{
		$this->countAll = max(0, (int) $count);
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
		$orderBy = array();
		if ($this->orderBy !== NULL) {
			$orderBy[ $this->orderBy ] = $this->orderDesc;

			foreach ($this->record->primaryKey as $column) {
				$orderBy[ $column ] = $this->orderDesc;
			}
		}

		$this->data = $this->dataLoader->invokeArgs( array(
			$this,
			array_merge( array_combine( $this->record->primaryKey, $this->record->primaryKey ), $this->getColumnNames() ),
			$orderBy,
			$this->filters,
			$this->page,
		) );
	}



	/**
	 * @param  mixed|NULL
	 * @return DataGrid
	 */
	function setValueGetter($callback = NULL)
	{
		$this->record->setValueGetter($callback);
		return $this;
	}



	/**
	 * @param  mixed
	 * @param  string
	 * @param  bool
	 * @return mixed
	 */
	function getValue($record, $column, $need = TRUE)
	{
		return $this->record->getValue($record, $column, $need);
	}



	/**
	 * API:
	 * $c->invalidate() - data reload + whole grid snippet
	 * $c->invalidate(FALSE) - whole grid snippet
	 * $c->invalidate('snippet1', 'snippet2', ...) - inv. given snippets
	 * $c->invalidate(TRUE, 'snippet1', 'snippet2', ...) - data reload + given snippets
	 *
	 * @param  bool|string|NULL
	 * @return void
	 */
	protected function invalidate($reloadData = TRUE)
	{
		$snippets = func_get_args();
		if (!is_bool($reloadData)) {
			$reloadData = TRUE;

		} else {
			array_shift($snippets);
		}

		unset($this['form']);
		$reloadData && ( $this->data = NULL );

		reset($snippets) === FALSE && ( $snippets[] = NULL );
		foreach ($snippets as $snippet) {
			$this->invalidateControl($snippet);
		}
	}



	// === INLINE EDITING ======================================================

	/**
	 * @param  mixed
	 * @param  mixed
	 * @return DataGrid
	 */
	function setInlineEditing($containerCb, $processCb)
	{
		if ($this->inlineEditContainerFactory !== NULL) {
			throw new Nette\InvalidStateException("Inline editing already set.");
		}

		$this->inlineEditContainerFactory = Nette\Callback::create( $containerCb );
		$this->inlineEditProcessCallback = Nette\Callback::create( $processCb );
	}



	/**
	 * @param  Forms\PrimarySubmitButton
	 * @return void
	 */
	function onActivateInlineEditButtonClick(Forms\PrimarySubmitButton $button)
	{
		$this->activateInlineEditing( $button->primary );
	}



	/**
	 * @param  string
	 * @return void
	 */
	function activateInlineEditing($primary)
	{
		$this->inlineEditPrimary = $primary;
		$this->refreshState(FALSE);
		$this->invalidate( FALSE, 'body' );
	}



	/**
	 * @param  bool
	 * @return void
	 */
	function deactivateInlineEditing($dataAsWell = TRUE)
	{
		$this->refreshState();
		$this->invalidate( $dataAsWell, 'body' );
	}



	/**
	 * @param  SubmitButton
	 * @return void
	 */
	function onEditInlineButtonClick(SubmitButton $button)
	{
		$this->inlineEditProcessCallback->invokeArgs( array( $this->record->stringToPrimary( $this->inlineEditPrimary ),
			$button->form['inline']['values']->getValues(TRUE) ) );
		$this->deactivateInlineEditing();
	}



	/**
	 * @param  SubmitButton
	 * @return void
	 */
	function onCancelInlineButtonClick(SubmitButton $button)
	{
		$this->deactivateInlineEditing( FALSE );
	}



	// === FORM BUILDING ======================================================

	/** @return UI\Form */
	protected function createComponentForm($name)
	{
		$form = new UI\Form;
		$this->translator !== NULL && $form->setTranslator( $this->translator );
		$form->addProtection();

		$this->addComponent( $form, $name );

		// filtering
		if ($this->filterFactory !== NULL) {
			$filters = $form->addContainer('filters');
			$filters['criteria'] = $this->filterFactory->invoke();

			$buttons = $filters->addContainer('buttons');
			$buttons->addSubmit('filter', 'Filter')->setValidationScope(FALSE)->onClick[] = $this->onFilterButtonClick;

			reset($this->filters) !== FALSE
					&& $filters['criteria']->setDefaults( $this->filters )
					&& ( $buttons->addSubmit('reset', 'Cancel')->setValidationScope(FALSE)->onClick[] = $this->onResetFiltersButtonClick );
		}

		if ( ( $sBy = $form->submitted ) instanceof ISubmitterControl && $sBy->parent->lookupPath('Nette\\Forms\\Form') === 'filters-buttons' ) {
			return $form; // no need to continue
		}

		// group actions
		if ($this->groupActions !== NULL) {
			$actions = $form->addContainer('actions');

			// records checkboxes
			$records = $actions->addContainer('records');
			$i = 0;
			foreach ($this->getData() as $record) {
				$records->addComponent( $checkbox = new Forms\PrimaryCheckbox(), $i );
				$checkbox->setPrimary( $this->record->primaryToString($record) );
				$i++ === 0 && $checkbox->addRule( __CLASS__ . '::validateCheckedCount', 'Choose at least one record!' );
			}

			// action buttons
			$buttons = $actions->addContainer('buttons');
			foreach ($this->groupActions as $name => $action) {
				$buttons->addSubmit($name, $action['label'])->setValidationScope(FALSE)->onClick[] = $this->onGroupActionButtonClick;
			}
		}

		// inline editing
		if ($this->inlineEditContainerFactory !== NULL) {
			$inline = $form->addContainer('inline');
			$buttons = $inline->addContainer('buttons');

			$i = 0;
			foreach ($this->getData() as $record) {
				$primaryString = $this->record->primaryToString($record);
				if ($this->inlineEditPrimary === $primaryString) {
					$inline['values'] = $this->inlineEditContainerFactory->invokeArgs( array($record) );
					$buttons->addSubmit('edit', 'Edit')->onClick[] = $this->onEditInlineButtonClick;
					$buttons->addSubmit('cancel', 'Cancel')->setValidationScope(FALSE)->onClick[] = $this->onCancelInlineButtonClick;

				} else {
					$buttons->addComponent( $ab = new Forms\PrimarySubmitButton('Edit inline'), $i );
					$ab->setPrimary( $primaryString )->setValidationScope(FALSE)->onClick[] = $this->onActivateInlineEditButtonClick;
				}

				$i++;
			}
		}

		return $form;
	}



	/**
	 * @param  Nette\Forms\Controls\Checkbox
	 * @return bool
	 */
	static function validateCheckedCount(Forms\PrimaryCheckbox $checkbox)
	{
		return $checkbox->form->submitted->parent->lookupPath('Nette\\Forms\\Form') !== 'actions-buttons'
				|| in_array(TRUE, $checkbox->parent->getValues(TRUE), TRUE);
	}



	/** @return \ArrayIterator|NULL */
	protected function getFilterButtons()
	{
		return isset($this['form']['filters']) ? $this['form']['filters']['buttons']->components : NULL;
	}



	// === RENDERING ======================================================

	/**
	 * @param  string|IFileTemplate
	 * @return DataGrid
	 */
	function setTemplateFile($templateFile)
	{
		$this->templateFile = (string) $templateFile;
		return $this;
	}



	/** @return void */
	function render()
	{
		$form = $this['form'];
		$this->isControlInvalid() && $this->invalidate(FALSE, 'flashes');
		$this->presenter->payload->twiGrids['forms'][ $form->elementPrototype->id ] = (string) $form->getAction();

		$template = $this->createTemplate();
		$template->registerHelper('translate', $this->translate);
		$template->registerHelper('primaryToString', $this->record->primaryToString);
		$template->registerHelper('getValue', $this->record->valueGetter);

		$template->defaultTemplate = __DIR__ . '/DataGrid.latte';
		$this->templateFile === NULL && ( $this->templateFile = $template->defaultTemplatePath );
		$template->setFile( $this->templateFile );

		$template->form = $template->_form = $form;
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
		$template->timeline = $this->timeline;
		$template->page = $this->page;
		$template->renderFirstColumn = $template->dataCount && $this->groupActions !== NULL;
		$template->renderFilterRow = $template->filterButtons !== NULL && ( $template->isFiltered || $template->dataCount );
		$template->renderLastColumn = $template->renderFilterRow || $this->rowActions !== NULL;
		$template->renderFooter = $template->dataCount && ( $this->groupActions !== NULL || $this->timeline ) && ( $this->groupActions !== NULL || $this->countAll === NULL || $template->dataCount < $this->countAll || $this->page !== 1 ); // see http://www.wolframalpha.com/input/?i=%21%28+%28+%28%21a+%26%26+%21b%29+%7C%7C+%21c+%29+%7C%7C+%28%21a+%26%26+b+%26%26+d+%26%26+e+%26%26+f%29+%29
		$template->columnCount = count($template->columns) + ( $template->renderFirstColumn ? 1 : 0 ) + ( $template->renderLastColumn ? 1 : 0 );
		$template->isActiveInlineEdit = $this->inlineEditContainerFactory !== NULL;
		$template->inlineEditPrimary = $this->inlineEditPrimary;
		$template->render();
	}
}
