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

use Nette\Forms\Container;
use TwiGrid\Components\Action;
use Nette\Forms\Controls\Button;
use Nette\Forms\Controls\Checkbox;
use Nette\Forms\Controls\TextInput;
use Nette\Forms\Controls\SubmitButton;


/** @template T */
class Form extends \Nette\Application\UI\Form
{

	/** @var RecordHandler<T> */
	private $recordHandler;


	/** @param  RecordHandler<T> $handler */
	public function __construct(RecordHandler $handler)
	{
		parent::__construct();
		$this->recordHandler = $handler;
	}


	/**
	 * @param  callable(Container): void $containerSetupCb
	 * @param  array<string, mixed> $defaults
	 * @return self<T>
	 */
	public function addFilterCriteria(callable $containerSetupCb, array $defaults): self
	{
		if ($this->lazyCreateContainer('filters', 'criteria', $criteria)) {
			assert($criteria instanceof Container);
			$containerSetupCb($criteria);
			$criteria->setDefaults($defaults);
		}

		$this->getSubmitButton(['filters', 'buttons', 'filter'])->setValidationScope(
			[$this->getContainer(['filters', 'criteria'])]
		);

		return $this;
	}


	/** @return self<T> */
	public function addFilterButtons(bool $hasFilters): self
	{
		if ($this->lazyCreateContainer('filters', 'buttons', $buttons)) {
			assert($buttons instanceof Container);
			$buttons->addSubmit('filter', 'twigrid.filters.filter');

			if ($hasFilters) {
				$buttons->addSubmit('reset', 'twigrid.filters.cancel')->setValidationScope([]);
			}
		}

		return $this;
	}


	/** @return array<string, mixed>|null */
	public function getFilterCriteria(): ?array
	{
		try {
			/** @var array{filters: array{criteria: array<string, array<string, mixed>|object|scalar>}} $values */
			$values = $this->getValues('array');
			return Helpers::filterEmpty($values['filters']['criteria']);

		} catch (\Throwable $e) {} // form may not be valid

		return null;
	}


	/** @return self<T> */
	public function addGroupActionCheckboxes(): self
	{
		if ($this->lazyCreateContainer('actions', 'records', $records)) {
			$first = true;

			/** @var DataGrid<T> $grid */
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

		/** @var SubmitButton $button */
		foreach ($this->getContainer(['actions', 'buttons'])->getComponents() as $button) {
			$button->setValidationScope([$this->getContainer(['actions', 'records'])]);
		}

		return $this;
	}


	/**
	 * @param  iterable<string, Action<T, T[]>> $actions
	 * @return self<T>
	 */
	public function addGroupActionButtons(iterable $actions): self
	{
		if ($this->lazyCreateContainer('actions', 'buttons', $buttons)) {
			assert($buttons instanceof Container);

			foreach ($actions as $name => $action) {
				$buttons->addSubmit($name, $action->getLabel());
			}
		}

		return $this;
	}


	/** @return string[]|null */
	public function getCheckedRecords(): ?array
	{
		$this->addGroupActionCheckboxes();

		try {
			/** @var array{actions: array{records: array<string, string>}} $values */
			$values = $this->getValues('array');
			return array_map('strval', array_keys(array_filter($values['actions']['records'])));

		} catch (\Throwable $e) {} // form may not be valid

		return null;
	}


	/**
	 * @param  iterable<T> $data
	 * @param  callable $containerSetupCb
	 * @return self<T>
	 */
	public function addInlineEditControls($data, callable $containerSetupCb, ?string $iePrimary): self
	{
		if ($this->lazyCreateContainer('inline', 'buttons', $buttons)) {
			assert($buttons instanceof Container);

			foreach ($data as $record) {
				if ($this->recordHandler->is($record, $iePrimary)) {
					/** @var Container $inline */
					$inline = $this['inline'];

					$containerSetupCb($inline->addContainer('values'), $record);

					$buttons->addSubmit('edit', 'twigrid.inline.edit_confirm')
							->setValidationScope([$this->getContainer(['inline', 'values'])]);

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


	/** @return array<string, mixed>|null */
	public function getInlineValues(): ?array
	{
		try {
			/** @var array{inline: array{values: array<string, string>}} $values */
			$values = $this->getValues('array');
			return $values['inline']['values'];

		} catch (\Throwable $e) {} // form may not be valid

		return null;
	}


	/** @return self<T> */
	public function addPaginationControls(int $current, int $pageCount): self
	{
		if ($this->lazyCreateContainer('pagination', 'controls', $controls)) {
			assert($controls instanceof Container);

			/** @var string $integerRule */
			$integerRule = $this::INTEGER;

			/** @var string $rangeRule */
			$rangeRule = $this::RANGE;

			$controls->addText('page', 'twigrid.pagination.page')
				->setRequired('twigrid.pagination.page_required')
				->addRule($integerRule, 'twigrid.pagination.page_integer')
				->addRule($rangeRule, 'twigrid.pagination.page_range', [1, $pageCount])
				->setDefaultValue($current);
		}

		if ($this->lazyCreateContainer('pagination', 'buttons', $buttons)) {
			assert($buttons instanceof Container);

			$buttons->addSubmit('change', 'twigrid.pagination.change')
				->setValidationScope([$this->getContainer(['pagination', 'controls'])]);
		}

		return $this;
	}


	public function getPage(): int
	{
		/** @var TextInput $pageControl */
		$pageControl = $this->getContainer(['pagination', 'controls'])->getComponent('page');

		/** @var scalar $value */
		$value = $pageControl->getValue();

		return (int) $value;
	}


	protected function lazyCreateContainer(string $parent, string $name, ?Container & $container = null): bool
	{
		if (!$this->hasContainer([$parent])) {
			$this->addContainer($parent);
		}

		if (!$this->hasContainer([$parent, $name])) {
			$parentContainer = $this->getContainer([$parent]);
			$parentContainer->addContainer($name);
			$created = true;
		}

		$container = $this->getContainer([$parent, $name]);
		return isset($created);
	}


	/** @param  string[] $path */
	protected function hasContainer(array $path): bool
	{
		$current = $this;
		foreach ($path as $name) {
			/** @var Container|null $current */
			$current = $current->getComponent($name, false);

			if ($current === null) {
				return false;
			}
		}

		return true;
	}


	/** @param  string[] $path */
	protected function getContainer(array $path): Container
	{
		$current = $this;
		foreach ($path as $name) {
			/** @var Container $current */
			$current = $current->getComponent($name);
		}

		return $current;
	}


	/** @param  string[] $path */
	protected function getSubmitButton(array $path): SubmitButton
	{
		/** @var string $buttonName */
		$buttonName = array_pop($path);

		/** @var SubmitButton $button */
		$button = $this->getContainer($path)->getComponent($buttonName);

		return $button;
	}


	public static function validateCheckedCount(Checkbox $checkbox): bool
	{
		/** @var self<T> $form */
		$form = $checkbox->getForm();

		/** @var Button $button */
		$button = $form->isSubmitted();

		/** @var Container $buttonParent */
		$buttonParent = $button->getParent();

		/** @var Container $checkboxParent */
		$checkboxParent = $checkbox->getParent();

		return $buttonParent->lookupPath(\Nette\Application\UI\Form::class) !== 'actions-buttons'
				|| in_array(true, (array) $checkboxParent->getValues('array'), true);
	}

}
