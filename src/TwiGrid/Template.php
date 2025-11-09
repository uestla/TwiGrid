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

use TwiGrid\Components\Column;
use TwiGrid\Components\Action;
use TwiGrid\Components\RowAction;

/** @template T */
class Template extends \Nette\Bridges\ApplicationLatte\Template
{
	/** @var DataGrid<T> */
	public $grid;

	/** @var string */
	public $defaultTemplate;

	/** @var string */
	public $csrfToken;

	/** @var Form<T> */
	public $form;

	/** @var iterable<string, Column<T>>|null */
	public $columns;

	/** @var callable(): iterable<T> */
	public $dataLoader;

	/** @var string */
	public $recordVariable;

	/** @var bool */
	public $hasFilters;

	/** @var iterable<string, RowAction<T>>|null */
	public $rowActions;

	/** @var bool */
	public $hasRowActions;

	/** @var iterable<string, Action<T>>|null */
	public $groupActions;

	/** @var bool */
	public $hasGroupActions;

	/** @var bool */
	public $hasInlineEdit;

	/** @var string|null */
	public $iePrimary;

	/** @var bool */
	public $isPaginated;

	/** @var int */
	public $columnCount;

	/** @var \stdClass[] */
	public $flashes;
}
