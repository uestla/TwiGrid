<?php

/**
 * This file is part of the TwiGrid component
 *
 * @license  MIT
 * @author   Petr Kessler (https://kesspess.cz)
 * @link     https://github.com/uestla/twigrid
 */

namespace TwiGrid;

use TwiGrid\Components\Column;
use Nette\Utils\Callback as NCallback;
use Nette\Application\UI\Control as NControl;
use Nette\Application\UI\Presenter as NPresenter;
use Nette\ComponentModel\Container as NContainer;
use Nette\Database\Table\Selection as NSelection;
use Nette\Http\SessionSection as NSessionSection;
use Nette\Localization\ITranslator as NITranslator;
use Nette\Forms\Controls\SubmitButton as NSubmitButton;


class DataGrid extends NControl
{

	/**
	 * @var bool
	 * @persistent
	 */
	public $polluted = FALSE;


	// === SORTING ===========

	/**
	 * @var array
	 * @persistent
	 */
	public $orderBy = [];

	/** @var array */
	private $defaultOrderBy = NULL;

	/** @var bool */
	private $multiSort = TRUE;


	// === FILTERING ===========

	/**
	 * @var array
	 * @persistent
	 */
	public $filters = [];

	/** @var array */
	private $defaultFilters = NULL;

	/** @var callable */
	private $filterFactory = NULL;


	// === INLINE EDITING ===========

	/**
	 * @var string|NULL
	 * @persistent
	 */
	public $iePrimary = NULL;

	/** @var callable */
	private $ieContainerFactory = NULL;

	/** @var callable */
	private $ieProcessCallback = NULL;


	// === PAGINATION ===========

	/**
	 * @var int
	 * @persistent
	 */
	public $page = 1;

	/** @var int */
	private $itemsPerPage = NULL;

	/** @var callable */
	private $itemCounter = NULL;

	/** @var int */
	private $itemCount = NULL;

	/** @var int */
	private $pageCount = NULL;


	// === REFRESH ===========

	/** @var bool */
	private $refreshing = FALSE;


	// === DATA ===========

	/** @var RecordHandler */
	private $recordHandler = NULL;

	/** @var callable */
	private $dataLoader = NULL;

	/** @var array|\Traversable */
	private $data = NULL;


	// === SESSION ===========

	/** @var NSessionSection */
	private $session;


	// === LOCALIZATION ===========

	/** @var NITranslator */
	private $translator = NULL;


	// === RENDERING ===========

	/** @var string */
	private $templateFile = NULL;

	/** @var string */
	private $recordVariable = 'record';


	// === AJAX PAYLOAD ===========

	/** @var \stdClass */
	private $payload;


	// === LIFE CYCLE ======================================================

	/**
	 * @param  NPresenter $presenter
	 * @return void
	 */
	protected function attached($presenter)
	{
		if ($presenter instanceof NPresenter) {
			$this->build();
			parent::attached($presenter);
			$this->session = $presenter->getSession(__CLASS__ . '-' . $this->getName());

			if (!isset($presenter->payload->twiGrid)) {
				$this->payload = $presenter->payload->twiGrid = new \stdClass;
				$this->payload->forms = [];
			}
		}
	}


	/** @return void */
	protected function build()
	{}


	/** @inheritdoc */
	public function loadState(array $params)
	{
		parent::loadState(static::processParams($params));

		if (!$this->polluted && !$this->isInDefaultState()) {
			$this->polluted = TRUE;
		}

		if (!$this->polluted) {
			if ($this->defaultOrderBy !== NULL) {
				$this->orderBy = array_merge($this->defaultOrderBy, $this->orderBy);
				$this->polluted = TRUE;
			}

			if ($this->defaultFilters !== NULL) {
				$this->setFilters($this->defaultFilters);
				$this->polluted = TRUE;
			}
		}

		$i = 0;
		foreach ($this->orderBy as $column => $dir) {
			try {
				$this['columns']->getComponent($column)->setSortedBy(TRUE, $dir, $i++);

			} catch (\RuntimeException $e) {
				unset($this->orderBy[$column]);
			}

			if (!$this->multiSort && $i > 1) {
				unset($this->orderBy[$column]);
			}
		}

		$this->validateState();
	}


	/**
	 * @param  array $params
	 * @return array
	 */
	protected static function processParams(array $params)
	{
		if (isset($params['orderBy'])) {
			foreach ($params['orderBy'] as & $dir) {
				$dir = (bool) $dir;
			}
		}

		return $params;
	}


	/** @return void */
	protected function validateState()
	{
		if ($this->getColumns() === NULL) {
			throw new \RuntimeException('At least one column must be added using DataGrid::addColumn($name, $label).');
		}

		if ($this->dataLoader === NULL) {
			throw new \RuntimeException('Data loader callback must be set using DataGrid::setDataLoader($callback).');
		}

		if ($this->getRecordHandler()->getPrimaryKey() === NULL) {
			throw new \RuntimeException('Record primary key must be set using DataGrid::setPrimaryKey($key).');
		}

		if ($this->iePrimary !== NULL && $this->ieContainerFactory === NULL) {
			$this->iePrimary = NULL;
		}
	}


	/** @return bool */
	protected function isInDefaultState()
	{
		foreach (static::getReflection()->getPersistentParams() as $name => $meta) {
			if ($this->$name !== $meta['def']) {
				return FALSE;
			}
		}

		return TRUE;
	}


	/**
	 * @param  bool $resetInlineEdit
	 * @return DataGrid
	 */
	protected function refreshState($resetInlineEdit = TRUE)
	{
		if ($resetInlineEdit) {
			$this->iePrimary = NULL;
		}

		if (!$this->presenter->isAjax()) {
			$this->redirect('this');
		}

		return $this;
	}


	// === LOCALIZATION ======================================================

	/** @return NITranslator */
	public function getTranslator()
	{
		if ($this->translator === NULL) {
			$this->translator = new Components\Translator;
		}

		return $this->translator;
	}


	/**
	 * @param  NITranslator $translator
	 * @return DataGrid
	 */
	public function setTranslator(NITranslator $translator)
	{
		$this->translator = $translator;
		return $this;
	}


	/**
	 * @param  string $s
	 * @param  int $count
	 * @return string
	 */
	public function translate($s, $count = NULL)
	{
		return $this->getTranslator()->translate($s, $count);
	}


	// === COLUMNS ======================================================

	/**
	 * @param  string $name
	 * @param  string $label
	 * @return Components\Column
	 */
	public function addColumn($name, $label = NULL)
	{
		if (!isset($this['columns'])) {
			$this['columns'] = new NContainer;
		}

		$column = new Components\Column($label === NULL ? $name : $label);
		$this['columns']->addComponent($column, $name);

		return $column;
	}


	/** @return \ArrayIterator|Column[]|NULL */
	public function getColumns()
	{
		return isset($this['columns']) ? $this['columns']->getComponents() : NULL;
	}


	/** @return bool */
	public function hasManySortableColumns()
	{
		$hasMany = FALSE;

		foreach ($this->getColumns() as $column) {
			if ($column->isSortable()) {
				if ($hasMany) { // 2nd sortable -> has many
					return TRUE;

				} else {
					$hasMany = TRUE;
				}
			}
		}

		return FALSE;
	}


	// === ROW ACTIONS ======================================================

	/**
	 * @param  string $name
	 * @param  string $label
	 * @param  callable $callback
	 * @return Components\RowAction
	 */
	public function addRowAction($name, $label, callable $callback)
	{
		if (!isset($this['rowActions'])) {
			$this['rowActions'] = new NContainer;
		}

		$action = new Components\RowAction($label, $callback);
		$this['rowActions']->addComponent($action, $name);

		return $action;
	}


	/** @return \ArrayIterator|NULL */
	public function getRowActions()
	{
		return isset($this['rowActions']) ? $this['rowActions']->getComponents() : NULL;
	}


	/**
	 * @param  string $action
	 * @param  string $primary
	 * @param  string|NULL $token
	 * @return void
	 */
	public function handleRowAction($action, $primary, $token = NULL)
	{
		$act = $this['rowActions']->getComponent($action);

		if (!$act->isProtected() || Helpers::checkCsrfToken($this->session, $token)) {
			$act->invoke($this->getRecordHandler()->findIn($primary, $this->getData()));
			$this->refreshState();
			$this->redraw(TRUE, TRUE, ['body', 'footer']);

		} else {
			$this->flashMessage('Security token does not match. Please try again.', 'error');
			$this->redirect('this');
		}
	}


	// === GROUP ACTIONS ======================================================

	/**
	 * @param  string $name
	 * @param  string $label
	 * @param  callable $callback
	 * @return Components\Action
	 */
	public function addGroupAction($name, $label, callable $callback)
	{
		if (!isset($this['groupActions'])) {
			$this['groupActions'] = new NContainer;
		}

		$action = new Components\Action($label, $callback);
		$this['groupActions']->addComponent($action, $name);

		return $action;
	}


	/** @return \ArrayIterator|NULL */
	public function getGroupActions()
	{
		return isset($this['groupActions']) ? $this['groupActions']->getComponents() : NULL;
	}


	// === SORTING ======================================================

	/**
	 * @param  array $orderBy
	 * @return void
	 */
	public function handleSort(array $orderBy)
	{
		$this->refreshState();
		$this->redraw(TRUE, TRUE, ['header-sort', 'body', 'footer']);
	}


	/**
	 * @param  string|array $column
	 * @param  bool $dir
	 * @return DataGrid
	 */
	public function setDefaultOrderBy($column, $dir = Components\Column::ASC)
	{
		if (is_array($column)) {
			$this->defaultOrderBy = $column;

		} else {
			$this->defaultOrderBy = [
				(string) $column => (bool) $dir,
			];
		}

		return $this;
	}


	/**
	 * @param  bool $bool
	 * @return DataGrid
	 */
	public function setMultiSort($bool = TRUE)
	{
		$this->multiSort = (bool) $bool;
		return $this;
	}


	/** @return bool */
	public function hasMultiSort()
	{
		return $this->multiSort;
	}


	// === FILTERING ======================================================

	/**
	 * @param  callable $factory
	 * @return DataGrid
	 */
	public function setFilterFactory(callable $factory)
	{
		$this->filterFactory = $factory;
		return $this;
	}


	/**
	 * @param  array $filters
	 * @return DataGrid
	 */
	public function setDefaultFilters(array $filters)
	{
		if ($this->filterFactory === NULL) {
			throw new \RuntimeException('Filter factory must be set using DataGrid::setFilterFactory($callback).');
		}

		$this->defaultFilters = $filters;
		return $this;
	}


	/**
	 * @param  array $filters
	 * @return DataGrid
	 */
	protected function setFilters(array $filters)
	{
		Helpers::recursiveKSort($filters);
		$this->filters = $filters;

		$this->redraw(TRUE, TRUE, ['header-sort', 'filter-controls', 'body', 'footer']);
		$this->setPage(1);

		return $this;
	}


	// === REFRESH ======================================================

	/** @return void */
	public function handleRefresh()
	{
		$this->refreshing = TRUE;
		$this->redraw(TRUE, TRUE);
		$this->refreshState(FALSE);
	}


	// === DATA LOADING ======================================================

	/** @return RecordHandler */
	private function getRecordHandler()
	{
		if ($this->recordHandler === NULL) {
			$this->recordHandler = new RecordHandler;
		}

		return $this->recordHandler;
	}


	/**
	 * @param  string|array $primaryKey
	 * @return DataGrid
	 */
	public function setPrimaryKey($primaryKey)
	{
		$this->getRecordHandler()->setPrimaryKey(is_array($primaryKey) ? $primaryKey : func_get_args());
		return $this;
	}


	/**
	 * @param  callable $loader
	 * @return DataGrid
	 */
	public function setDataLoader(callable $loader)
	{
		$this->dataLoader = $loader;
		return $this;
	}


	/** @return array|\Traversable */
	public function getData()
	{
		if ($this->data === NULL) {
			$order = $this->orderBy;
			$primaryDir = count($order) ? end($order) : Components\Column::ASC;

			foreach ($this->getRecordHandler()->getPrimaryKey() as $column) {
				if (!isset($order[$column])) {
					$order[$column] = $primaryDir;
				}
			}

			$args = [
				$this->filters,
				$order,
			];

			if ($this->itemsPerPage !== NULL) { // validate page & append limit & offset
				$this->initPagination();
				$args[] = $this->itemsPerPage;
				$args[] = ($this->page - 1) * $this->itemsPerPage;
			}

			$this->data = NCallback::invokeArgs($this->dataLoader, $args);
		}

		return $this->data;
	}


	/** @return bool */
	public function hasData()
	{
		return count($this->getData());
	}


	/**
	 * @param  mixed|NULL $callback
	 * @return DataGrid
	 */
	public function setValueGetter($callback = NULL)
	{
		$this->getRecordHandler()->setValueGetter($callback);
		return $this;
	}


	/**
	 * @param  bool $reloadData
	 * @param  bool $reloadForm
	 * @param  string[] $snippets
	 * @return void
	 */
	protected function redraw($reloadData = TRUE, $reloadForm = FALSE, array $snippets = [NULL])
	{
		if ($reloadData) {
			$this->data = NULL;
		}

		if ($reloadForm) {
			unset($this['form']);
		}

		foreach ($snippets as $snippet) {
			$this->redrawControl($snippet);
		}
	}


	// === INLINE EDITING ======================================================

	/**
	 * @param  callable $containerCb
	 * @param  callable $processCb
	 * @return DataGrid
	 */
	public function setInlineEditing(callable $containerCb, callable $processCb)
	{
		$this->ieProcessCallback = $processCb;
		$this->ieContainerFactory = $containerCb;
	}


	/**
	 * @param  string $primary
	 * @return void
	 */
	protected function activateInlineEditing($primary)
	{
		$this->iePrimary = $primary;
		$this->refreshState(FALSE);
		$this->redraw(FALSE, TRUE, ['body']);
	}


	/**
	 * @param  bool $dataAsWell
	 * @return void
	 */
	protected function deactivateInlineEditing($dataAsWell = TRUE)
	{
		$this->refreshState();
		$this->redraw($dataAsWell, TRUE, ['body']);
	}


	// === PAGINATION ======================================================

	/**
	 * @param  int $itemsPerPage
	 * @param  callable $itemCounter
	 * @return DataGrid
	 */
	public function setPagination($itemsPerPage, callable $itemCounter = NULL)
	{
		$this->itemsPerPage = max(0, (int) $itemsPerPage);
		$this->itemCounter = $itemCounter;
		return $this;
	}


	/**
	 * @param  int $p
	 * @return void
	 */
	public function handlePaginate($p)
	{
		$this->paginate($p);
	}


	/**
	 * @param  int $page
	 * @return void
	 */
	protected function paginate($page)
	{
		$this->setPage($page);
		$this->refreshState();
		$this->redraw(TRUE, FALSE, ['body', 'footer']);
	}


	/**
	 * @param  int $page
	 * @return DataGrid
	 */
	protected function setPage($page)
	{
		if ($this->itemsPerPage !== NULL) {
			$this->page = (int) $page;
			$this->itemCount = NULL;

		} else {
			$this->page = 1;
		}

		return $this;
	}


	/** @return DataGrid */
	protected function initPagination()
	{
		if ($this->itemCount === NULL) {
			if ($this->itemCounter === NULL) { // fallback - fetch data with empty filters
				$data = NCallback::invoke($this->dataLoader, $this->filters, [], NULL, 0);

				if ($data instanceof NSelection) {
					$count = $data->count('*');

				} else {
					$count = count($data);
				}

			} else {
				$count = NCallback::invoke($this->itemCounter, $this->filters);
			}

			$this->itemCount = max(0, (int) $count);
			$this->pageCount = (int) ceil($this->itemCount / $this->itemsPerPage);
			$this->page = Helpers::fixPage($this->page, $this->pageCount);
		}

		return $this;
	}


	/** @return int|NULL */
	public function getPageCount()
	{
		return $this->pageCount;
	}


	/** @return int|NULL */
	public function getItemCount()
	{
		return $this->itemCount;
	}


	/** @return int|NULL */
	public function getItemsPerPage()
	{
		return $this->itemsPerPage;
	}


	// === FORM BUILDING ======================================================

	/** @return Form */
	protected function createComponentForm()
	{
		$form = new Form($this->getRecordHandler());
		$form->addProtection();
		$form->setTranslator($this->getTranslator());
		$form->onSuccess[] = [$this, 'processForm'];
		$form->onSubmit[] = [$this, 'formSubmitted'];

		return $form;
	}


	/** @return DataGrid */
	public function addFilterCriteria()
	{
		if ($this->filterFactory !== NULL) {
			$this->addFilterButtons();
			$this['form']->addFilterCriteria($this->filterFactory, $this->filters);
		}

		return $this;
	}


	/** @return DataGrid */
	public function addFilterButtons()
	{
		if ($this->filterFactory !== NULL) {
			$this['form']->addFilterButtons(count($this->filters));
		}

		return $this;
	}


	/** @return DataGrid */
	public function addGroupActionCheckboxes()
	{
		if ($this->getGroupActions() !== NULL) {
			$this->addGroupActionButtons();
			$this['form']->addGroupActionCheckboxes();
		}

		return $this;
	}


	/** @return DataGrid */
	public function addGroupActionButtons()
	{
		if ($this->getGroupActions() !== NULL) {
			$this['form']->addGroupActionButtons($this->getGroupActions());
		}

		return $this;
	}


	/** @return DataGrid */
	public function addInlineEditControls()
	{
		if ($this->ieContainerFactory !== NULL) {
			$this['form']->addInlineEditControls(
				$this->getData(),
				$this->ieContainerFactory,
				$this->iePrimary
			);
		}

		return $this;
	}


	/** @return DataGrid */
	public function addPaginationControls()
	{
		if ($this->itemsPerPage !== NULL) {
			$this->initPagination();
			$this['form']->addPaginationControls($this->page, $this->pageCount);
		}

		return $this;
	}


	/**
	 * @param  Form $form
	 * @return void
	 */
	public function formSubmitted(Form $form)
	{
		$this->redraw(FALSE, FALSE, ['form-errors']);
	}


	/**
	 * @param  Form $form
	 * @return void
	 */
	public function processForm(Form $form)
	{
		// detect submit button by lazy buttons appending (beginning with the most lazy ones)
		$this->addFilterButtons();
		if (($button = $form->isSubmitted()) === TRUE) {
			$this->addGroupActionButtons();

			if (($button = $form->isSubmitted()) === TRUE) {
				$this->addPaginationControls();

				if (($button = $form->isSubmitted()) === TRUE) {
					$this->addInlineEditControls();
					$button = $form->isSubmitted();
				}
			}
		}

		if ($button instanceof NSubmitButton) {
			$name = $button->getName();
			$path = $button->getParent()->lookupPath(Form::class);

			if ("$path-$name" === 'filters-buttons-filter') {
				$this->addFilterCriteria();
				$criteria = $form->getFilterCriteria();

				if ($criteria !== NULL) {
					$this->setFilters($criteria);
					$this->refreshState();
				}

			} elseif ("$path-$name" === 'filters-buttons-reset') {
				$this->setFilters([]);
				$this->refreshState();

				if ($this->defaultFilters !== NULL) {
					$this->polluted = TRUE;
				}

			} elseif ("$path-$name" === 'pagination-buttons-change') {
				$this->paginate($form->getPage());

			} elseif ($path === 'actions-buttons') {
				$checked = $form->getCheckedRecords();

				if ($checked !== NULL) {
					$records = [];
					foreach ($checked as $primaryString) {
						$record = $this->getRecordHandler()->findIn($primaryString, $this->getData());

						if ($record !== NULL) {
							$records[] = $record;
						}
					}

					$this['groupActions']->getComponent($name)->invoke($records);
					$this->refreshState();
					$this->redraw(TRUE, TRUE, ['body', 'footer']);
				}

			} elseif ($path === 'inline-buttons') {
				if ($name === 'edit') {
					$values = $form->getInlineValues();

					if ($values !== NULL) {
						NCallback::invoke($this->ieProcessCallback, $this->getRecordHandler()->findIn($this->iePrimary, $this->getData()), $values);
						$this->deactivateInlineEditing();
					}

				} elseif ($name === 'cancel') {
					$this->deactivateInlineEditing(FALSE);

				} else {
					$this->activateInlineEditing($button->getName());
				}
			}
		}
	}


	// === RENDERING ======================================================

	/**
	 * @param  string $templateFile
	 * @return DataGrid
	 */
	public function setTemplateFile($templateFile)
	{
		$this->templateFile = (string) $templateFile;
		return $this;
	}


	/**
	 * @param  string $name
	 * @return DataGrid
	 */
	public function setRecordVariable($name)
	{
		$this->recordVariable = (string) $name;
		return $this;
	}


	/** @return void */
	public function render()
	{
		$template = $this->createTemplate();

		$template->grid = $this;
		$template->defaultTemplate = __DIR__ . '/DataGrid.latte';
		$template->setFile($this->templateFile === NULL ? $template->defaultTemplate : $this->templateFile);
		$template->csrfToken = Helpers::getCsrfToken($this->session);

		$latte = $template->getLatte();
		$latte->addFilter('translate', [$this, 'translate']);
		$latte->addFilter('primaryToString', [$this->getRecordHandler(), 'getPrimaryHash']);
		$latte->addFilter('getValue', [$this->getRecordHandler(), 'getValue']);
		$latte->addFilter('sortLink', function (Components\Column $c, $m = Helpers::SORT_LINK_SINGLE) {
			return Helpers::createSortLink($this, $c, $m);
		});

		if ($this->isControlInvalid()) {
			$this->redraw(FALSE, FALSE, ['flashes']);
		}

		$template->form = $form = $this['form'];

		if ($this->presenter->isAjax()) {
			$this->payload->id = $this->getSnippetId();
			$this->payload->url = $this->link('this');
			$this->payload->refreshing = $this->refreshing;
			$this->payload->refreshSignal = $this->link('refresh!');
			$this->payload->forms[$form->getElementPrototype()->id] = (string) $form->getAction();
		}

		if ($this->presenter->isAjax()) {
			$latte->addProvider('formsStack', [$form]);
		}

		$template->columns = $this->getColumns();
		$template->dataLoader = [$this, 'getData'];
		$template->recordVariable = $this->recordVariable;

		$template->hasFilters = $this->filterFactory !== NULL;

		$template->rowActions = $this->getRowActions();
		$template->hasRowActions = $template->rowActions !== NULL;

		$template->groupActions = $this->getGroupActions();
		$template->hasGroupActions = $template->groupActions !== NULL;

		$template->hasInlineEdit = $this->ieContainerFactory !== NULL;
		$template->iePrimary = $this->iePrimary;

		$template->isPaginated = $this->itemsPerPage !== NULL;

		$template->columnCount = count($template->columns)
				+ ($template->hasGroupActions ? 1 : 0)
				+ ($template->hasFilters || $template->hasRowActions || $template->hasInlineEdit ? 1 : 0);

		$template->render();
	}

}
