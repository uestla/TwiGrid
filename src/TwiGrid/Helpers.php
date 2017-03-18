<?php

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

	/**
	 * @param  array $array
	 * @return void
	 */
	public static function recursiveKSort(array & $array)
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
	 * @param  array $a
	 * @return array
	 */
	public static function filterEmpty(array $a)
	{
		$ret = [];
		foreach ($a as $k => $v) {
			if (is_array($v)) { // recursive
				if (count($tmp = static::filterEmpty($v))) {
					$ret[$k] = $tmp;
				}

			} elseif (is_object($v)) {
				$ret[$k] = $v;

			} elseif (strlen($v)) {
				$ret[$k] = $v;
			}
		}

		return $ret;
	}


	// === SORTING ======================================================

	const SORT_LINK_SINGLE = 0;
	const SORT_LINK_MULTI = 1;


	/**
	 * @param  DataGrid $grid
	 * @param  Column $column
	 * @param  int $mode
	 * @return string
	 */
	public static function createSortLink(DataGrid $grid, Column $column, $mode = self::SORT_LINK_SINGLE)
	{
		$by = NULL;

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

	/**
	 * @param  int $page
	 * @param  int $pageCount
	 * @return int
	 */
	public static function fixPage($page, $pageCount)
	{
		return max(1, min($page, $pageCount));
	}


	// === CSRF PROTECTION ======================================================

	/**
	 * @param  NSessionSection $session
	 * @return string
	 */
	public static function getCsrfToken(NSessionSection $session)
	{
		if (!isset($session->token)) {
			$session->token = NRandom::generate(10);
		}

		return $session->token;
	}


	/**
	 * @param  NSessionSection $session
	 * @param  string $token
	 * @return bool
	 */
	public static function checkCsrfToken(NSessionSection $session, $token)
	{
		if (isset($session->token) && strcmp($session->token, $token) === 0) {
			unset($session->token);
			return TRUE;
		}

		return FALSE;
	}

}
