<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Kdmail Decode Pop
 * accesses your POP account.
 *
 * @package    Kdmail
 * @author     ele_eel
 * @copyright  9km.jp
 * @license    http://www.opensource.org/licenses/mit-license.php
 */
class Kohana_Kdmail_Decode_Pop extends Kdmail_Decode {

	//mail server configration
	protected $_server = array(
		'host' => '',
		'port' => 110,
		'user' => '',
		'pass' => '',
		'timeout' => 5,
	);
	//pop params
	protected $_pop = array(
		'delete'   => FALSE,
		'pop_high_speed' => -1,
		'pop_uid'  => 'popuid',
		'get_uid'  => TRUE,
		'uid_list' => array(),
		'pointer'  => 1,
		'count'    => 0,
	);
	//stream handler
	protected $_fp;
	
	//stream strings
	public $messages = array();

	/**
	 * sets the mail server configuration
	 *
	 * @param mixed if string, loads config file; if array, overwrites the param.
	 */
	public function __construct($server)
	{
		//load config
		if(is_string($server))
		{
			$this->_server = Kohana::$config->load('kdmail')->get($server);
		}
		//overwrite server config
		elseif(is_array($server))
		{
			$this->_server = array_merge($this->_server, $server);
		}
	}

	/**
	 * builds the mail header
	 * adds 'popid's to the mail header if need
	 * 
	 * @param string
	 * @return array
	 */
	function buildHeader($header_laof)
	{
		$header = parent::buildHeader($header_laof);

		if($this->_pop['get_uid'])
		{
			$i = 0;
			$key = $this->_pop['pop_uid'];
			while(isset($header[$key]) AND $i < 10000) {
				$key = $this->_pop['pop_uid'].'_'.$i++;
			}
			$id = $this->getUid();
			if(FALSE !== $id)
			{
				$header[$key] = $id;
			}
		}
		return $header;
	}

	/**
	 * builds a header with UIDL
	 * 
	 * @param boolean
	 * @return object Kohana_Kdmail_Decode_Pop 
	 */
	function withUid($bool = TRUE)
	{
		$this->_pop['get_uid'] = $bool === TRUE OR $bool;
		return $this;
	}

	/**
	 * deletes a mail always after calling getMail()
	 * 
	 * @param boolean
	 * @return object Kohana_Kdmail_Decode_Pop 
	 */
	function delete_after_load($bool = TRUE)
	{
		$this->_pop['delete'] = $bool === TRUE OR $bool;
		return $this;
	}

	/**
	 * connects a mail server
	 * 
	 * @return object this
	 * @throws Kdmail_Exception 
	 */
	public function connect()
	{
		try
		{
			$this->_fp = fsockopen($this->_server['host'], $this->_server['port'], $err, $errst, $this->_server['timeout']);
		}
		catch(Exception $e)
		{
			//this HOST is not an available host name. 
			throw new Kdmail_Exception('Connection failure, HOST :host is not available.', array(':host'=>$this->_server['host']));
		}

		//
		if(FALSE === $this->_fp)
		{
			throw new Kdmail_Exception('Connection failure, HOST :host PORT: :port.', array(':host'=>$this->_server['host'],':port'=>$this->_server['port']));
		}
		else
		{
			stream_set_timeout($this->_fp, $this->_server['timeout']);

			$this->_pop['uid_list'] = array();
			$this->getMessageOne();
			if($this->isOK($this->communicate('USER '.$this->_server['user'])->getMessageOne()) === FALSE OR 
					$this->isOK($this->communicate('PASS '.$this->_server['pass'])->getMessageOne()) === FALSE)
			{
				throw new Kdmail_Exception('Mail account USER: :user is not valid.', array(':user'=>$this->_server['user']));
			}
		}
		return $this;
	}

	/**
	 * sends a command to the server
	 * 
	 * @param string command
	 * @param string command options
	 * @return object this
	 * @throws Kdmail_Exception 
	 */
	public function communicate($cmd, $param = '')
	{
		$cmd = $param != '' ? $cmd.' '.$param : $cmd;
		
		if(fwrite($this->_fp, $cmd.Kdmail_Decode::$CR) === FALSE)
		{
			throw new Kdmail_Exception('Comunicate error: :error', array(':error'=>'the resouce stream is not writable.'));
		}
		return $this;
	}
	
	/**
	 * is status OK? checks the message string
	 * 
	 * @param string
	 * @return boolean
	 */
	public function isOK($str)
	{
		return ('+OK' === strtoupper(substr(trim($str), 0, 3)));
	}

	/**
	 * closes the connection
	 * 
	 * @throws Exception
	 */
	public function quit()
	{
		try
		{
			if($this->_fp)
			{
				$this->communicate('QUIT');
				fclose($this->_fp);
			}
			$this->_fp = NULL;
		}
		catch(Exception $e)
		{
			throw $e;
		}
	}

	/**
	 * has connected a server? if not, reconnect
	 * 
	 * @return boolean 
	 */
	protected function _preCheck($with_count = FALSE)
	{
		if( ! is_resource($this->_fp))
		{
			$this->connect();
		}
		//check count and pointer nums
		if(TRUE === $with_count)
		{
			( 0 !== $this->_pop['count']) OR $this->count();
			if(( 0 >= $this->_pop['pointer']) OR ( $this->_pop['pointer'] > $this->_pop['count'] ))
			{
				return FALSE;
			}
		}
		return TRUE;
	}

	/**
	 * command : STAT
	 * counts mails on the server
	 * 
	 * @param boolean number of retrying time
	 * @return integer
	 */
	public function count($retry = FALSE)
	{
		if( ! $retry AND $this->_pop['count'] > 0)
		{
			return $this->_pop['count'];
		}

		$this->_preCheck();
		$arr = explode(' ', $this->communicate('STAT')->getMessageOne());
		
		$this->_pop['count'] = $arr[1];
		return $this->_pop['count'];
	}

	/**
	 * command : RETR
	 * 
	 * @return string
	 */
	public function getMail()
	{
		parent::getMail();
		
		$data = FALSE;
		if(-1 !== $this->_pop['pop_high_speed'])
		{
			$data = $this->top(null, $this->_pop['pop_high_speed']);
		}
		else
		{
			if($this->_preCheck(TRUE))
			{
				$mail = $this->communicate('RETR', $this->_pop['pointer'])->getMessage(FALSE);
				
				if($this->_pop['delete'] === TRUE)
				{
					$this->delete($this->_pop['pointer']);
				}
				$data = $mail[1];
				$this->set($data);
			}
		}
		return $data;
	}

	/**
	 * gets all mails
	 * 
	 * @return array
	 */
	public function getMailAll()
	{
		$this->_preCheck();
		
		( 0 !== $this->_pop['count'] ) OR $this->count();
		
		$mail = array();
		for($i = 1; $i <= $this->_pop['count']; $i++)
		{
			$mail[] = $this->getMail();
			$this->_pop['pointer']++;
		}
		return $mail;
	}

	/**
	 * command : TOP
	 * 
	 * @param integer TOP command param
	 * @param integer TOP command param
	 * @return string
	 */
	public function top($msg_num = NULL, $line_num = NULL)
	{
		$data = FALSE;
		if($this->_preCheck(TRUE))
		{
			$msg_num = is_null($msg_num) ? $this->_pop['pointer'] : $msg_num;
			$line_num = is_null($line_num) ? 0 : $line_num;
			$mail = $this->communicate('TOP', $msg_num.' '.$line_num)->getMessage(FALSE);
			$data = $mail[1];
		}
		return $data;
	}

	/**
	 * command : UIDL
	 * 
	 * @param integer
	 * @return string
	 */
	public function getUid($msg_num = NULL)
	{
		$data = FALSE;
		if($this->_preCheck(TRUE))
		{
			$msg_num = is_null($msg_num) ? $this->_pop['pointer'] : $msg_num;
			$msg = $this->communicate('UIDL', $msg_num)->getMessageOne();
			
			$num = $msg_num;
			$id = NULL;
			if($this->isOK($msg))
			{
				$_id = explode(' ', $msg);
				$num = $_id[1];
				$id = $_id[2];
			}
			
			if( ! empty($id) AND ( (int) $num == (int) $msg_num ))
			{
				$data = $id;
			}
		}
		return $data;
	}

	/**
	 * gets UIDL of all mails
	 * 
	 * @return array
	 */
	public function getUidAll()
	{
		$this->_preCheck();
		
		$this->_pop['uid_list'] = array();
		
		$mail = $this->communicate('UIDL')->getMessage();
		$_id = explode(' ', array_shift($mail));
		
		if($this->isOK($_id[0]) AND 0 < count($mail))
		{
			foreach($mail as $ma)
			{
				if('.' === $ma)
				{  // fool proof
					break;
				}
				$temp = explode(' ', $ma);
				$this->_pop['uid_list'][$temp[0]] = trim($temp[1]);
			}
		}
		return $this->_pop['uid_list'];
	}

	/**
	 * gets the number form UIDL
	 * 
	 * @param string
	 * @return mixed
	 */
	public function uidToNum($uid)
	{
		if(0 === count($this->_pop['uid_list']))
		{
			$this->getUidAll();
		}
		return array_search($uid, $this->_pop['uid_list']);
	}

	/**
	 * command : RSET
	 * 
	 * @return boolean
	 */
	public function reset()
	{
		$this->_preCheck();
		return $this->isOK($this->communicate('RSET')->getMessageOne());
	}

	/**
	 * pointer +1
	 * 
	 * @return object Kohana_Kdmail_Decode_Pop 
	 */
	public function next()
	{
		$this->pointer('++');
		return $this;
	}

	/**
	 * pointer -1
	 * 
	 * @return object Kohana_Kdmail_Decode_Pop 
	 */
	public function prev()
	{
		$this->pointer('--');
		return $this;
	}

	/**
	 * manipulates the pointer number
	 * 
	 * @param string
	 * @return integer
	 */
	public function pointer($param = NULL)
	{
		$this->alreadyReset();
		
		switch($param)
		{
			case '++' :
				$this->_pop['pointer']++;
				break;
			case '--' :
				$this->_pop['pointer']--;
				break;
			default :
				if(is_numeric($param))
				{
					$this->_pop['pointer'] = $param;
				}
		}
		return $this->_pop['pointer'];
	}

	/**
	 * command : DELE
	 * 
	 * @param integer
	 * @param boolean closes the connect after sending a DELE command
	 * @return object Kohana_Kdmail_Decode_Pop
	 * @throws Kdmail_Exception 
	 */
	public function delete($num = NULL, $done = FALSE)
	{
		$num = is_null($num) ? $this->_pop['pointer'] : $num;
		
		if( ! is_numeric($num))
		{
			throw new Kdmail_Exception('Specifed error , delete command needs numeric');
		}

		$result = $this->isOK($this->communicate('DELE', $num)->getMessageOne());
		
		//close and delete
		if($done AND TRUE === $result)
		{
			$this->quit();
		}
		return $result;
	}

	/**
	 * deletes a mail with UIDL
	 * 
	 * @param mixed a string or array of UIDL
	 * @return boolean
	 */
	public function deleteUid($uid)
	{
		if( ! is_array($uid))
		{
			$uid = array($uid);
		}
		$uid = array_flip($uid);
		
		$this->pointer(1);
		$max = $this->count();
		
		$result = TRUE;
		for($i = 1; $i <= $max; $i++)
		{
			$key = $this->getUid($i);
			if(isset($uid[trim($key)]))
			{
				$result = ($result AND $this->delete($i));
			}
		}
		return $result;
	}

	/**
	 * gets the list of headers in all mails
	 * call this after getMail() or getMailAll()
	 * 
	 * @param string [keys of the mail header]
	 * @return array
	 */
	public function listHeader()
	{
		$parameter = func_get_args();

		foreach($parameter as $key => $param)
		{
			if(is_string($param))
			{
				$parameter[$key] = array($param);
			}
		}

		$ret = array();
		$max = $this->count();
		
		$this->_pop['pop_high_speed'] = 0;
		
		for($i = 1; $i <= $max; $i++)
		{
			$this->pointer($i);
			foreach($parameter as $key => $param)
			{
				$ret[$i][$param[0]] = $this->header($param, NULL);
			}
		}
		$this->_pop['pop_high_speed'] = -1;
		
		return $ret;
	}

	/**
	 * checks the end of this stream
	 * 
	 * @return boolean
	 */
	public function eof()
	{
		$c = $this->count();
		$p = $this->pointer();
		
		return empty($c) OR ($p <= 0) OR ( $p > $c );
	}
	
	/**
	 * gets one line message
	 * 
	 * @return string
	 */
	public function getMessageOne()
	{
		$this->_preCheck();
		
		$r = trim(fgets($this->_fp, 512));
		$this->messages[] = $r;
		
		return $r;
	}

	/**
	 * gets messages until the end of lines
	 * 
	 * @param boolean
	 * @return array
	 */
	public function getMessage($array = TRUE)
	{
		$this->_preCheck();
		$data = array();
		
		$data[0] = fgets($this->_fp, 512);

		if(('.' === substr($data[0], -1)))
		{
			return $data;
		}

		if( ! $array)
		{
			$data[1] = NULL;
		}
		
		$r = fgets($this->_fp, 512);
		
		while(('.' != trim($r)) AND (FALSE !== $r) AND ( ! feof($this->_fp)))
		{
			if('.' === substr($r, 0, 1) AND '.' !== $r)
			{
				$r = substr($r, 1);
			}
			if($array)
			{
				$data[] = $r;
			}
			else
			{
				$data[1] .= $r;
			}
			$r = fgets($this->_fp, 512);
		}
		if(isset($data[1]))
		{
			$data[1] = $array ? $data[1] : trim($data[1]."\r\n\r\n");
		}
		return $data;
	}

	/**
	 * closes the connection
	 */
	public function __destruct()
	{ 
		$this->quit();
	}
}