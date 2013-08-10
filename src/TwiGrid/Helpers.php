<?php

/**
 * This file is part of the TwiGrid component
 *
 * Copyright (c) 2013 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
 * @link     https://github.com/uestla/twigrid
 */

namespace TwiGrid;

use Nette;
use TwiGrid\Components\Column;
use Nette\Http\Session as NSession;
use Nette\Utils\Strings as NStrings;


abstract class Helpers extends Nette\Object
{

	/**
	 * @param  array $array
	 * @param  int $flags
	 * @return void
	 */
	static function recursiveKSort(array & $array, $flags = SORT_REGULAR)
	{
		count($array) && ksort($array, $flags);
		foreach ($array as & $val) {
			is_array($val) && static::recursiveKSort($val, $flags);
		}
	}



	/**
	 * @param  array $a
	 * @return array
	 */
	static function filterEmpty(array $a)
	{
		$ret = array();
		foreach ($a as $k => $v) {
			if (is_array($v)) { // recursive
				if (count($tmp = static::filterEmpty($v))) {
					$ret[$k] = $tmp;
				}

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
	static function createSortLink(DataGrid $grid, Column $column, $mode = self::SORT_LINK_SINGLE)
	{
		if ($mode === self::SORT_LINK_SINGLE) {
			$by = array();
			if (!$column->sortedBy || count($grid->orderBy) > 1) {
				$by[$column->name] = Column::ASC;

			} elseif ($column->sortedBy && $column->sortDir === Column::ASC) {
				$by[$column->name] = Column::DESC;
			}

		} elseif ($mode === self::SORT_LINK_MULTI) {
			$by = $grid->orderBy;
			if (!$column->sortedBy) {
				$by[$column->name] = Column::ASC;

			} elseif ($column->sortDir === Column::ASC) {
				$by[$column->name] = Column::DESC;

			} else {
				unset($by[$column->name]);
			}
		}

		return $grid->link('sort!', array(
			'orderBy' => $by,
		));
	}



	// === PAGINATION ======================================================

	/**
	 * @param  int $page
	 * @param  int $pageCount
	 * @return int
	 */
	static function fixPage($page, $pageCount)
	{
		return max(1, min((int) $page, $pageCount));
	}



	// === CSRF PROTECTION ======================================================

	/**
	 * @param  NSession $session
	 * @param  string $namespace
	 * @param  bool $generate
	 * @return string|NULL
	 */
	static function getCsrfToken(NSession $session, $namespace, $generate = TRUE)
	{
		$session = static::getCsrfTokenSession($session, $namespace);
		return isset($session->token) ? $session->token
			: ($generate ? ($session->token = NStrings::random()) : NULL);
	}



	/**
	 * @param  NSession $session
	 * @param  string $namespace
	 * @param  string $token
	 * @return bool
	 */
	static function checkCsrfToken(NSession $session, $namespace, $token)
	{
		$sToken = static::getCsrfToken($session, $namespace, FALSE);
		if ($sToken !== NULL && $sToken === $token) {
			unset(self::getCsrfTokenSession($session, $namespace)->token);
			return TRUE;
		}

		return FALSE;
	}



	/**
	 * @param  NSession $session
	 * @param  string $namespace
	 * @return Nette\Http\SessionSection
	 */
	private static function getCsrfTokenSession(NSession $session, $namespace)
	{
		return $session->getSection($namespace);
	}

}
