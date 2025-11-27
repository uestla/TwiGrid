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

use Nette\Utils\Random;
use Nette\Http\SessionSection;
use TwiGrid\Components\Column;


abstract class Helpers
{

	/** @param  array<string, mixed> $array */
	public static function recursiveKSort(array & $array): void
	{
		if ($array !== []) {
			ksort($array);
		}

		foreach ($array as & $val) {
			if (is_array($val)) {
				/** @var array<string, mixed> $val */
				static::recursiveKSort($val);
			}
		}
	}


	/**
	 * @param  array<string, array<string, mixed>|object|scalar> $a
	 * @return array<string, array<string, mixed>|object|scalar>
	 */
	public static function filterEmpty(array $a): array
	{
		$ret = [];
		foreach ($a as $k => $v) {
			if (is_array($v)) { // recursive
				/** @var array<string, array<string, mixed>|object|scalar> $v */
				if (($tmp = static::filterEmpty($v)) !== []) {
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


	/**
	 * @template T
	 * @param  DataGrid<T> $grid
	 * @param  Column<T> $column
	 * @param  mixed $mode
	 */
	public static function createSortLink(DataGrid $grid, Column $column, $mode = self::SORT_LINK_SINGLE): string
	{
		$by = null;

		if ($mode === self::SORT_LINK_SINGLE) {
			$by = [];
			if (!$column->isSortedBy() || count($grid->orderBy) > 1) {
				$by[$column->getName()] = Column::ASC;

			} elseif ($column->getSortDir() === Column::ASC) {
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

	public static function getCsrfToken(SessionSection $session): string
	{
		if (!isset($session->token)) {
			$session->token = Random::generate();
		}

		$token = $session->token;
		assert(is_string($token));

		return $token;
	}


	public static function checkCsrfToken(SessionSection $session, string $token): bool
	{
		if (isset($session->token)) {
			$sessionToken = $session->token;
			assert(is_string($sessionToken));

			if (strcmp($sessionToken, $token) === 0) {
				unset($session->token);
				return true;
			}
		}

		return false;
	}

}
