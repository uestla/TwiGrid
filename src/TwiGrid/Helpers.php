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

use TwiGrid\Components\Column;
use Nette\Utils\Random as NRandom;
use Nette\Http\SessionSection as NSessionSection;


abstract class Helpers
{

	/** @param  mixed[] $array */
	public static function recursiveKSort(array & $array): void
	{
		if (count($array)) {
			ksort($array);
		}

		foreach ($array as & $val) {
			if (is_array($val)) {
				static::recursiveKSort($val);
			}
		}
	}


	/**
	 * @param  mixed[] $a
	 * @return mixed[]
	 */
	public static function filterEmpty(array $a): array
	{
		$ret = [];
		foreach ($a as $k => $v) {
			if (is_array($v)) { // recursive
				if (count($tmp = static::filterEmpty($v))) {
					$ret[$k] = $tmp;
				}

			} elseif (is_object($v)) {
				$ret[$k] = $v;

			} elseif ((string) $v !== '') {
				$ret[$k] = $v;
			}
		}

		return $ret;
	}


	// === SORTING ======================================================

	public const SORT_LINK_SINGLE = 0;
	public const SORT_LINK_MULTI = 1;


	public static function createSortLink(DataGrid $grid, Column $column, int $mode = self::SORT_LINK_SINGLE): string
	{
		$by = null;

		if ($mode === self::SORT_LINK_SINGLE) {
			$by = [];
			if (!$column->isSortedBy() || count($grid->orderBy) > 1) {
				$by[$column->getName()] = Column::ASC;

			} elseif ($column->isSortedBy() && $column->getSortDir() === Column::ASC) {
				$by[$column->getName()] = Column::DESC;
			}

		} elseif ($mode === self::SORT_LINK_MULTI) {
			$by = $grid->orderBy;
			if (!$column->isSortedBy()) {
				$by[$column->getName()] = Column::ASC;

			} elseif ($column->getSortDir() === Column::ASC) {
				$by[$column->getName()] = Column::DESC;

			} else {
				unset($by[$column->getName()]);
			}
		}

		return $grid->link('sort!', [
			'orderBy' => $by,
		]);
	}


	// === PAGINATION ======================================================

	public static function fixPage(int $page, int $pageCount): int
	{
		return max(1, min($page, $pageCount));
	}


	// === CSRF PROTECTION ======================================================

	public static function getCsrfToken(NSessionSection $session): string
	{
		if (!isset($session->token)) {
			$session->token = NRandom::generate(10);
		}

		return $session->token;
	}


	public static function checkCsrfToken(NSessionSection $session, string $token): bool
	{
		if (isset($session->token) && strcmp($session->token, $token) === 0) {
			unset($session->token);
			return true;
		}

		return false;
	}

}
