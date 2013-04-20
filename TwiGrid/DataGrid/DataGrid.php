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
use Nette\Templating\IFileTemplate;
use Nette\Localization\ITranslator;


/** @property-read mixed $data */
class DataGrid extends Nette\Application\UI\Control
{

	/** @persistent bool */
	public $poluted = FALSE;



	// === sorting ===========

	/** @persistent */
	public $orderBy = NULL;

	/** @persistent */
	public $orderDesc = FALSE;

	/** @var array */
	private $defaultOrderBy = NULL;



	// === filtering ===========

	/** @persistent array */
	public $filters = array();

	/** @var array */
	private $defaultFilters = NULL;

	/** @var Nette\Callback */
	private $filterFactory = NULL;



	// === inline editing ===========

	/** @persistent string|NULL */
	public $iePrimary = NULL;

	/** @var Nette\Callback */
	private $ieContainerFactory = NULL;

	/** @var Nette\Callback */
	private $ieProcessCallback = NULL;



	// === pagination ===========

	/** @persistent int */
	public $page = 1;

	/** @var int */
	private $itemsPerPage = NULL;

	/** @var Nette\Callback */
	private $itemCounter = NULL;

	/** @var int */
	private $itemCount = NULL;

	/** @var int */
	private $pageCount = NULL;



	// === data ===========

	/** @var Record */
	private $record;

	/** @var Nette\Callback */
	private $dataLoader = NULL;

	/** @var array|\Traversable */
	private $data = NULL;



	// === sessions ===========

	/** @var Nette\Http\Session */
	private $session;

	/** @var string */
	private $sessNamespace;



	// === l10n ===========

	/** @var ITranslator */
	private $translator = NULL;



	// === rendering ===========

	/** @var string */
	private $templateFile = NULL;



	// === LIFE CYCLE ======================================================

	/** @param  Nette\Http\Session */
	function __construct(Nette\Http\Session $s)
	{
		parent::__construct();
		$this->session = $s;
		$this->record = new Record;
		$this->sessNamespace = __CLASS__ . '-' . $this->name;
	}



	/**
	 * @param  Nette\ComponentModel\IComponent
	 * @return void
	 */
	protected function attached($presenter)
	{
		parent::attached($presenter);
		!isset($this->presenter->payload->twiGrid)
			&& ($this->presenter->payload->twiGrid['forms'] = $this->presenter->payload->twiGrid = array());
	}



	/**
	 * @param  array
	 * @return void
	 */
	function loadState(array $params)
	{
		parent::loadState($params);

		!$this->poluted && !$this->isInDefaultState() && ($this->poluted = TRUE);

		if (!$this->poluted) {
			$this->defaultOrderBy !== NULL
				&& ($this->orderBy = $this->defaultOrderBy[0]) && ($this->orderDesc = $this->defaultOrderBy[1]);

			$this->defaultFilters !== NULL && $this->setFilters($this->defaultFilters, FALSE);
			($this->defaultOrderBy !== NULL || $this->defaultFilters !== NULL) && ($this->poluted = TRUE);
		}

		$this->orderBy !== NULL
			&& $this['columns']->getComponent($this->orderBy)->setOrderedBy(TRUE, $this->orderDesc);

		$this->validateState();
	}



	/** @return void */
	protected function validateState()
	{
		if ($this->getColumns() === NULL) {
			throw new Nette\InvalidStateException("No columns set.");
		}

		if ($this->dataLoader === NULL) {
			throw new Nette\InvalidStateException("Data loader not set.");
		}

		if ($this->record->primaryKey === NULL) {
			throw new Nette\InvalidStateException("Primary key not set.");
		}

		if ($this->iePrimary !== NULL && $this->ieContainerFactory === NULL) {
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
	 * @return DataGrid
	 */
	protected function refreshState($cancelInlineEditing = TRUE)
	{
		$cancelInlineEditing && ($this->iePrimary = NULL);
		!$this->presenter->isAjax() && $this->redirect('this');
		return $this;
	}



	// === L10N ======================================================

	/**
	 * @param  ITranslator
	 * @return DataGrid
	 */
	function setTranslator(ITranslator $translator)
	{
		$this->translator = $translator;
		return $this;
	}



	/**
	 * @param  string
	 * @param  int|NULL
	 * @return string
	 */
	function translate($s, $count = NULL)
	{
		return $this->translator === NULL ? sprintf((string) $s, $count)
			: $this->translator->translate((string) $s, $count);
	}



	// === COLUMNS ======================================================

	/**
	 * @param  string
	 * @param  string
	 * @return Column
	 */
	function addColumn($name, $label = NULL)
	{
		!isset($this['columns']) && ($this['columns'] = new Nette\ComponentModel\Container);
		$c = new Column($this->translate($label === NULL ? $name : $label));
		$this['columns']->addComponent($c, $name);
		return $c;
	}



	/** @return \ArrayIterator|NULL */
	function getColumns()
	{
		return isset($this['columns']) ? $this['columns']->components : NULL;
	}



	/** @return array */
	function getColumnNames()
	{
		$names = array_keys(iterator_to_array($this->getColumns()));
		return array_merge(array_combine($this->record->primaryKey, $this->record->primaryKey), $names);
	}



	// === ACTIONS ======================================================

	/**
	 * @param  string
	 * @param  string
	 * @param  mixed
	 * @return RowAction
	 */
	function addRowAction($name, $label, $callback)
	{
		!isset($this['rowActions']) && ($this['rowActions'] = new Nette\ComponentModel\Container);
		$a = new RowAction($label, Nette\Callback::create($callback));
		$this['rowActions']->addComponent($a, $name);
		return $a;
	}



	/** @return \ArrayIterator|NULL */
	function getRowActions()
	{
		return isset($this['rowActions']) ? $this['rowActions']->components : NULL;
	}



	/**
	 * @param  string
	 * @param  string
	 * @param  string|NULL
	 */
	function handleRowAction($action, $primary, $token = NULL)
	{
		$a = $this['rowActions']->getComponent($action);
		if (!$a->protected || Helpers::checkCsrfToken($this->session, $this->sessNamespace, $token)) {
			$a->callback->invokeArgs(array($this->record->stringToPrimary($primary)));
			$this->refreshState();
			$this->invalidate(TRUE, TRUE, 'body', 'footer');

		} else {
			$this->flashMessage('Security token does not match.', 'error');
			$this->redirect('this');
		}
	}



	/**
	 * @param  string
	 * @param  string
	 * @param  mixed
	 * @return Action
	 */
	function addGroupAction($name, $label, $callback)
	{
		!isset($this['groupActions']) && ($this['groupActions'] = new Nette\ComponentModel\Container);
		$a = new Action($label, Nette\Callback::create($callback));
		$this['groupActions']->addComponent($a, $name);
		return $a;
	}



	/** @return \ArrayIterator|NULL */
	function getGroupActions()
	{
		return isset($this['groupActions']) ? $this['groupActions']->components : NULL;
	}



	// === SORTING ======================================================

	/** @return void */
	function handleSort()
	{
		$this->refreshState();
		$this->invalidate(TRUE, TRUE, 'header-sort', 'body', 'footer');
	}



	/**
	 * @param  string
	 * @param  bool
	 * @return DataGrid
	 */
	function setDefaultOrderBy($column, $desc = FALSE)
	{
		$this->defaultOrderBy = array((string) $column, (bool) $desc);
		return $this;
	}



	// === FILTERING ======================================================

	/**
	 * @param  mixed
	 * @return DataGrid
	 */
	function setFilterFactory($factory)
	{
		$this->filterFactory = Nette\Callback::create($factory);
		return $this;
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
		Helpers::recursiveKSort($filters);
		($diff = $this->filters !== $filters) && (($this->filters = $filters) || TRUE) && $this->handlePaginate(1, FALSE);
		$refresh && $this->refreshState($diff)
			&& $diff && $this->invalidate(TRUE, TRUE, 'header-sort', 'filter-controls', 'body', 'footer');

		return $this;
	}



	// === DATA LOADING ======================================================

	/**
	 * @param  string|array
	 * @return DataGrid
	 */
	function setPrimaryKey($primaryKey)
	{
		$this->record->setPrimaryKey($primaryKey);
		return $this;
	}



	/**
	 * @param  mixed
	 * @return DataGrid
	 */
	function setDataLoader($loader)
	{
		$this->dataLoader = Nette\Callback::create($loader);
		return $this;
	}



	/** @return array|\Traversable */
	function getData()
	{
		if ($this->data === NULL) {
			$order = array();
			if ($this->orderBy !== NULL) {
				$order[$this->orderBy] = $this->orderDesc;

				foreach ($this->record->primaryKey as $column) {
					$order[$column] = $this->orderDesc;
				}
			}

			$args = array(
				$this,
				$this->getColumnNames(),
				$this->filters,
				$order,
			);

			if ($this->itemsPerPage !== NULL) { // validate page & append limit & offset
				$this->initPagination();
				$args[] = $this->itemsPerPage;
				$args[] = ($this->page - 1) * $this->itemsPerPage;
			}

			$this->data = $this->dataLoader->invokeArgs($args);
		}

		return $this->data;
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
	 * API:
	 * $c->invalidate( [bool $data, [bool $form, ]] [string $snippet1 [, string $snippet2 [, ...]]] )
	 *
	 * @param  bool|string|NULL
	 * @return void
	 */
	protected function invalidate($reloadData = TRUE, $reloadForm = FALSE)
	{
		$snippets = func_get_args();
		!is_bool($reloadData) ? ($reloadData = TRUE) : array_shift($snippets);
		!is_bool($reloadForm) ? ($reloadForm = FALSE) : array_shift($snippets);

		$reloadData && ($this->data = NULL);
		if ($reloadForm) { unset($this['form']); }

		reset($snippets) === FALSE && ($snippets[] = NULL);
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
		$this->ieContainerFactory = Nette\Callback::create($containerCb);
		$this->ieProcessCallback = Nette\Callback::create($processCb);
	}



	/**
	 * @param  string
	 * @return void
	 */
	protected function activateInlineEditing($primary)
	{
		$this->iePrimary = $primary;
		$this->refreshState(FALSE);
		$this->invalidate(FALSE, TRUE, 'body');
	}



	/**
	 * @param  bool
	 * @return void
	 */
	protected function deactivateInlineEditing($dataAsWell = TRUE)
	{
		$this->refreshState();
		$this->invalidate($dataAsWell, TRUE, 'body');
	}



	// === PAGINATION ======================================================

	/**
	 * @param  int
	 * @param  mixed
	 * @return DataGrid
	 */
	function setPagination($itemsPerPage, $itemCounter)
	{
		$this->itemsPerPage = max(0, (int) $itemsPerPage);
		$this->itemCounter = Nette\Callback::create($itemCounter);
		return $this;
	}



	/**
	 * @param  int
	 * @param  bool
	 * @return void
	 */
	function handlePaginate($p, $refresh = TRUE)
	{
		if ($this->itemsPerPage !== NULL) {
			$this->initPagination();
			$p = Helpers::fixPage($p, $this->pageCount);
			$this->page !== $p && ($this->page = $p) && $this->invalidate('body', 'footer');
		}

		$refresh && $this->refreshState();
	}



	/** @return DataGrid */
	protected function initPagination()
	{
		if ($this->itemCount === NULL) {
			$this->itemCount = max(0, (int) $this->itemCounter->invokeArgs(array($this->getColumnNames(), $this->filters)));
			$this->pageCount = (int) ceil($this->itemCount / $this->itemsPerPage);
			$this->page = Helpers::fixPage($this->page, $this->pageCount);
		}

		return $this;
	}



	/** @return int|NULL */
	function getPageCount()
	{
		return $this->pageCount;
	}



	/** @return int|NULL */
	function getItemCount()
	{
		return $this->itemCount;
	}



	// === FORM BUILDING ======================================================

	/** @return Forms\Form */
	protected function createComponentForm()
	{
		$form = new Forms\Form;
		$this->translator !== NULL && $form->setTranslator($this->translator);
		$form->addProtection();

		$form->onSuccess[] = $this->processForm;
		$form->onSubmit[] = $this->formSubmitted;
		return $form;
	}



	/** @return DataGrid */
	function addFilterCriteria()
	{
		$this->filterFactory !== NULL
			&& $this['form']->addFilterCriteria($this->filterFactory, $this->filters);

		return $this;
	}



	/** @return DataGrid */
	function addFilterButtons()
	{
		$this->filterFactory !== NULL
			&& $this['form']->addFilterButtons(reset($this->filters) !== FALSE);

		return $this;
	}



	/** @return DataGrid */
	function addGroupActionCheckboxes()
	{
		$this->groupActions !== NULL
			&& $this['form']->addGroupActionCheckboxes($this->record->primaryToString);

		return $this;
	}



	/** @return DataGrid */
	function addGroupActionButtons()
	{
		$this->groupActions !== NULL
			&& $this['form']->addGroupActionButtons($this->getGroupActions());

		return $this;
	}



	/** @return DataGrid */
	function addInlineEditControls()
	{
		$this->ieContainerFactory !== NULL
			&& $this['form']->addInlineEditControls(
				$this->getData, $this->record->primaryToString, $this->ieContainerFactory, $this->iePrimary
			);

		return $this;
	}



	/** @return DataGrid */
	function addPaginationControls()
	{
		$this->itemsPerPage !== NULL
			&& $this->initPagination()
			&& $this['form']->addPaginationControls($this->page, $this->pageCount);

		return $this;
	}



	/**
	 * @param  Forms\Form
	 * @return void
	 */
	function formSubmitted(Forms\Form $form)
	{
		$this->invalidate(FALSE, 'form-errors');
	}



	/**
	 * @param  Forms\Form
	 * @return void
	 */
	function processForm(Forms\Form $form)
	{
		// detect submit button by lazy buttons appending (beginning with the most lazy ones)
		$this->addFilterButtons();
		if ($form->submitted === TRUE) {
			$this->addGroupActionButtons();

			if ($form->submitted === TRUE) {
				$this->addPaginationControls();

				if ($form->submitted === TRUE) {
					$this->addInlineEditControls();
				}
			}
		}

		$button = $form->submitted;
		$name = $button->name;
		$path = $button->parent->lookupPath('TwiGrid\\Forms\\Form');

		if ("$path-$name" === 'filters-buttons-filter') {
			$this->addFilterCriteria();
			($criteria = $form->getFilterCriteria()) !== NULL && $this->setFilters($criteria);

		} elseif ("$path-$name" === 'filters-buttons-reset') {
			$this->setFilters(array());
			$this->defaultFilters !== NULL && ($this->poluted = TRUE);

		} elseif ("$path-$name" === 'pagination-buttons-change') {
			$this->handlePaginate($form->getPage());

		} elseif ($path === 'actions-buttons') {
			if (($checked = $form->getCheckedRecords($this->record->primaryToString, $this->record->stringToPrimary)) !== NULL) {
				$primaries = array();
				foreach ($checked as $primaryString) {
					$primaries[] = $this->record->stringToPrimary($primaryString);
				}

				$this['groupActions']->getComponent($name)->callback->invokeArgs(array($primaries));
				$this->refreshState();
				$this->invalidate(TRUE, TRUE, 'body', 'footer');
			}

		} elseif ($path === 'inline-buttons') {
			if ($name === 'edit') {
				if (($values = $form->getInlineValues()) !== NULL) {
					$this->ieProcessCallback->invokeArgs(
						array($this->record->stringToPrimary($this->iePrimary), $values)
					);

					$this->deactivateInlineEditing();
				}

			} elseif ($name === 'cancel') {
				$this->deactivateInlineEditing(FALSE);

			} else {
				$this->activateInlineEditing($button->primary);
			}
		}
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



	/** @return bool */
	protected function passForm()
	{
		return !$this->presenter->isAjax()
			|| $this->isControlInvalid('form-errors')
			|| $this->isControlInvalid('filter-controls')
			|| $this->isControlInvalid('body')
			|| $this->isControlInvalid('footer');
	}



	/** @return void */
	function render()
	{
		$template = $this->createTemplate();

		$template->grid = $this;
		$template->defaultTemplate = __DIR__ . '/DataGrid.latte';
		$this->templateFile === NULL && ($this->templateFile = $template->defaultTemplate);
		$template->setFile($this->templateFile);

		$template->registerHelper('translate', $this->translate);
		$template->registerHelper('primaryToString', $this->record->primaryToString);
		$template->registerHelper('getValue', $this->record->getValue);

		$this->isControlInvalid() && $this->invalidate(FALSE, 'flashes');
		$this->passForm() && ($template->form = $template->_form = $form = $this['form'])
				&& $this->presenter->payload->twiGrid['forms'][$form->elementPrototype->id] = (string) $form->getAction();
		$template->columns = $this->getColumns();
		$template->data = $this->getData;
		$template->csrfToken = Helpers::getCsrfToken($this->session, $this->sessNamespace);
		$template->rowActions = $this->getRowActions();
		$template->hasRowActions = $template->rowActions !== NULL;
		$template->groupActions = $this->getGroupActions();
		$template->hasGroupActions = $template->groupActions !== NULL;
		$template->hasFilters = $this->filterFactory !== NULL;
		$template->hasInlineEdit = $this->ieContainerFactory !== NULL;
		$template->iePrimary = $this->iePrimary;
		$template->isPaginated = $this->itemsPerPage !== NULL;
		$template->columnCount = count($template->columns)
				+ ($template->hasGroupActions ? 1 : 0)
				+ ($template->hasFilters || $template->hasRowActions ? 1 : 0);

		$template->render();
	}

}
