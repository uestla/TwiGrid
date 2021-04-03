<?php

declare(strict_types = 1);

namespace Tests\Cases;

use Tester\Assert;
use Tester\TestCase;
use TwiGrid\DataGrid;
use Nette\Forms\Container;
use Tests\Utils\GridPresenter;

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
				if ($i & (1 << $j)) {
					$this->{'add' . ucFirst($feature)}($grid);
				}
			}

			$this->renderGrid($grid);
		}
	}


	private function createGrid(bool $hasData): DataGrid
	{
		$grid = new DataGrid;
		$grid->addColumn('fistname')->setSortable();
		$grid->addColumn('lastname')->setSortable();

		if ($hasData) {
			$grid->setDataLoader(static function () {
				return [
					(object) [
						'id' => 1,
						'firstname' => 'John',
						'lastname' => 'Doe',
					],
				];
			});

		} else {
			$grid->setDataLoader(static function () {
				return [];
			});
		}

		$grid->setPrimaryKey('id');
		return $grid;
	}


	public function renderGrid(DataGrid $grid): void
	{
		new GridPresenter($grid);

		ob_start(static function () {});
		$grid->render();
		$s = ob_get_clean();

		Assert::contains('"tw-cnt"', (string) $s);
	}


	// === FEATURES DEFINITIONS =================================

	private function addFiltering(DataGrid $grid): void
	{
		$grid->setFilterFactory(static function (Container $c) {
			$c->addText('firstname');
			$c->addText('lastname');
		});
	}


	private function addRowActions(DataGrid $grid): void
	{
		$grid->addRowAction('edit', 'Edit', static function (\stdClass $person) {});
		$grid->addRowAction('delete', 'Delete', static function (\stdClass $person) {})->setConfirmation('Are you sure?');
	}


	private function addGroupActions(DataGrid $grid): void
	{
		$grid->addGroupAction('export', 'Export', static function (array $persons) {});
		$grid->addGroupAction('delete', 'Delete', static function (array $persons) {})->setConfirmation('Are you sure?');
	}


	private function addInlineEditing(DataGrid $grid): void
	{
		$grid->setInlineEditing(static function (Container $c, \stdClass $person) {
			$c->addText('firstname')->setRequired();
			$c->addText('lastname')->setRequired();
			$c->setDefaults((array) $person);

		}, static function (\stdClass $person) {});
	}


	private function addPagination(DataGrid $grid): void
	{
		$grid->setPagination(20);
	}

}


(new FeaturesTest)->run();
