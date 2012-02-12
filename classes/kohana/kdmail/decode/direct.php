<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Kdmail Decode Direct
 * parses a mail-text directly.
 *
 * @package    Kdmail
 * @author     ele_eel
 * @copyright  9km.jp
 * @license    http://www.opensource.org/licenses/mit-license.php
 */
class Kohana_Kdmail_Decode_Direct extends Kdmail_Decode {
	
	/**
	 * constructor
	 * 
	 * @param string
	 */
	public function __construct($mail = NULL)
	{
		if( ! empty($mail))
		{
			$this->set($mail);
		}
	}

	/**
	 * returns a mail
	 * 
	 * @return string
	 */
	public function getMail()
	{
		parent::getMail();
		return $this->all();
	}

}