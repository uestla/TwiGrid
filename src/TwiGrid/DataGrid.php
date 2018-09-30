<?php declare(strict_types=1);

/**
 * This file is part of the TwiGrid component
 *
 * @license  MIT
 * @author   Petr Kessler (https://kesspess.cz)
 * @link     https://github.com/uestla/twigrid
 */

namespace TwiGrid;

use TwiGrid\Components\Action;
use TwiGrid\Components\Column;
use TwiGrid\Components\RowAction;
use Nette\Utils\Callback as NCallback;
use Nette\Bridges\ApplicationLatte\Template;
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

	/** @var array|NULL */
	private $defaultOrderBy = NULL;

	/** @var bool */
	private $multiSort = TRUE;


	// === FILTERING ===========

	/**
	 * @var array
	 * @persistent
	 */
	public $filters = [];

	/** @var array|NULL */
	private $defaultFilters = NULL;

	/** @var callable|NULL */
	private $filterFactory = NULL;


	// === INLINE EDITING ===========

	/**
	 * @var string|NULL
	 * @persistent
	 */
	public $iePrimary = NULL;

	/** @var callable|NULL */
	private $ieContainerSetupCallback = NULL;

	/** @var callable|NULL */
	private $ieProcessCallback = NULL;


	// === PAGINATION ===========

	/**
	 * @var int
	 * @persistent
	 */
	public $page = 1;

	/** @var int|NULL */
	private $itemsPerPage = NULL;

	/** @var callable|NULL */
	private $itemCounter = NULL;

	/** @var int|NULL */
	private $itemCount = NULL;

	/** @var int|NULL */
	private $pageCount = NULL;


	// === REFRESH ===========

	/** @var bool */
	private $refreshing = FALSE;


	// === DATA ===========

	/** @var RecordHandler|NULL */
	private $recordHandler = NULL;

	/** @var callable|NULL */
	private $dataLoader = NULL;

	/** @var array|\Traversable|NULL */
	private $data = NULL;


	// === SESSION ===========

	/** @var NSessionSection */
	private $session;


	// === LOCALIZATION ===========

	/** @var NITranslator|NULL */
	private $translator = NULL;


	// === RENDERING ===========

	/** @var string|NULL */
	private $templateFile = NULL;

	/** @var string */
	private $recordVariable = 'record';


	// === AJAX PAYLOAD ===========

	/** @var \stdClass */
	private $payload;


	// === LIFE CYCLE ======================================================

	/** @param  NPresenter $presenter */
	protected function attached($presenter): void
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


	protected function build()
	{}


	/** @inheritdoc */
	public function loadState(array $params): void
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


	protected static function processParams(array $params): array
	{
		if (isset($params['orderBy'])) {
			foreach ($params['orderBy'] as & $dir) {
				$dir = (bool) $dir;
			}
		}

		return $params;
	}


	protected function validateState(): void
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

		if ($this->iePrimary !== NULL && $this->ieContainerSetupCallback === NULL) {
			$this->iePrimary = NULL;
		}
	}


	protected function isInDefaultState(): bool
	{
		foreach (static::getReflection()->getPersistentParams() as $name => $meta) {
			if ($this->$name !== $meta['def']) {
				return FALSE;
			}
		}

		return TRUE;
	}


	protected function refreshState(bool $resetInlineEdit = TRUE): self
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

	public function getTranslator(): NITranslator
	{
		if ($this->translator === NULL) {
			$this->translator = new Components\Translator;
		}

		return $this->translator;
	}


	public function setTranslator(NITranslator $translator): self
	{
		$this->translator = $translator;
		return $this;
	}


	public function translate(string $s, int $count = NULL): string
	{
		return $this->getTranslator()->translate($s, $count);
	}


	// === COLUMNS ======================================================

	public function addColumn(string $name, string $label = NULL): Column
	{
		if (!isset($this['columns'])) {
			$this['columns'] = new NContainer;
		}

		$column = new Components\Column($label === NULL ? $name : $label);
		$this['columns']->addComponent($column, $name);

		return $column;
	}


	/** @return \ArrayIterator|Column[]|NULL */
	public function getColumns(): ?\ArrayIterator
	{
		return isset($this['columns']) ? $this['columns']->getComponents() : NULL;
	}


	public function hasManySortableColumns(): bool
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

	public function addRowAction(string $name, string $label, callable $callback): RowAction
	{
		if (!isset($this['rowActions'])) {
			$this['rowActions'] = new NContainer;
		}

		$action = new Components\RowAction($label, $callback);
		$this['rowActions']->addComponent($action, $name);

		return $action;
	}


	/** @return \ArrayIterator|RowAction[]|NULL */
	public function getRowActions(): ?\ArrayIterator
	{
		return isset($this['rowActions']) ? $this['rowActions']->getComponents() : NULL;
	}


	public function handleRowAction(string $name, string $primary, string $token = NULL): void
	{
		$action = $this['rowActions']->getComponent($name);

		if (!$action->isProtected() || Helpers::checkCsrfToken($this->session, $token)) {
			$action->invoke($this->getRecordHandler()->findIn($primary, $this->getData()));
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
	public function addGroupAction(string $name, string $label, callable $callback): Action
	{
		if (!isset($this['groupActions'])) {
			$this['groupActions'] = new NContainer;
		}

		$action = new Components\Action($label, $callback);
		$this['groupActions']->addComponent($action, $name);

		return $action;
	}


	/** @return \ArrayIterator|Action[]|NULL */
	public function getGroupActions(): ?\ArrayIterator
	{
		return isset($this['groupActions']) ? $this['groupActions']->getComponents() : NULL;
	}


	// === SORTING ======================================================

	public function handleSort(array $orderBy): void
	{
		$this->refreshState();
		$this->redraw(TRUE, TRUE, ['header-sort', 'body', 'footer']);
	}


	/**
	 * @param  string|array $column
	 * @param  bool $dir
	 * @return DataGrid
	 */
	public function setDefaultOrderBy($column, bool $dir = Components\Column::ASC): self
	{
		if (is_array($column)) {
			$this->defaultOrderBy = $column;

		} else {
			$this->defaultOrderBy = [
				(string) $column => $dir,
			];
		}

		return $this;
	}


	public function setMultiSort(bool $bool = TRUE): self
	{
		$this->multiSort = $bool;
		return $this;
	}


	public function hasMultiSort(): bool
	{
		return $this->multiSort;
	}


	// === FILTERING ======================================================

	public function setFilterFactory(callable $factory): self
	{
		$this->filterFactory = $factory;
		return $this;
	}


	public function setDefaultFilters(array $filters): self
	{
		if ($this->filterFactory === NULL) {
			throw new \RuntimeException('Filter factory must be set using DataGrid::setFilterFactory($callback).');
		}

		$this->defaultFilters = $filters;
		return $this;
	}


	protected function setFilters(array $filters): self
	{
		Helpers::recursiveKSort($filters);
		$this->filters = $filters;

		$this->redraw(TRUE, TRUE, ['header-sort', 'filter-controls', 'body', 'footer']);
		$this->setPage(1);

		return $this;
	}


	// === REFRESH ======================================================

	public function handleRefresh(): void
	{
		$this->refreshing = TRUE;
		$this->redraw(TRUE, TRUE);
		$this->refreshState(FALSE);
	}


	// === DATA LOADING ======================================================

	private function getRecordHandler(): RecordHandler
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
	public function setPrimaryKey($primaryKey): self
	{
		$this->getRecordHandler()->setPrimaryKey(is_array($primaryKey) ? $primaryKey : func_get_args());
		return $this;
	}


	public function setDataLoader(callable $loader): self
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


	public function hasData(): bool
	{
		return (bool) count($this->getData());
	}


	public function setValueGetter(callable $callback = NULL): self
	{
		$this->getRecordHandler()->setValueGetter($callback);
		return $this;
	}


	protected function redraw(bool $reloadData = TRUE, bool $reloadForm = FALSE, array $snippets = [NULL]): void
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

	public function setInlineEditing(callable $containerSetupCb, callable $processCb): self
	{
		$this->ieProcessCallback = $processCb;
		$this->ieContainerSetupCallback = $containerSetupCb;
		return $this;
	}


	protected function activateInlineEditing(string $primary): void
	{
		$this->iePrimary = $primary;
		$this->refreshState(FALSE);
		$this->redraw(FALSE, TRUE, ['body']);
	}


	protected function deactivateInlineEditing(bool $dataAsWell = TRUE): void
	{
		$this->refreshState();
		$this->redraw($dataAsWell, TRUE, ['body']);
	}


	// === PAGINATION ======================================================

	public function setPagination(int $itemsPerPage, callable $itemCounter = NULL): self
	{
		$this->itemsPerPage = max(0, $itemsPerPage);
		$this->itemCounter = $itemCounter;
		return $this;
	}


	public function handlePaginate(int $p): void
	{
		$this->paginate($p);
	}


	protected function paginate(int $page): void
	{
		$this->setPage($page);
		$this->refreshState();
		$this->redraw(TRUE, FALSE, ['body', 'footer']);
	}


	protected function setPage(int $page): self
	{
		if ($this->itemsPerPage !== NULL) {
			$this->page = $page;
			$this->itemCount = NULL;

		} else {
			$this->page = 1;
		}

		return $this;
	}


	protected function initPagination(): self
	{
		if ($this->pageCount === NULL) {
			$this->pageCount = (int) ceil($this->getItemCount() / $this->itemsPerPage);
			$this->page = Helpers::fixPage($this->page, $this->pageCount);
		}

		return $this;
	}


	public function getItemsPerPage(): ?int
	{
		return $this->itemsPerPage;
	}


	public function getItemCount(): ?int
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
		}

		return $this->itemCount;
	}


	public function getPageCount(): ?int
	{
		return $this->pageCount;
	}


	// === FORM BUILDING ======================================================

	protected function createComponentForm(): Form
	{
		$form = new Form($this->getRecordHandler());
		$form->addProtection();
		$form->setTranslator($this->getTranslator());
		$form->onSuccess[] = [$this, 'processForm'];
		$form->onSubmit[] = [$this, 'formSubmitted'];

		return $form;
	}


	public function addFilterCriteria(): self
	{
		if ($this->filterFactory !== NULL) {
			$this->addFilterButtons();
			$this['form']->addFilterCriteria($this->filterFactory, $this->filters);
		}

		return $this;
	}


	public function addFilterButtons(): self
	{
		if ($this->filterFactory !== NULL) {
			$this['form']->addFilterButtons((bool) count($this->filters));
		}

		return $this;
	}


	public function addGroupActionCheckboxes(): self
	{
		if ($this->getGroupActions() !== NULL) {
			$this->addGroupActionButtons();
			$this['form']->addGroupActionCheckboxes();
		}

		return $this;
	}


	public function addGroupActionButtons(): self
	{
		if ($this->getGroupActions() !== NULL) {
			$this['form']->addGroupActionButtons($this->getGroupActions());
		}

		return $this;
	}


	public function addInlineEditControls(): self
	{
		if ($this->ieContainerSetupCallback !== NULL) {
			$this['form']->addInlineEditControls(
				$this->getData(),
				$this->ieContainerSetupCallback,
				$this->iePrimary
			);
		}

		return $this;
	}


	public function addPaginationControls(): self
	{
		if ($this->itemsPerPage !== NULL) {
			$this->initPagination();
			$this['form']->addPaginationControls($this->page, $this->pageCount);
		}

		return $this;
	}


	public function formSubmitted(Form $form): void
	{
		$this->redraw(FALSE, FALSE, ['form-errors']);
	}


	public function processForm(Form $form): void
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
						$record = $this->getRecordHandler()->findIn((string) $primaryString, $this->getData());

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

	public function setTemplateFile(string $templateFile): self
	{
		$this->templateFile = $templateFile;
		return $this;
	}


	public function setRecordVariable(string $name): self
	{
		$this->recordVariable = $name;
		return $this;
	}


	public function render(): void
	{
		/** @var Template $template */
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

		$template->hasInlineEdit = $this->ieContainerSetupCallback !== NULL;
		$template->iePrimary = $this->iePrimary;

		$template->isPaginated = $this->itemsPerPage !== NULL;

		$template->columnCount = count($template->columns)
				+ ($template->hasGroupActions ? 1 : 0)
				+ ($template->hasFilters || $template->hasRowActions || $template->hasInlineEdit ? 1 : 0);

		$template->render();
	}

}
