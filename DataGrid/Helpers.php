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


final class Helpers extends Nette\Object
{
	/**
	 * @param  array
	 * @param  int
	 * @return void
	 */
	static function recursiveKSort(array & $array, $flags = SORT_REGULAR)
	{
		ksort($array, $flags);
		foreach ($array as & $val) {
			if (is_array($val) && count($val)) {
				ksort($val, $flags);
			}
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
				if (count( $tmp = static::filterEmpty($v) )) {
					$ret[$k] = $tmp;
				}

			} elseif (strlen($v)) {
				$ret[$k] = $v;
			}
		}

		return $ret;
	}
}
