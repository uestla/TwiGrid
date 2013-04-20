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
use Nette\Http\Session as NSession;
use Nette\Utils\Strings as NStrings;


abstract class Helpers extends Nette\Object
{

	/**
	 * @param  array
	 * @param  int
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
	 * @param  array
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



	// === PAGINATION ======================================================

	/**
	 * @param  int
	 * @param  int
	 * @return int
	 */
	static function fixPage($page, $pageCount)
	{
		return max(1, min((int) $page, $pageCount));
	}



	// === CSRF PROTECTION ======================================================

	/**
	 * @param  NSession
	 * @param  string
	 * @param  bool
	 * @return string|NULL
	 */
	static function getCsrfToken(NSession $session, $namespace, $generate = TRUE)
	{
		$session = static::getCsrfTokenSession($session, $namespace);
		return isset($session->token) ? $session->token
			: ($generate ? ($session->token = NStrings::random()) : NULL);
	}



	/**
	 * @param  NSession
	 * @param  string
	 * @param  string
	 * @return bool
	 */
	static function checkCsrfToken(NSession $session, $namespace, $token)
	{
		$sToken = static::getCsrfToken($session, $namespace, FALSE);
		if ($sToken !== NULL && $sToken === $token) {
			unset(static::getCsrfTokenSession($session, $namespace)->token);
			return TRUE;
		}

		return FALSE;
	}



	/**
	 * @param  NSession
	 * @param  string
	 * @return Nette\Http\SessionSection
	 */
	protected static function getCsrfTokenSession(NSession $session, $namespace)
	{
		return $session->getSection($namespace);
	}

}
