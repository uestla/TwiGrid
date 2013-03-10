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
	protected $defaultOrderBy = NULL;



	// === filtering ===========

	/** @persistent array */
	public $filters = array();

	/** @var array */
	protected $defaultFilters = NULL;

	/** @var Nette\Callback */
	protected $filterFactory = NULL;



	// === inline editing ===========

	/** @persistent string|NULL */
	public $iePrimary = NULL;

	/** @var Nette\Callback */
	protected $ieContainerFactory = NULL;

	/** @var Nette\Callback */
	protected $ieProcessCallback = NULL;



	// === data ===========

	/** @var Record */
	protected $record;

	/** @var Nette\Callback */
	protected $dataLoader = NULL;

	/** @var array|\Traversable */
	protected $data = NULL;



	// === actions ===========

	/** @var array */
	protected $rowActions = NULL;

	/** @var array */
	protected $groupActions = NULL;

	/** @var Nette\Http\Session */
	protected $session;



	// === l10n ===========

	/** @var ITranslator */
	protected $translator = NULL;



	// === rendering ===========

	/** @var string */
	protected $templateFile = NULL;



	// === LIFE CYCLE ======================================================

	/** @param  Nette\Http\Session */
	function __construct(Nette\Http\Session $s)
	{
		parent::__construct();
		$this->record = new Record;
		$this->session = $s;
	}



	/**
	 * @param  Nette\ComponentModel\IComponent
	 * @return void
	 */
	protected function attached($presenter)
	{
		parent::attached($presenter);
		!isset($this->presenter->payload->twiGrid) && ( $this->presenter->payload->twiGrid = $this->presenter->payload->twiGrid['forms'] = array() );
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
			$this->defaultOrderBy !== NULL && ( $this->orderBy = $this->defaultOrderBy[0] ) && ( ($this->orderDesc = $this->defaultOrderBy[1]) || TRUE );
			$this->defaultFilters !== NULL && $this->setFilters( $this->defaultFilters, FALSE );
			($this->defaultOrderBy !== NULL || $this->defaultFilters !== NULL) && ($this->poluted = TRUE);
		}

		$this->orderBy !== NULL && $this['columns']->getComponent( $this->orderBy )->setOrderedBy( TRUE, $this->orderDesc );
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
	 * @return TRUE
	 */
	protected function refreshState($cancelInlineEditing = TRUE)
	{
		$cancelInlineEditing && ($this->iePrimary = NULL);
		!$this->presenter->isAjax() && $this->redirect('this');
		return TRUE;
	}



	// === CSRF TOKEN ======================================================

	/**
	 * @param  bool
	 * @param  Nette\Http\SessionSection
	 * @return string|NULL
	 */
	protected function getCsrfToken($generate = TRUE, & $session = NULL)
	{
		$session = $this->session->getSection( __CLASS__ . '-' . $this->name );

		if ($this->rowActions === NULL) {
			unset($session->csrfToken);

		} else {
			$session->setExpiration('+ 5 minutes', 'csrfToken');
			return isset($session->csrfToken) ? $session->csrfToken : ( $generate ? ( $session->csrfToken = Nette\Utils\Strings::random(16) ) : NULL );
		}

		return NULL;
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
		return $this->translator === NULL ? $s : $this->translator->translate($s, $count);
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
		$this['columns']->addComponent( $c = new Column( $this->translate( $label === NULL ? $name : $label ) ), $name );
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
		$names = array_keys( iterator_to_array($this->getColumns()) );
		return array_combine($names, $names);
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
		$this->rowActions === NULL && ($this->rowActions = array());
		if (isset($this->rowActions[$name])) {
			throw new Nette\InvalidArgumentException("Row action '$name' already set.");
		}

		$this->rowActions[$name] = array(
			'label' => $this->translate( (string) $label ),
			'callback' => Nette\Callback::create($callback),
			'confirmation' => $confirmation === NULL ? $confirmation : $this->translate($confirmation),
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
		if (($sToken = $this->getCsrfToken(FALSE, $session)) !== NULL && $token === $sToken) {
			unset($session->csrfToken);
			$this->rowActions[$action]['callback']( $this->record->stringToPrimary($primary) );
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
	 * @param  string|NULL
	 * @return DataGrid
	 */
	function addGroupAction($name, $label, $callback, $confirmation = NULL)
	{
		$this->groupActions === NULL && ($this->groupActions = array());
		if (isset($this->groupActions[$name])) {
			throw new Nette\InvalidArgumentException("Group action '$name' already set.");
		}

		$this->groupActions[$name] = array(
			'label' => $this->translate( (string) $label ),
			'callback' => Nette\Callback::create($callback),
			'confirmation' => $confirmation === NULL ? $confirmation : $this->translate($confirmation),
		);
		return $this;
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
		( $diff = $this->filters !== $filters ) && ($this->filters = $filters);
		$refresh && $this->refreshState( $diff ) && $diff && $this->invalidate(TRUE, TRUE, 'header-sort', 'filter-controls', 'body', 'footer');
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
				$order[ $this->orderBy ] = $this->orderDesc;

				foreach ($this->record->primaryKey as $column) {
					$order[ $column ] = $this->orderDesc;
				}
			}

			$this->data = $this->dataLoader->invokeArgs( array(
				$this,
				array_merge( array_combine( $this->record->primaryKey, $this->record->primaryKey ), $this->getColumnNames() ),
				$order,
				$this->filters,
			) );
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
		$this->invalidate( FALSE, TRUE, 'body' );
	}



	/**
	 * @param  bool
	 * @return void
	 */
	protected function deactivateInlineEditing($dataAsWell = TRUE)
	{
		$this->refreshState();
		$this->invalidate( $dataAsWell, TRUE, 'body' );
	}



	// === FORM BUILDING ======================================================

	/** @return Forms\Form */
	protected function createComponentForm()
	{
		$form = new Forms\Form;
		$this->translator !== NULL && $form->setTranslator( $this->translator );
		$this->filterFactory !== NULL && $form->setFilterFactory( $this->filterFactory ) && $form->addFilterButtons( reset($this->filters) !== FALSE );
		$this->groupActions !== NULL && $form->addGroupActionButtons( $this->groupActions );

		$form->addProtection();
		$form->onSuccess[] = $this->processForm;
		$form->onSubmit[] = $this->formSubmitted;
		return $form;
	}



	/** @return void */
	function addGroupActionCheckboxes()
	{
		$this->groupActions !== NULL
				&& $this['form']->addGroupActionCheckboxes( $this->record->primaryToString );
	}



	/** @return void */
	function addInlineEditControls()
	{
		$this->ieContainerFactory !== NULL
				&& $this['form']->addInlineEditControls( $this->getData, $this->record->primaryToString, $this->ieContainerFactory, $this->iePrimary );
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
		$button = $form->submitted;

		if ($button === TRUE) { // submitted by inline edit button that is not attached yet
			$this->addInlineEditControls();
			$button = $form->submitted; // refresh the submit button
		}

		$name = $button->name;
		$path = $button->parent->lookupPath('TwiGrid\\Forms\\Form');

		if ("$path-$name" === 'filters-buttons-filter') {
			($criteria = $form->getFilterCriteria()) !== NULL && $this->setFilters($criteria);

		} elseif ("$path-$name" === 'filters-buttons-reset') {
			$this->poluted = TRUE;
			$this->setFilters( array() );

		} elseif ($path === 'actions-buttons') {
			if (($checked = $form->getCheckedRecords( $this->record->primaryToString, $this->record->stringToPrimary )) !== NULL) {
				$primaries = array();
				foreach ($checked as $primaryString) {
					$primaries[] = $this->record->stringToPrimary( $primaryString );
				}

				$this->groupActions[ $name ]['callback']( $primaries );
				$this->refreshState();
				$this->invalidate(TRUE, TRUE, 'body', 'footer');
			}

		} elseif ($path === 'inline-buttons') {
			if ($name === 'edit') {
				if (($values = $form->getInlineValues()) !== NULL) {
					$this->ieProcessCallback->invokeArgs(
						array( $this->record->stringToPrimary( $this->iePrimary ), $values )
					);

					$this->deactivateInlineEditing();
				}

			} elseif ($name === 'cancel') {
				$this->deactivateInlineEditing( FALSE );

			} else {
				$this->activateInlineEditing( $button->primary );
			}
		}
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
		$template->defaultTemplate = __DIR__ . '/DataGrid.latte';
		$this->templateFile === NULL && ($this->templateFile = $template->defaultTemplate);
		$template->setFile( $this->templateFile );

		$template->registerHelper('translate', $this->translate);
		$template->registerHelper('primaryToString', $this->record->primaryToString);
		$template->registerHelper('getValue', $this->record->getValue);

		$this->isControlInvalid() && $this->invalidate(FALSE, 'flashes');
		$this->passForm() && ($template->form = $template->_form = $form = $this['form'])
				&& $this->presenter->payload->twiGrid['forms'][ $form->elementPrototype->id ] = (string) $form->getAction();
		$template->columns = $this->getColumns();
		$template->filterButtons = $this->getFilterButtons();
		$template->data = $this->getData;
		$template->rowActions = $this->rowActions;
		$template->csrfToken = $this->getCsrfToken();
		$template->groupActions = $this->groupActions;
		$template->hasRowActions = $this->rowActions !== NULL;
		$template->hasGroupActions = $this->groupActions !== NULL;
		$template->hasFilters = $template->filterButtons !== NULL;
		$template->hasInlineEdit = $this->ieContainerFactory !== NULL;
		$template->iePrimary = $this->iePrimary;
		$template->columnCount = count($template->columns) + ( $template->hasGroupActions ? 1 : 0 ) + ( $template->hasFilters || $template->hasRowActions ? 1 : 0 );
		$template->render();
	}

}
