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

use Nette\Utils\Arrays;
use Nette\Application\UI\Link;
use Nette\Http\SessionSection;
use TwiGrid\Components\Action;
use TwiGrid\Components\Column;
use Nette\Application\UI\Control;
use TwiGrid\Components\RowAction;
use TwiGrid\Components\Translator;
use Nette\Application\UI\Presenter;
use Nette\ComponentModel\Container;
use Nette\Database\Table\Selection;
use Nette\Localization\ITranslator;
use Nette\Forms\Controls\SubmitButton;


/** @template T */
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

	/** @var callable(\Nette\Forms\Container): void|null */
	private $filterFactory;


	// === INLINE EDITING ===========

	/**
	 * @var string|null
	 * @persistent
	 */
	public $iePrimary;

	/** @var callable(\Nette\Forms\Container, T): void|null */
	private $ieContainerSetupCallback;

	/** @var callable(T, array<string, mixed>): void|null */
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

	/** @var RecordHandler<T>|null */
	private $recordHandler;

	/** @var callable(array<string, mixed>, array<string, bool>, int|null, int): iterable<T>|null */
	private $dataLoader;

	/** @var iterable<T>|null */
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

			assert($presenter->payload->twiGrid instanceof \stdClass);
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
				$this->getColumn($column)->setSortedBy(true, $dir, $i++);

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
		foreach ($params['orderBy'] ?? [] as $column => $dir) {
			$params['orderBy'][$column] = (bool) $dir;
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


	/** @return self<T> */
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
			$this->translator = new Translator;
		}

		return $this->translator;
	}


	/** @return self<T> */
	public function setTranslator(ITranslator $translator): self
	{
		$this->translator = $translator;
		return $this;
	}


	public function translate(string $s, ?int $count = null): string
	{
		return (string) $this->getTranslator()->translate($s, $count);
	}


	// === COLUMNS ======================================================

	/** @return Column<T> */
	public function addColumn(string $name, ?string $label = null): Column
	{
		/** @var Column<T> $column */
		$column = new Column($label === null ? $name : $label);
		$this->getColumnsContainer()->addComponent($column, $name);

		return $column;
	}


	/** @return iterable<string, Column<T>>|null */
	public function getColumns(): ?iterable
	{
		if (isset($this['columns'])) {
			/** @var iterable<string, Column<T>> $components */
			$components = $this->getColumnsContainer()->getComponents();

			return $components;
		}

		return null;
	}


	/** @return Column<T> */
	private function getColumn(string $name): Column
	{
		$column = $this->getColumnsContainer()->getComponent($name);
		assert($column instanceof Column);
		return $column;
	}


	private function getColumnsContainer(): Container
	{
		if (!isset($this['columns'])) {
			$this['columns'] = new Container;
		}

		$columns = $this['columns'];
		assert($columns instanceof Container);

		return $columns;
	}


	public function hasManySortableColumns(): bool
	{
		$columns = $this->getColumns();

		if ($columns === null) {
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

	/**
	 * @param  callable(T): void $callback
	 * @return RowAction<T, T>
	 */
	public function addRowAction(string $name, string $label, callable $callback): RowAction
	{
		if (!isset($this['rowActions'])) {
			$this['rowActions'] = new Container;
		}

		/** @var RowAction<T, T> $action */
		$action = new RowAction($label, $callback);
		$this->getRowActionsContainer()->addComponent($action, $name);

		return $action;
	}


	/** @return iterable<string, RowAction<T, T>>|null */
	public function getRowActions(): ?iterable
	{
		if (isset($this['rowActions'])) {
			/** @var iterable<string, RowAction<T, T>> $components */
			$components = $this->getRowActionsContainer()->getComponents();

			return $components;
		}

		return null;
	}


	private function getRowActionsContainer(): Container
	{
		$rowActions = $this['rowActions'];
		assert($rowActions instanceof Container);
		return $rowActions;
	}


	public function handleRowAction(string $name, string $primary, ?string $token = null): void
	{
		/** @var RowAction<T, T> $action */
		$action = $this->getRowActionsContainer()->getComponent($name);

		if (!$action->isProtected() || ($token !== null && Helpers::checkCsrfToken($this->session, $token))) {
			$record = $this->getRecordHandler()->findIn($primary, $this->getData());

			if ($record !== null) {
				$action->invoke($record);
			}

			$this->refreshState();
			$this->redraw(true, true, ['body', 'footer']);

		} else {
			$this->flashMessage('Security token does not match. Please try again.', 'error');
			$this->redirect('this');
		}
	}


	// === GROUP ACTIONS ======================================================

	/**
	 * @param  callable(T[]): void $callback
	 * @return Action<T, T[]>
	 */
	public function addGroupAction(string $name, string $label, callable $callback): Action
	{
		if (!isset($this['groupActions'])) {
			$this['groupActions'] = new Container;
		}

		/** @var Action<T, T[]> $action */
		$action = new Action($label, $callback);
		$this->getGroupActionsContainer()->addComponent($action, $name);

		return $action;
	}


	/** @return iterable<string, Action<T, T[]>>|null */
	public function getGroupActions(): ?iterable
	{
		if (isset($this['groupActions'])) {
			/** @var iterable<string, Action<T, T[]>> $groupActions */
			$groupActions = $this->getGroupActionsContainer()->getComponents();

			return $groupActions;
		}

		return null;
	}


	/** @return Action<T, T[]> */
	private function getGroupAction(string $name): Action
	{
		$action = $this->getGroupActionsContainer()->getComponent($name);
		assert($action instanceof Action);
		return $action;
	}


	private function getGroupActionsContainer(): Container
	{
		$groupActions = $this['groupActions'];
		assert($groupActions instanceof Container);
		return $groupActions;
	}


	// === SORTING ======================================================

	/** @param  array<string, bool> $orderBy */
	public function handleSort(array $orderBy): void
	{
		$this->refreshState();
		$this->redraw(true, true, ['header-sort', 'body', 'footer']);
	}


	/**
	 * @param  string|array<string, bool> $column
	 * @return self<T>
	 */
	public function setDefaultOrderBy($column, bool $dir = Column::ASC): self
	{
		if (is_array($column)) {
			$this->defaultOrderBy = $column;

		} else {
			$this->defaultOrderBy = [
				$column => $dir,
			];
		}

		return $this;
	}


	/** @return self<T> */
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

	/**
	 * @param  callable(\Nette\Forms\Container): void $factory
	 * @return self<T>
	 */
	public function setFilterFactory(callable $factory): self
	{
		$this->filterFactory = $factory;
		return $this;
	}


	/**
	 * @param  array<string, mixed> $filters
	 * @return self<T>
	 */
	public function setDefaultFilters(array $filters): self
	{
		if ($this->filterFactory === null) {
			throw new \RuntimeException('Filter factory must be set using DataGrid::setFilterFactory($callback).');
		}

		$this->defaultFilters = $filters;
		return $this;
	}


	/**
	 * @param  array<string, mixed> $filters
	 * @return self<T>
	 */
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

	/** @return RecordHandler<T> */
	private function getRecordHandler(): RecordHandler
	{
		if ($this->recordHandler === null) {
			/** @var RecordHandler<T> $recordHandler */
			$recordHandler = new RecordHandler;

			$this->recordHandler = $recordHandler;
		}

		return $this->recordHandler;
	}


	/**
	 * @param  string|string[] $primaryKey
	 * @return self<T>
	 */
	public function setPrimaryKey($primaryKey): self
	{
		if (is_array($primaryKey)) {
			$keys = $primaryKey;

		} else {
			/** @var mixed[] $keys */
			$keys = func_get_args();

			assert(Arrays::every($keys, function ($s): bool {
				return is_string($s);

			}), 'Primary keys must be an array of strings.');

			/** @var string[] $keys */
		}

		$this->getRecordHandler()->setPrimaryKey($keys);
		return $this;
	}


	/**
	 * @param  callable(array<string, mixed>, array<string, bool>, int|null, int): iterable<T> $loader
	 * @return self<T>
	 */
	public function setDataLoader(callable $loader): self
	{
		$this->dataLoader = $loader;
		return $this;
	}


	/** @return iterable<T> */
	public function getData()
	{
		if ($this->dataLoader === null) {
			throw new \LogicException('Data loader not set.');
		}

		if ($this->data === null) {
			$order = $this->orderBy;
			$primaryDir = $order === [] ? Column::ASC : end($order);
			$primaryKey = $this->getRecordHandler()->getPrimaryKey();

			if ($primaryKey !== null) {
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

			/** @var iterable<T> $data */
			$data = call_user_func_array($this->dataLoader, $args);

			$this->data = $data;
		}

		return $this->data;
	}


	public function hasData(): bool
	{
		$data = $this->getData();
		return count(is_array($data) || $data instanceof \Countable ? $data : iterator_to_array($data)) > 0;
	}


	/**
	 * @param  callable(T, string, bool): mixed|null $callback
	 * @return self<T>
	 */
	public function setValueGetter(?callable $callback = null): self
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

	/**
	 * @param  callable(\Nette\Forms\Container, T): void $containerSetupCb
	 * @param  callable(T, array<string, mixed>): void $processCb
	 * @return self<T>
	 */
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

	/**
	 * @param  callable(array<string, mixed>): int|null $itemCounter
	 * @return self<T>
	 */
	public function setPagination(int $itemsPerPage, ?callable $itemCounter = null): self
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


	/** @return self<T> */
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


	/** @return self<T> */
	protected function initPagination(): self
	{
		assert($this->itemsPerPage !== null);

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


	public function getItemCount(): int
	{
		if ($this->itemCount === null) {
			if ($this->itemCounter === null) { // fallback - fetch data with empty filters
				if ($this->dataLoader === null) {
					throw new \LogicException('Data loader not set.');
				}

				/** @var iterable<mixed> $data */
				$data = call_user_func($this->dataLoader, $this->filters, [], null, 0);

				if ($data instanceof Selection) {
					$count = $data->count('*');

				} else {
					$count = count((array) $data);
				}

			} else {
				$count = call_user_func($this->itemCounter, $this->filters);
			}

			$this->itemCount = max(0, $count);
		}

		return $this->itemCount;
	}


	public function getPageCount(): ?int
	{
		return $this->pageCount;
	}


	// === FORM BUILDING ======================================================

	/** @return Form<T> */
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


	/** @return self<T> */
	public function addFilterCriteria(): self
	{
		if ($this->filterFactory !== null) {
			$this->addFilterButtons();
			$this['form']->addFilterCriteria($this->filterFactory, $this->filters);
		}

		return $this;
	}


	/** @return self<T> */
	public function addFilterButtons(): self
	{
		if ($this->filterFactory !== null) {
			$this['form']->addFilterButtons((bool) count($this->filters));
		}

		return $this;
	}


	/** @return self<T> */
	public function addGroupActionCheckboxes(): self
	{
		if ($this->getGroupActions() !== null) {
			$this->addGroupActionButtons();
			$this['form']->addGroupActionCheckboxes();
		}

		return $this;
	}


	/** @return self<T> */
	public function addGroupActionButtons(): self
	{
		if ($this->getGroupActions() !== null) {
			$this['form']->addGroupActionButtons($this->getGroupActions());
		}

		return $this;
	}


	/** @return self<T> */
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


	/** @return self<T> */
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

		$button = $form->isSubmitted();
		if ($button === true) {
			$this->addGroupActionButtons();
			$button = $form->isSubmitted();

			if ($button === true) {
				$this->addPaginationControls();
				$button = $form->isSubmitted();

				if ($button === true) {
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

					$this->getGroupAction((string) $name)->invoke($records);
					$this->refreshState();
					$this->redraw(true, true, ['body', 'footer']);
				}

			} elseif ($path === 'inline-buttons') {
				if ($name === 'edit') {
					$values = $form->getInlineValues();

					if ($values !== null) {
						if ($this->iePrimary !== null) {
							if ($this->ieProcessCallback === null) {
								throw new \LogicException('Inline edit callback not set.');
							}

							/** @var T|null $record */
							$record = $this->getRecordHandler()->findIn($this->iePrimary, $this->getData());
							assert($record !== null);

							call_user_func($this->ieProcessCallback, $record, $values);
						}

						$this->deactivateInlineEditing();
					}

				} elseif ($name === 'cancel') {
					$this->deactivateInlineEditing(false);

				} else {
					$primary = $button->getName();

					if ($primary !== null) {
						$this->activateInlineEditing($primary);
					}
				}
			}
		}
	}


	// === RENDERING ======================================================

	/** @return self<T> */
	public function setTemplateFile(string $templateFile): self
	{
		$this->templateFile = $templateFile;
		return $this;
	}


	/** @return self<T> */
	public function setRecordVariable(string $name): self
	{
		$this->recordVariable = $name;
		return $this;
	}


	public function render(): void
	{
		/** @var Template<T> $template */
		$template = $this->createTemplate(Template::class);

		$template->grid = $this;
		$template->setTranslator($this->translator);
		$template->defaultTemplate = __DIR__ . '/DataGrid.latte';
		$template->setFile($this->templateFile === null ? $template->defaultTemplate : $this->templateFile);
		$template->csrfToken = Helpers::getCsrfToken($this->session);

		$latte = $template->getLatte();
		$latte->addFilter('translate', [$this, 'translate']);
		$latte->addFilter('primaryToString', [$this->getRecordHandler(), 'getPrimaryHash']);
		$latte->addFilter('getValue', [$this->getRecordHandler(), 'getValue']);
		$latte->addFilter('sortLink', function (Column $c, $m = Helpers::SORT_LINK_SINGLE) {
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

			if (!is_array($this->payload->forms ?? null)) {
				$this->payload->forms = [];
			}

			$formID = $form->getElementPrototype()->id;
			assert(is_string($formID));

			$this->payload->forms[$formID] = (string) $action;

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

		$template->columnCount = ($columns === null ? 0 : count((array) $columns))
				+ ($template->hasGroupActions ? 1 : 0)
				+ ($template->hasFilters || $template->hasRowActions || $template->hasInlineEdit ? 1 : 0);

		$template->render();
	}

}
