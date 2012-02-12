<?php defined('SYSPATH') or die('No direct script access.');
/**
 * QdmailReceiver including QdmailDecoder & QdPop
 * E-Mail for multibyte charset
 *
 * PHP versions 4 and 5 (PHP4.3 upper)
 *
 * Copyright 2008, Spok in japan , tokyo
 * hal456.net/qdmail    :  http://hal456.net/qdmail_rec/
 * & CPA-LAB/Technical  :  http://www.cpa-lab.com/tech/
 * Licensed under The MIT License License
 *
 * @copyright		Copyright 2008, Spok.
 * @link			http://hal456.net/qdmail_rec/
 * @version			0.1.4.alpha
 * @lastmodified	2008-09-15
 * @license			The MIT License http://www.opensource.org/licenses/mit-license.php
 * 
 * QdmailReceiver is POP Receive & decorde e-mail library for multibyte language ,
 * easy , quickly , usefull , and you can specify deeply the details.
 * Copyright (C) 2008   spok 
 * 
 */

/**
 * Kdmail
 *
 * - based on QdmailReceiver
 *
 * @package   Kdmail
 * @author    ele_eel
 * @copyright 9km.jp
 * @license   http://www.opensource.org/licenses/mit-license.php
 */
class Kohana_Kdmail {

	//module version
	const VERSION = '0.1.0';
	
	//instances
	public static $instance = array();

	/**
	 * returns the instance of the decode class
	 * 
	 * @param string direct or pop or stdin
	 * @param mixed  see each decode class
	 * @return object Kdmail_Decode
	 */
	public static function instance($type, $param = NULL)
	{
		if( ! isset(Kdmail::$instance[$type]))
		{
			$class = 'Kdmail_Decode_'.$type;
		
			Kdmail::$instance[$type] = new $class($param);
		}
		return Kdmail::$instance[$type];
	}

	/**
	 * returns Kdmail_Decode_Direct
	 * 
	 * @param string
	 * @return object Kdmail_Decode_Direct
	 */
	public static function direct($param = NULL)
	{
		return Kdmail::instance('direct', $param);
	}
	
	/**
	 * returns Kdmail_Decode_Stdin
	 * 
	 * @return object Kdmail_Decode_Stdin
	 */
	public static function stdin()
	{
		return Kdmail::instance('stdin');
	}
	
	/**
	 * returns Kdmail_Decode_Pop
	 * 
	 * @param mixed
	 * @return object Kdmail_Decode_Pop
	 */
	public static function pop($param = NULL)
	{
		return Kdmail::instance('pop', $param);
	}
}