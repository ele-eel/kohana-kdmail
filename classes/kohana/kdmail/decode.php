<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Kdmail Decode
 * the abstruct class to decode a mail
 *
 * @package    Kdmail
 * @author     ele_eel
 * @copyright  9km.jp
 * @license    http://www.opensource.org/licenses/mit-license.php
 */
 abstract class Kohana_Kdmail_Decode {
	
	//the character set outputting
	public static $target_charset = 'utf-8';
	//carrige return
	public static $CR = "\r\n";

	public $header = array();
	public $all;
	public $body_all;
	public $body = array();

	//header, text, attach, getMail
	protected $_already = array();
	
	//decode types
	protected $_decode = array(
		'text' => TRUE,
		'attach' => TRUE,
	);
	
	//attached files
	protected $_attach = array();
	protected $_upfile = array();
	
	//this mail is html file?
	protected $_is_html;
	
	//
	protected $_line = array();
	
	//
	protected $_max = 0;
	protected $_num = 0;
	
	//these multi parts are skipped
	protected $_other_multipart = array(
		'alternative' => 'skip',
		'related' => 'skip',
		'signed' => 'skip',
		'mixed' => 'skip',
		'x-mixed-replace' => 'skip',
		'parallel' => 'skip',
		'encrypted' => 'skip',
	);

	/**
	 * returns a mail
	 * see the method in each decode class
	 */
	public function getMail()
	{
		$this->alreadyReset();
		$this->_already['getMail'] = TRUE;
	}

	/**
	 * builds the mail header
	 * 
	 * @param string
	 * @return array
	 */
	public function buildHeader($header_laof)
	{
		$header = array();
		$line = preg_split('/\r?\n/is', trim($header_laof));
		
		// connect line
		$prev_key = 0;
		foreach($line as $key => $li)
		{
			if(1 === preg_match('/^\s/', $li))
			{
				$line[$key] = $line[$prev_key].Kdmail_Decode::$CR.$line[$key];
				unset($line[$prev_key]);
			}
			$prev_key = $key;
		}
		
		// split header
		foreach($line as $li)
		{
			if(FALSE !== ( $split = strpos($li, ':') ))
			{
				$obj = trim(substr($li, $split + 1));
				$p_name = strtolower($org = substr($li, 0, $split));
				if(isset($header[$p_name]) AND !is_array($header[$p_name]))
				{
					$temp = $header[$p_name];
					$header[$p_name] = array($temp, $obj);
				}
				elseif(isset($header[$p_name]) AND is_array($header[$p_name]))
				{
					$header[$p_name][] = $obj;
				}
				else
				{
					$header[$p_name] = $obj;
				}
			}
			else
			{
				continue;
			}
		}
		return $header;
	}

	/**
	 * sets a mail to $this->all
	 *
	 * @return object Kohana_Kdmail_Decode
	 */
	public function set($param = NULL)
	{
		$this->all = is_string($param) ? $param : NULL;
		return $this;
	}

	/**
	 * returns a mail text
	 *
	 * @return string
	 */
	public function all()
	{
		return $this->all;
	}

	/**
	 * sets _text_decode
	 * 
	 * @param boolean
	 * @return object Kohana_Kdmail_Decode
	 */
	public function textDecode($bool = TRUE)
	{
		$this->_decode['text'] = $bool === TRUE OR $bool;
		return $this;
	}

	/**
	 * sets _attach_decode
	 * 
	 * @param boolean
	 * @return object Kohana_Kdmail_Decode
	 */
	public function attachDecode($bool = TRUE)
	{
		$this->_decode['attach'] = $bool === TRUE OR $bool;
		return $this;
	}

	/**
	 * returns the header param
	 * 
	 * @param mixed header name or all
	 * @param boolean
	 * @return mixed array of the header or FALSE
	 */
	public function header($param = NULL, $return_false = FALSE)
	{
		if(empty($this->_already['header']))
		{
			$this->decodeHeader();
		}
		
		if(is_null($param))
		{
			return $this->header;
		}
		elseif(is_string($param))
		{
			if('all' === strtolower($param))
				return $this->header;
			else
				$param = array($param);
		}
		
		$ret = $this->arrayDigup($this->header, $param);
		return (FALSE === $ret) ? $return_false : $ret;
	}

	/**
	 * returns the mail body
	 * 
	 * @param mixed
	 * @return array 
	 */
	public function body($param = NULL)
	{
		if(empty($this->_already['text']))
		{
			$this->decodeBody();
		}
		if( ! is_array($param))
		{
			$param = array($param);
		}
		return $this->arrayDigup($this->body, $param);
	}

	/**
	 * checks the mail type and returns the mail body
	 * 
	 * @return mixed
	 */
	public function bodyAutoSelect()
	{
		$ret = $this->body(array('html', 'value'));
		if( ! empty($ret))
		{
			$this->_is_html = TRUE;
			return $ret;
		}
		$ret = $this->body(array('text', 'value'));
		if( ! empty($ret))
		{
			$this->_is_html = FALSE;
			return $ret;
		}
		return FALSE;
	}

	/**
	 * returns whether the mail is HTML
	 * 
	 * @return boolean
	 */
	public function isHtml()
	{
		if( ! isset($this->_is_html) AND FALSE === $this->bodyAutoSelect())
		{
			return FALSE;
		}
		return (boolean) $this->_is_html;
	}

	/**
	 * sets a body type of the mail
	 * 
	 * @param string html or text
	 * @return object Kohana_Kdmail_Decode 
	 */
	public function setBodyType($type = '')
	{
		switch($type)
		{
			case 'html' :
				$this->body(array('html', 'value'));
				break;
			case 'text' :
				$this->body(array('text', 'value'));
				break;
		}
		return $this;
	}

	/**
	 * returns the attached flie(s)
	 * 
	 * @return array if attached files are empty, then FALSE
	 */
	public function attach()
	{
		if(empty($this->_already['attach']))
		{
			$this->decodeBody();
		}

		if($this->_attach)
		{
			return $this->_attach;
		}
		else
		{
			if(count($this->_upfile) != 0)
			{
				return $this->_upfile;
			}
			else
			{
				return FALSE;
			}
		}
	}
	
	/**
	 * resets properties
	 */
	public function alreadyReset()
	{
		$this->body_all = NULL;
		$this->header = array();
		$this->body = array();
		$this->_already = array();
		$this->_attach = array();
	}

	/**
	 * decodes a mail header
	 */
	public function decodeHeader()
	{
		$addr = array(
			'to',
			'cc',
			'bcc',
			'reply-to',
			'from',
		);

		if(empty($this->_already['getMail']))
		{
			$this->getMail();
		}

		// cutting
		if(0 === preg_match('/\r?\n\r?\n/is', trim($this->all), $matches, PREG_OFFSET_CAPTURE))
		{
			$header_all = $this->all;
			$this->body_all = NULL;
		}
		else
		{
			$offset = $matches[0][1];
			$header_all = trim(substr($this->all, 0, $offset));
			$this->body_all = trim(substr($this->all, $offset + 1));
		}
		$this->header = $this->buildHeader($header_all);
		// address field action , force to array type
		foreach($addr as $ad)
		{
			if( ! isset($this->header[$ad]))
			{
				continue;
			}
			if(is_array($this->header[$ad]))
			{
				$addr_header = array_shift($this->header[$ad]);
			}
			else
			{
				$addr_header = $this->header[$ad];
			}
			$person = explode(',', $addr_header);
			$this->header[$ad] = array();
			foreach($person as $pers)
			{
				if( ! empty($pers))
				{
					$this->header[$ad][] = $this->splitMime($pers, TRUE);
				}
			}
		}
		// subject
		if(isset($this->header['subject']))
		{
			$this->header['subject'] = $this->splitMime($this->header['subject'], FALSE);
		}
		$this->_already['header'] = TRUE;
	}

	/**
	 * decodes a mail body
	 */
	public function decodeBody()
	{
		if(empty($this->_already['header']))
		{
			$this->decodeHeader();
		}
		// body
		if((empty($this->_already['text']) AND $this->_decode['text'])
				OR (empty($this->_already['attach']) AND $this->_decode['attach']))
		{
			if(isset($this->header['content-type']))
			{
				$type = $this->typeJudge($this->header['content-type']);

				preg_match('/boundary\s*=\s*"*([^"]+)"*/is', $this->header['content-type'], $matches);

				if(isset($matches[1]))
				{
					$this->_line = preg_split('/\r?\n/is', $this->body_all);
					$this->_num = 0;
					$this->_max = count($this->_line);
					$this->buildPart($matches[1], $type);
				}
				else
				{
					$this->body[$type] = $this->makeBody($this->header, $this->body_all);
					$this->_already['text'] = TRUE;
				}
			}
			else
			{
				$type = 'unknown';
				$_hd = array('content-type' => $type.'/'.$type);
				$this->body[$type] = $this->makeBody(array_merge($this->header, $_hd), $this->body_all);
			}
		}
	}

	/**
	 * builds a multi-part mail
	 * 
	 * @param string
	 * @param string
	 * @return boolean 
	 */
	public function buildPart($boundary, $type)
	{
		if( ! $this->skipto('--'.$boundary))
		{
			return FALSE;
		}
		do {
			// header in body
			$header = NULL;
			do {
				$li = $this->get_1_line(FALSE);
				if(FALSE === $li)
				{
					return FALSE;
				}
				$header .= $li.Kdmail_Decode::$CR;
			} while( ! empty($li) || '0' === $li);

			$header = $this->buildHeader($header);

			if(isset($header['content-type']))
			{
				$type = $this->typeJudge($header['content-type']);
				preg_match('/boundary\s*=\s*"?([^"]+)"?/is', $header['content-type'], $matches);
				
				if( ! empty($matches[1]))
				{
					$this->buildPart($matches[1], $type);
				}
			}
			else
			{
				$type = 'unknown';
				$header['content-type'] = $type.'/'.$type;
			}
			
			if(( ! $this->_decode['attach'] AND ($type == 'attach' || $type == 'unknown'))
					|| ( ! $this->_decode['text'] AND ($type == 'text' || $type == 'html')) )
			{
				if( ! $this->skipto('--'.$boundary))
				{
					return FALSE;
				}
				continue;
			}

			$plain_body = NULL;
			$li = $this->get_1_line(FALSE);
			while(( trim($li) != '--'.$boundary.'--') AND ( trim($li) != '--'.$boundary) AND (FALSE !== $li )) {
				$plain_body .= $li.Kdmail_Decode::$CR;
				$li = $this->get_1_line(FALSE);
			}
			$_body = $this->makeBody($header, $plain_body);
			if($_body['attach_flag'])
			{
				$type = 'attach';
			}

			if($type == 'attach' || $type == 'unknown')
			{
				$this->_attach[] = $_body;
				$this->_already['attach'] = TRUE;
			}
			elseif($type == 'text' || $type == 'html')
			{
				
				if(empty($this->body[$type]))
				{
					$this->body[$type] = $_body;
				}
				
				$this->_already['text'] = TRUE;
			}
			
		} while(trim($li) == '--'.$boundary);
		
		return TRUE;
	}

	/**
	 * makes a mail with the header and body
	 * 
	 * @param array
	 * @param string
	 * @return type 
	 */
	public function makeBody($header, $body)
	{
		if(1 === preg_match('/charset\s*=\s*"?([^\s;"]+)"?\s*;?\r?\n?/is', $header['content-type'], $matches))
		{
			$charset = $matches[1];
		}

		$encoding = isset($header['content-transfer-encoding']) ? $header['content-transfer-encoding'] : '7bit';

		if( ! is_null($body) AND ( 'base64' === strtolower($encoding) ))
		{
			$body = base64_decode($body);
		}
		elseif( ! is_null($body) AND ( 'quoted-printable' === strtolower($encoding) ))
		{
			$body = quoted_printable_decode($body);
		}

		if( ! is_null($body) AND ( 1 === preg_match('/text\//is', $header['content-type'], $matches) ))
		{
			$charset = isset($charset) ? $charset : mb_detect_encoding($body);

//			mb_check_encoding ([ string $var [, string $encoding ]] )
			if(FALSE !== strpos(strtoupper($charset), 'UNKNOWN'))
			{
				$charset = mb_detect_encoding($body);
			}
			$stack = mb_detect_order();
			if(( FALSE !== mb_detect_order($charset) ) AND isset(Kdmail_Decode::$target_charset) AND ( strtolower(Kdmail_Decode::$target_charset) != strtolower($charset)))
			{
				if(mb_check_encoding($body, $charset))
				{
					$body = mb_convert_encoding($body, Kdmail_Decode::$target_charset, $charset);
				}
			}
			mb_detect_order($stack);
		}

		$ret = array();
		if(isset($charset))
		{
			$ret['charset'] = $charset;
		}
		// attachment or other
		$ret['attach_flag'] = FALSE;
		if( ! empty($header['content-disposition']) || !empty($header['content-id']))
		{
			$ret['attach_flag'] = TRUE;
		}
		if( ! empty($header['content-id']))
		{
			$ret['content-id'] = $header['content-id'];
			$ret['content-id_essence'] = trim(trim($header['content-id'], '<>'));
		}

		// filename
		$filename = '';
		if($ret['attach_flag'] AND (1 === preg_match('/name\s*=\s*"?([^"\r\n]+)"?\r?\n?/is', $header['content-type'], $matches) ))
		{
			$filename = $matches[1];
		}
		elseif($ret['attach_flag'] AND isset($header['content-disposition']) AND (1 === preg_match('/name\s*=\s*"?([^"\r\n]+)"?\r?\n?/is', $header['content-disposition'], $matches) ))
		{
			$filename = $matches[1];
		}
		if(1 === preg_match('/(=\?.+\?=)/is ', $filename, $matches))
		{
			$_filename = mb_decode_mimeheader($matches[1]);
			$org_charset = mb_internal_encoding();
			if(isset(Kdmail_Decode::$target_charset) AND (strtolower(Kdmail_Decode::$target_charset) != strtolower($org_charset)))
			{
				if(mb_check_encoding($_filename, $org_charset))
				{
					$_filename = mb_convert_encoding(
							$_filename, Kdmail_Decode::$target_charset, $org_charset
					);
				}
			}

			$filename = str_replace($matches[1], $_filename, $filename);
		}

		$filename = trim($filename);
		if( ! empty($filename))
		{
			$ret['filename'] = $filename;
			$ret['filename_safe'] = urlencode($filename);
		}

		//mimetype
		if(1 === preg_match('/^\s*([^\s]*\/[^\s;]+)/is', $header['content-type'], $matches))
		{
			$ret['mimetype'] = $matches[1];
		}

		$ret['enc'] = $encoding;
		$ret['content-type'] = $header['content-type'];
		$ret['value'] = $body;


		if($ret['attach_flag'])
		{

			if(count($this->_upfile) == 0)
			{
				$this->_upfile[] = $ret;
			}
		}

		return $ret;
	}

	/**
	 * judges the mail type
	 * 
	 * @param string
	 * @return string 
	 */
	public function typeJudge($value)
	{
		$type = 'attach';
		preg_match('/\s*([^\s;,]+\/[^\s;,]+)\s*;?/is', $value, $matches);
		if( ! empty($matches[1]) AND 'TEXT/PLAIN' == strtoupper($matches[1]))
		{
			$type = 'text';
		}
		elseif( ! empty($matches[1]) AND 'TEXT/HTML' == strtoupper($matches[1]))
		{
			$type = 'html';
		}
		elseif( ! empty($matches[1]))
		{
			$slash = strpos($matches[1], '/');
			if(FALSE !== $slash)
			{
				$mime_main = strtolower(substr($matches[1], 0, $slash));
				$mime_sub = strtolower(substr($matches[1], $slash + 1));
				if('multipart' === $mime_main)
				{
					if(isset($this->_other_multipart[$mime_sub]) AND 'skip' === $this->_other_multipart[$mime_sub])
					{
						$type = 'skip';
					}
					else
					{
						$type = 'unknown';
					}
				}
			}
		}
		return $type;
	}

	/**
	 * skips texts to a boundary part
	 * 
	 * @param string
	 * @return boolean 
	 */
	public function skipto($boundary)
	{
		$fg = TRUE;
		while(trim($this->_line[$this->_num]) != trim($boundary)) {
			$this->_num++;
			if($this->_num >= $this->_max)
			{
				$fg = FALSE;
				break;
			}
		}
		return $fg;
	}

	/**
	 * gets one line text
	 * 
	 * @param boolean
	 * @return mixed
	 */
	public function get_1_line($empty = TRUE)
	{
		$fg = TRUE;
		do {
			if( ! isset($this->_line[$this->_num]))
			{
				$fg = FALSE;
				break;
			}
			$li = rtrim($this->_line[$this->_num++]);
			if($this->_num >= $this->_max)
			{
				$fg = FALSE;
				break;
			}
		} while($empty AND empty($li));
		return $fg ? $li : FALSE;
	}

	/**
	 * splits the mime header
	 * 
	 * @param string
	 * @param boolean
	 * @return array
	 */
	public function splitMime($var, $address_mode = FALSE)
	{
		$obj = array();
		$obj['value'] = trim($var);

		$var = preg_replace('/\r?\n\s*=\?/', '=?', $var);
		preg_match_all('/=\?(?:(?!\?=).)*\?=/is', $var, $matches);

		$mime_fg = FALSE;
		if(0 < count($matches[0]))
		{
			$rep = array();
			foreach($matches[0] as $one)
			{
				$rep[] = mb_decode_mimeheader($one);
			}
			$var = str_replace($matches[0], $rep, $var);
			$mime_fg = TRUE;
		}

		if($address_mode AND ( 1 === preg_match('/<?([^<>\s]+@[^<>\s]+\.[^<>\s]+)>?/is', $var, $matches) ))
		{
			$obj['mail'] = trim($matches[1]);
			$obj['name'] = trim(trim(str_replace($matches[0], '', $var), "\" \t"));
			if(empty($obj['name']))
			{
				unset($obj['name']);
			}
		}
		elseif( ! $address_mode)
		{
			$obj['name'] = $var;
		}

		if($mime_fg AND !empty($obj['name']))
		{
			$org_charset = mb_internal_encoding();
			$org_charset = empty($org_charset) ? NULL : $org_charset;
			if(isset(Kdmail_Decode::$target_charset) AND ( strtolower(Kdmail_Decode::$target_charset) != strtolower($org_charset)))
			{
				$obj['name'] = trim(mb_convert_encoding($obj['name'], Kdmail_Decode::$target_charset, $org_charset));
			}
		}
		return $obj;
	}

	/**
	 * searches key and returns value
	 * 
	 * @param array
	 * @param array
	 * @return mixed
	 */
	public function arrayDigup($array, $keys)
	{
		$key = array_shift($keys);
		
		if(isset($array[$key]))
		{
			if(0 === count($keys))
			{
				return $array[$key];
			}
			else
			{
				return $this->arrayDigup($array[$key], $keys);
			}
		}
		elseif(isset($array[0]))
		{
			array_unshift($keys, $key);
			return $this->arrayDigup($array[0], $keys);
		}
		else
		{
			return FALSE;
		}
	}

}