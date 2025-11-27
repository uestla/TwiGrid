<?php

declare(strict_types = 1);

namespace Tests\Cases;

use Tester\Assert;
use Tester\TestCase;
use TwiGrid\DataGrid;
use Nette\Forms\Container;
use Tests\UI\GridPresenter;

require_once __DIR__ . '/../bootstrap.php';


class FeaturesTest extends TestCase
{

	/**
	 * Creates DataGrids with all possible features and data-presence
	 * combinations, renders them and checks they're rendered properly.
	 *
	 * This should prevent mutual collisions amongst individual features.
	 */
	public function testAllFeatureCombinations(): void
	{
		$features = [
			'filtering',
			'rowActions',
			'groupActions',
			'inlineEditing',
			'pagination',
		];

		$combinations = 1 << (count($features) + 1);

		for ($i = 0; $i < $combinations; $i++) {
			// create grid and add features which have 1 on their index in binary $i
			$grid = $this->createGrid((bool) ($i & 1));

			foreach ($features as $j => $feature) {
				if (($i & (1 << ($j + 1))) !== 0) {
					$this->{'add' . ucfirst($feature)}($grid);
				}
			}

			$this->renderGrid($grid);
		}
	}


	/** @return DataGrid<array<string, mixed>> */
	private function createGrid(bool $hasData): DataGrid
	{
		/** @var DataGrid<array<string, mixed>> $grid */
		$grid = new DataGrid;

		$grid->addColumn('firstname')->setSortable();
		$grid->addColumn('lastname')->setSortable();

		if ($hasData) {
			$grid->setDataLoader(static function (): array {
				return [
					[
						'id' => 1,
						'firstname' => 'John',
						'lastname' => 'Doe',
					],
				];
			});

		} else {
			$grid->setDataLoader(static function (): array {
				return [];
			});
		}

		$grid->setPrimaryKey('id');
		return $grid;
	}


	/** @param  DataGrid<array<string, mixed>> $grid */
	public function renderGrid(DataGrid $grid): void
	{
		new GridPresenter($grid);

		ob_start(static function (): void {});
		$grid->render();
		$s = ob_get_clean();

		Assert::contains('"tw-cnt"', (string) $s);
	}


	// === FEATURES DEFINITIONS =================================

	/** @param  DataGrid<array<string, mixed>> $grid */
	private function addFiltering(DataGrid $grid): void
	{
		$grid->setFilterFactory(static function (Container $c): void {
			$c->addText('firstname');
			$c->addText('lastname');
		});
	}


	/** @param  DataGrid<array<string, mixed>> $grid */
	private function addRowActions(DataGrid $grid): void
	{
		$grid->addRowAction('show', 'Show', static function (array $person): void {})->setProtected(false);
		$grid->addRowAction('edit', 'Edit', static function (array $person): void {});
		$grid->addRowAction('delete', 'Delete', static function (array $person): void {})->setConfirmation('Are you sure?');
	}


	/** @param  DataGrid<array<string, mixed>> $grid */
	private function addGroupActions(DataGrid $grid): void
	{
		$grid->addGroupAction('export', 'Export', static function (array $persons): void {});
		$grid->addGroupAction('delete', 'Delete', static function (array $persons): void {})->setConfirmation('Are you sure?');
	}


	/** @param  DataGrid<array<string, mixed>> $grid */
	private function addInlineEditing(DataGrid $grid): void
	{
		$grid->setInlineEditing(static function (Container $c, array $person): void {
			$c->addText('firstname')->setRequired();
			$c->addText('lastname')->setRequired();
			$c->setDefaults($person);

		}, static function (array $person, array $values): void {});
	}


	/** @param  DataGrid<array<string, mixed>> $grid */
	private function addPagination(DataGrid $grid): void
	{
		$grid->setPagination(20);
	}

}


(new FeaturesTest)->run();
