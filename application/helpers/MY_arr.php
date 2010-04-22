<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Array helper class.
 * Extends built-in Array Helper
 *
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license 
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author     Ushahidi Team <team@ushahidi.com> 
 * @package    Ushahidi - http://source.ushahididev.com
 * @module     Array Helper
 * @copyright  Ushahidi - http://www.ushahidi.com
 * @license    http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL)
 */
class arr extends arr_Core {

	/**
	 * Remove Empty Keys from Array
	 *
	 * @param   string
	 * @return  array
	 */
	public static function remove_empty($arr){
		$narr = array();
		while(list($key, $val) = each($arr))
		{
			if (is_array($val))
			{
				$val = arr::remove_empty($val);
				// does the result array contain anything?
				if (count($val)!=0)
				{
					// yes :-)
					$narr[$key] = $val;
				}
			}
			else
			{
				if (trim($val) != "")
				{
					$narr[$key] = $val;
				}
			}
		}
		unset($arr);
		return $narr;
	}
}