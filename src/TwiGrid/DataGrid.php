<?php

declare(strict_types = 1);

/**
 * This file is part of the TwiGrid component
 *
 * @license  MIT
 * @author   Petr Kessler (https://kesspess.cz)
 * @link     https://github.com/uestla/TwiGrid
 */

namespace TwiGrid;

use Nette\Application\UI\Link;
use Nette\Http\SessionSection;
use TwiGrid\Components\Action;
use TwiGrid\Components\Column;
use Nette\Application\UI\Control;
use TwiGrid\Components\RowAction;
use Nette\Application\UI\Presenter;
use Nette\ComponentModel\Container;
use Nette\Database\Table\Selection;
use Nette\Localization\ITranslator;
use Nette\Forms\Controls\SubmitButton;
use Nette\Bridges\ApplicationLatte\Template;


class DataGrid extends Control
{

	/**
	 * @var bool
	 * @persistent
	 */
	public $polluted = false;


	// === SORTING ===========

	/**
	 * @var array<string, bool>
	 * @persistent
	 */
	public $orderBy = [];

	/** @var array<string, bool>|null */
	private $defaultOrderBy;

	/** @var bool */
	private $multiSort = true;


	// === FILTERING ===========

	/**
	 * @var array<string, mixed>
	 * @persistent
	 */
	public $filters = [];

	/** @var array<string, mixed>|null */
	private $defaultFilters;

	/** @var callable|null */
	private $filterFactory;


	// === INLINE EDITING ===========

	/**
	 * @var string|null
	 * @persistent
	 */
	public $iePrimary;

	/** @var callable|null */
	private $ieContainerSetupCallback;

	/** @var callable|null */
	private $ieProcessCallback;


	// === PAGINATION ===========

	/**
	 * @var int
	 * @persistent
	 */
	public $page = 1;

	/** @var int|null */
	private $itemsPerPage;

	/** @var callable(array<string, mixed> $filters): int|null */
	private $itemCounter;

	/** @var int|null */
	private $itemCount;

	/** @var int|null */
	private $pageCount;


	// === REFRESH ===========

	/** @var bool */
	private $refreshing = false;


	// === DATA ===========

	/** @var RecordHandler|null */
	private $recordHandler;

	/** @var callable|null */
	private $dataLoader;

	/** @var iterable<int|string, mixed>|null */
	private $data;


	// === SESSION ===========

	/** @var SessionSection */
	private $session;


	// === LOCALIZATION ===========

	/** @var ITranslator|null */
	private $translator;


	// === RENDERING ===========

	/** @var string|null */
	private $templateFile;

	/** @var string */
	private $recordVariable = 'record';


	// === AJAX PAYLOAD ===========

	/** @var \stdClass */
	private $payload;


	// === LIFE CYCLE ======================================================

	public function __construct()
	{
		$this->monitor(Presenter::class, function (Presenter $presenter) {
			$this->build();

			$this->session = $presenter->getSession(sprintf('%s-%s', __CLASS__, $this->getName()));

			if (!isset($presenter->payload->twiGrid)) {
				$presenter->payload->twiGrid = (object) [
					'forms' => [],
				];
			}

			$this->payload = $presenter->payload->twiGrid;
		});
	}


	protected function build(): void
	{}


	/** @param  array{polluted?: string, orderBy?: array<string, string>, filters?: array<string, mixed>, iePrimary?: string, page?: int} $params */
	public function loadState(array $params): void
	{
		parent::loadState(static::processParams($params));

		if (!$this->polluted && !$this->isInDefaultState()) {
			$this->polluted = true;
		}

		if (!$this->polluted) {
			if ($this->defaultOrderBy !== null) {
				$this->orderBy = array_merge($this->defaultOrderBy, $this->orderBy);
				$this->polluted = true;
			}

			if ($this->defaultFilters !== null) {
				$this->setFilters($this->defaultFilters);
				$this->polluted = true;
			}
		}

		$i = 0;
		foreach ($this->orderBy as $column => $dir) {
			try {
				$this['columns']->getComponent($column)->setSortedBy(true, $dir, $i++);

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
	 * @param  array{polluted?: string, orderBy?: array<string, string>, filters?: array<string, mixed>, iePrimary?: string, page?: int} $params
	 * @return array<string, mixed>
	 */
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
		if ($this->getColumns() === null) {
			throw new \RuntimeException('At least one column must be added using DataGrid::addColumn($name, $label).');
		}

		if ($this->dataLoader === null) {
			throw new \RuntimeException('Data loader callback must be set using DataGrid::setDataLoader($callback).');
		}

		if ($this->getRecordHandler()->getPrimaryKey() === null) {
			throw new \RuntimeException('Record primary key must be set using DataGrid::setPrimaryKey($key).');
		}

		if ($this->iePrimary !== null && $this->ieContainerSetupCallback === null) {
			$this->iePrimary = null;
		}
	}


	protected function isInDefaultState(): bool
	{
		foreach (static::getReflection()->getPersistentParams() as $name => $meta) {
			if ($this->$name !== $meta['def']) {
				return false;
			}
		}

		return true;
	}


	protected function refreshState(bool $resetInlineEdit = true): self
	{
		if ($resetInlineEdit) {
			$this->iePrimary = null;
		}

		if (!$this->presenter->isAjax()) {
			$this->redirect('this');
		}

		return $this;
	}


	// === LOCALIZATION ======================================================

	public function getTranslator(): ITranslator
	{
		if ($this->translator === null) {
			$this->translator = new Components\Translator;
		}

		return $this->translator;
	}


	public function setTranslator(ITranslator $translator): self
	{
		$this->translator = $translator;
		return $this;
	}


	public function translate(string $s, int $count = null): string
	{
		return $this->getTranslator()->translate($s, $count);
	}


	// === COLUMNS ======================================================

	public function addColumn(string $name, string $label = null): Column
	{
		if (!isset($this['columns'])) {
			$this['columns'] = new Container;
		}

		$column = new Components\Column($label === null ? $name : $label);
		$this['columns']->addComponent($column, $name);

		return $column;
	}


	/** @return \ArrayIterator<string, Column>|null */
	public function getColumns(): ?\ArrayIterator
	{
		return isset($this['columns']) ? $this['columns']->getComponents() : null;
	}


	public function hasManySortableColumns(): bool
	{
		$columns = $this->getColumns();

		if (!$columns) {
			return false;
		}

		$hasMany = false;

		foreach ($columns as $column) {
			if ($column->isSortable()) {
				if ($hasMany) { // 2nd sortable -> has many
					return true;

				} else {
					$hasMany = true;
				}
			}
		}

		return false;
	}


	// === ROW ACTIONS ======================================================

	public function addRowAction(string $name, string $label, callable $callback): RowAction
	{
		if (!isset($this['rowActions'])) {
			$this['rowActions'] = new Container;
		}

		$action = new Components\RowAction($label, $callback);
		$this['rowActions']->addComponent($action, $name);

		return $action;
	}


	/** @return \ArrayIterator<string, RowAction>|null */
	public function getRowActions(): ?\ArrayIterator
	{
		return isset($this['rowActions']) ? $this['rowActions']->getComponents() : null;
	}


	public function handleRowAction(string $name, string $primary, string $token = null): void
	{
		$action = $this['rowActions']->getComponent($name);

		if (!$action->isProtected() || ($token && Helpers::checkCsrfToken($this->session, $token))) {
			$action->invoke($this->getRecordHandler()->findIn($primary, $this->getData()));
			$this->refreshState();
			$this->redraw(true, true, ['body', 'footer']);

		} else {
			$this->flashMessage('Security token does not match. Please try again.', 'error');
			$this->redirect('this');
		}
	}


	// === GROUP ACTIONS ======================================================

	public function addGroupAction(string $name, string $label, callable $callback): Action
	{
		if (!isset($this['groupActions'])) {
			$this['groupActions'] = new Container;
		}

		$action = new Components\Action($label, $callback);
		$this['groupActions']->addComponent($action, $name);

		return $action;
	}


	/** @return \ArrayIterator<string, Action>|null */
	public function getGroupActions(): ?\ArrayIterator
	{
		return isset($this['groupActions']) ? $this['groupActions']->getComponents() : null;
	}


	// === SORTING ======================================================

	/** @param  array<string, bool> $orderBy */
	public function handleSort(array $orderBy): void
	{
		$this->refreshState();
		$this->redraw(true, true, ['header-sort', 'body', 'footer']);
	}


	/** @param  string|array<string, bool> $column */
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


	public function setMultiSort(bool $bool = true): self
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


	/** @param  array<string, mixed> $filters */
	public function setDefaultFilters(array $filters): self
	{
		if ($this->filterFactory === null) {
			throw new \RuntimeException('Filter factory must be set using DataGrid::setFilterFactory($callback).');
		}

		$this->defaultFilters = $filters;
		return $this;
	}


	/** @param  array<string, mixed> $filters */
	protected function setFilters(array $filters): self
	{
		Helpers::recursiveKSort($filters);
		$this->filters = $filters;

		$this->redraw(true, true, ['header-sort', 'filter-controls', 'body', 'footer']);
		$this->setPage(1);

		return $this;
	}


	// === REFRESH ======================================================

	public function handleRefresh(): void
	{
		$this->refreshing = true;
		$this->redraw(true, true);
		$this->refreshState(false);
	}


	// === DATA LOADING ======================================================

	private function getRecordHandler(): RecordHandler
	{
		if ($this->recordHandler === null) {
			$this->recordHandler = new RecordHandler;
		}

		return $this->recordHandler;
	}


	/** @param  string|string[] $primaryKey */
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


	/** @return iterable<int|string, mixed> */
	public function getData()
	{
		if (!$this->dataLoader) {
			throw new \LogicException('Data loader not set.');
		}

		if ($this->data === null) {
			$order = $this->orderBy;
			$primaryDir = count($order) ? end($order) : Components\Column::ASC;
			$primaryKey = $this->getRecordHandler()->getPrimaryKey();

			if ($primaryKey) {
				foreach ($primaryKey as $column) {
					if (!isset($order[$column])) {
						$order[$column] = $primaryDir;
					}
				}
			}

			$args = [
				$this->filters,
				$order,
			];

			if ($this->itemsPerPage !== null) { // validate page & append limit & offset
				$this->initPagination();
				$args[] = $this->itemsPerPage;
				$args[] = ($this->page - 1) * $this->itemsPerPage;
			}

			/** @var iterable<int|string, mixed> $data */
			$data = call_user_func_array($this->dataLoader, $args);

			assert(is_array($data) || $data instanceof \Countable);

			$this->data = $data;
		}

		return $this->data;
	}


	public function hasData(): bool
	{
		$data = $this->getData();
		return count(is_array($data) || $data instanceof \Countable ? $data : iterator_to_array($data)) > 0;
	}


	public function setValueGetter(callable $callback = null): self
	{
		$this->getRecordHandler()->setValueGetter($callback);
		return $this;
	}


	/** @param  array<int, string|null> $snippets */
	protected function redraw(bool $reloadData = true, bool $reloadForm = false, array $snippets = [null]): void
	{
		if ($reloadData) {
			$this->data = null;
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
		$this->refreshState(false);
		$this->redraw(false, true, ['body']);
	}


	protected function deactivateInlineEditing(bool $dataAsWell = true): void
	{
		$this->refreshState();
		$this->redraw($dataAsWell, true, ['body']);
	}


	// === PAGINATION ======================================================

	public function setPagination(int $itemsPerPage, callable $itemCounter = null): self
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
		$this->redraw(true, false, ['body', 'footer']);
	}


	protected function setPage(int $page): self
	{
		if ($this->itemsPerPage !== null) {
			$this->page = $page;
			$this->itemCount = null;

		} else {
			$this->page = 1;
		}

		return $this;
	}


	protected function initPagination(): self
	{
		if ($this->pageCount === null) {
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
		if ($this->itemCount === null) {
			if ($this->itemCounter === null) { // fallback - fetch data with empty filters
				if (!$this->dataLoader) {
					throw new \LogicException('Data loader not set.');
				}

				$data = call_user_func($this->dataLoader, $this->filters, [], null, 0);
				assert(is_array($data) || $data instanceof \Countable);

				if ($data instanceof Selection) {
					$count = $data->count('*');

				} else {
					$count = count($data);
				}

			} else {
				$count = call_user_func($this->itemCounter, $this->filters);
			}

			assert(is_int($count));
			$this->itemCount = max(0, $count);
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

		$form->onSuccess[] = function (\Nette\Forms\Form $form): void {
			$this->processForm($form);
		};

		$form->onSubmit[] = function (\Nette\Forms\Form $form): void {
			$this->formSubmitted($form);
		};

		return $form;
	}


	public function addFilterCriteria(): self
	{
		if ($this->filterFactory !== null) {
			$this->addFilterButtons();
			$this['form']->addFilterCriteria($this->filterFactory, $this->filters);
		}

		return $this;
	}


	public function addFilterButtons(): self
	{
		if ($this->filterFactory !== null) {
			$this['form']->addFilterButtons((bool) count($this->filters));
		}

		return $this;
	}


	public function addGroupActionCheckboxes(): self
	{
		if ($this->getGroupActions() !== null) {
			$this->addGroupActionButtons();
			$this['form']->addGroupActionCheckboxes();
		}

		return $this;
	}


	public function addGroupActionButtons(): self
	{
		if ($this->getGroupActions() !== null) {
			$this['form']->addGroupActionButtons($this->getGroupActions());
		}

		return $this;
	}


	public function addInlineEditControls(): self
	{
		if ($this->ieContainerSetupCallback !== null) {
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
		if ($this->itemsPerPage !== null) {
			$this->initPagination();
			$this['form']->addPaginationControls($this->page, (int) $this->pageCount);
		}

		return $this;
	}


	public function formSubmitted(\Nette\Forms\Form $form): void
	{
		$this->redraw(false, false, ['form-errors']);
	}


	public function processForm(\Nette\Forms\Form $form): void
	{
		if (!$form instanceof Form) {
			throw new \LogicException('Invalid form instance.');
		}

		// detect submit button by lazy buttons appending (beginning with the most lazy ones)
		$this->addFilterButtons();

		if (($button = $form->isSubmitted()) === true) {
			$this->addGroupActionButtons();

			if (($button = $form->isSubmitted()) === true) {
				$this->addPaginationControls();

				if (($button = $form->isSubmitted()) === true) {
					$this->addInlineEditControls();
					$button = $form->isSubmitted();
				}
			}
		}

		if ($button instanceof SubmitButton) {
			$name = $button->getName();

			/** @var Container $parent */
			$parent = $button->getParent();

			$path = $parent->lookupPath(Form::class);

			if ("$path-$name" === 'filters-buttons-filter') {
				$this->addFilterCriteria();
				$criteria = $form->getFilterCriteria();

				if ($criteria !== null) {
					$this->setFilters($criteria);
					$this->refreshState();
				}

			} elseif ("$path-$name" === 'filters-buttons-reset') {
				$this->setFilters([]);
				$this->refreshState();

				if ($this->defaultFilters !== null) {
					$this->polluted = true;
				}

			} elseif ("$path-$name" === 'pagination-buttons-change') {
				$this->paginate($form->getPage());

			} elseif ($path === 'actions-buttons') {
				$checked = $form->getCheckedRecords();

				if ($checked !== null) {
					$records = [];
					foreach ($checked as $primaryString) {
						$record = $this->getRecordHandler()->findIn($primaryString, $this->getData());

						if ($record !== null) {
							$records[] = $record;
						}
					}

					$this['groupActions']->getComponent($name)->invoke($records);
					$this->refreshState();
					$this->redraw(true, true, ['body', 'footer']);
				}

			} elseif ($path === 'inline-buttons') {
				if ($name === 'edit') {
					$values = $form->getInlineValues();

					if ($values !== null) {
						if ($this->iePrimary !== null) {
							if (!$this->ieProcessCallback) {
								throw new \LogicException('Inline edit callback not set.');
							}

							call_user_func($this->ieProcessCallback, $this->getRecordHandler()->findIn($this->iePrimary, $this->getData()), $values);
						}

						$this->deactivateInlineEditing();
					}

				} elseif ($name === 'cancel') {
					$this->deactivateInlineEditing(false);

				} else {
					$primary = $button->getName();

					if ($primary) {
						$this->activateInlineEditing($primary);
					}
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
		$template->setTranslator($this->translator);
		$template->defaultTemplate = __DIR__ . '/DataGrid.latte';
		$template->setFile($this->templateFile === null ? $template->defaultTemplate : $this->templateFile);
		$template->csrfToken = Helpers::getCsrfToken($this->session);

		$latte = $template->getLatte();
		$latte->addFilter('translate', [$this, 'translate']);
		$latte->addFilter('primaryToString', [$this->getRecordHandler(), 'getPrimaryHash']);
		$latte->addFilter('getValue', [$this->getRecordHandler(), 'getValue']);
		$latte->addFilter('sortLink', function (Components\Column $c, $m = Helpers::SORT_LINK_SINGLE) {
			return Helpers::createSortLink($this, $c, $m);
		});

		if ($this->isControlInvalid()) {
			$this->redraw(false, false, ['flashes']);
		}

		$template->form = $form = $this['form'];

		if ($this->presenter->isAjax()) {
			$this->payload->id = $this->getSnippetId('');
			$this->payload->url = $this->link('this');
			$this->payload->refreshing = $this->refreshing;
			$this->payload->refreshSignal = $this->link('refresh!');

			$action = $form->getAction();
			assert(is_string($action) || $action instanceof Link);

			$this->payload->forms[$form->getElementPrototype()->id] = (string) $action;

			$latte->addProvider('formsStack', [$form]);
		}

		$template->columns = $columns = $this->getColumns();
		$template->dataLoader = [$this, 'getData'];
		$template->recordVariable = $this->recordVariable;

		$template->hasFilters = $this->filterFactory !== null;

		$template->rowActions = $this->getRowActions();
		$template->hasRowActions = $template->rowActions !== null;

		$template->groupActions = $this->getGroupActions();
		$template->hasGroupActions = $template->groupActions !== null;

		$template->hasInlineEdit = $this->ieContainerSetupCallback !== null;
		$template->iePrimary = $this->iePrimary;

		$template->isPaginated = $this->itemsPerPage !== null;

		$template->columnCount = ($columns ? count($columns) : 0)
				+ ($template->hasGroupActions ? 1 : 0)
				+ ($template->hasFilters || $template->hasRowActions || $template->hasInlineEdit ? 1 : 0);

		$template->render();
	}

}
