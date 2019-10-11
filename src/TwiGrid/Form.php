<?php

declare(strict_types = 1);

/**
 * This file is part of the TwiGrid component
 *
 * @license  MIT
 * @author   Petr Kessler (https://kesspess.cz)
 * @link     https://github.com/uestla/twigrid
 */

namespace TwiGrid;

use Nette\Forms\Container;
use Nette\Forms\Controls\Button;
use Nette\Forms\Controls\Checkbox;
use Nette\Forms\Controls\SubmitButton;
use Nette\Application\UI\Form as NForm;
use Nette\Forms\Container as NContainer;


class Form extends NForm
{

	/** @var RecordHandler */
	private $recordHandler;


	public function __construct(RecordHandler $handler)
	{
		parent::__construct();
		$this->recordHandler = $handler;
	}


	public function addFilterCriteria(callable $containerSetupCb, array $defaults): self
	{
		if ($this->lazyCreateContainer('filters', 'criteria', $criteria)) {
			$containerSetupCb($criteria);
			$criteria->setDefaults($defaults);
		}

		$this['filters']['buttons']['filter']->setValidationScope([$this['filters']['criteria']]);
		return $this;
	}


	public function addFilterButtons(bool $hasFilters): self
	{
		if ($this->lazyCreateContainer('filters', 'buttons', $buttons)) {
			$buttons->addSubmit('filter', 'twigrid.filters.filter');

			if ($hasFilters) {
				$buttons->addSubmit('reset', 'twigrid.filters.cancel')->setValidationScope([]);
			}
		}

		return $this;
	}


	public function getFilterCriteria(): ?array
	{
		$this->validate();
		if ($this->isValid()) {
			return Helpers::filterEmpty($this['filters']['criteria']->getValues(true));
		}

		return null;
	}


	public function addGroupActionCheckboxes(): self
	{
		if ($this->lazyCreateContainer('actions', 'records', $records)) {
			$first = true;

			/** @var DataGrid $grid */
			$grid = $this->getParent();

			foreach ($grid->getData() as $record) {
				$hash = $this->recordHandler->getPrimaryHash($record);
				$records[$hash] = $checkbox = new Checkbox;

				if ($first) {
					$checkbox->addRule(__CLASS__ . '::validateCheckedCount', 'twigrid.group_actions.checked_count_message');
					$first = false;
				}
			}
		}

		foreach ($this['actions']['buttons']->getComponents() as $button) {
			$button->setValidationScope([$this['actions']['records']]);
		}

		return $this;
	}


	public function addGroupActionButtons(\ArrayIterator $actions): self
	{
		if ($this->lazyCreateContainer('actions', 'buttons', $buttons)) {
			foreach ($actions as $name => $action) {
				$buttons->addSubmit($name, $action->getLabel());
			}
		}

		return $this;
	}


	public function getCheckedRecords(): ?array
	{
		$this->addGroupActionCheckboxes();

		$this->validate();
		if ($this->isValid()) {
			return array_map('strval', array_keys(array_filter($this['actions']['records']->getValues(true))));
		}

		return null;
	}


	/**
	 * @param  array|\Traversable $data
	 * @param  callable $containerSetupCb
	 * @param  string|null $iePrimary
	 * @return Form
	 */
	public function addInlineEditControls($data, callable $containerSetupCb, ?string $iePrimary): self
	{
		if ($this->lazyCreateContainer('inline', 'buttons', $buttons)) {
			foreach ($data as $record) {
				if ($this->recordHandler->is($record, $iePrimary)) {
					/** @var Container $inline */
					$inline = $this['inline'];

					$containerSetupCb($inline->addContainer('values'), $record);

					$buttons->addSubmit('edit', 'twigrid.inline.edit_confirm')
							->setValidationScope([$this['inline']['values']]);

					$buttons->addSubmit('cancel', 'twigrid.inline.cancel')->setValidationScope([]);

				} else {
					$submit = new SubmitButton('twigrid.inline.edit');
					$submit->setValidationScope([]);
					$buttons[$this->recordHandler->getPrimaryHash($record)] = $submit;
				}

			}
		}

		return $this;
	}


	public function getInlineValues(): ?array
	{
		$this->validate();
		return $this->isValid() ? $this['inline']['values']->getValues(true) : null;
	}


	public function addPaginationControls(int $current, int $pageCount): self
	{
		if ($this->lazyCreateContainer('pagination', 'controls', $controls)) {
			$controls->addText('page', 'twigrid.pagination.page')
				->setRequired('twigrid.pagination.page_required')
				->addRule(Form::INTEGER, 'twigrid.pagination.page_integer')
				->addRule(Form::RANGE, 'twigrid.pagination.page_range', [1, $pageCount])
				->setDefaultValue($current);
		}

		if ($this->lazyCreateContainer('pagination', 'buttons', $buttons)) {
			$buttons->addSubmit('change', 'twigrid.pagination.change')
				->setValidationScope([$this['pagination']['controls']]);
		}

		return $this;
	}


	public function getPage(): int
	{
		return (int) $this['pagination']['controls']['page']->getValue();
	}


	protected function lazyCreateContainer(string $parent, string $name, NContainer & $container = null): bool
	{
		if (!isset($this[$parent])) {
			$this->addContainer($parent);
		}

		if (!isset($this[$parent][$name])) {
			/** @var Container $parentContainer */
			$parentContainer = $this[$parent];

			$parentContainer->addContainer($name);
			$created = true;
		}

		$container = $this[$parent][$name];
		return isset($created);
	}


	public static function validateCheckedCount(Checkbox $checkbox): bool
	{
		/** @var Form $form */
		$form = $checkbox->getForm();

		/** @var Button $button */
		$button = $form->isSubmitted();

		/** @var NContainer $buttonParent */
		$buttonParent = $button->getParent();

		/** @var NContainer $checkboxParent */
		$checkboxParent = $checkbox->getParent();

		return $buttonParent->lookupPath(NForm::class) !== 'actions-buttons'
				|| in_array(true, (array) $checkboxParent->getValues('array'), true);
	}

}
