<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Kdmail Decode Stdin
 * parses a mail-text via STDIN.
 *
 * @package    Kdmail
 * @author     ele_eel
 * @copyright  9km.jp
 * @license    http://www.opensource.org/licenses/mit-license.php
 */
class Kohana_Kdmail_Decode_Stdin extends Kdmail_Decode {

	/**
	 * returns a mail text
	 * 
	 * @return string
	 * @throws Kdmail_Exception 
	 */
	public function getMail()
	{
		parent::getMail();
		
		$fp = fopen('php://stdin', 'r');
		
		if( ! is_resource($fp))
		{
			throw new Kdmail_Exception('Kdmail have no resouce.');
		}
		
		$content = '';
		while( ! feof($fp))
		{
			$content .= fgets($fp, 1024);
		}
		$this->set($content);
		
		return $content;
	}

}