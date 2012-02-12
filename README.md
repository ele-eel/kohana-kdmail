#Kdmail - the multi-byte mail decoder and POP mail client

This module is in development.

Kdmail based on QdmailReceiver
http://hal456.net/qdmail_rec/


##How to use

###1.direct mode
This mode is to parse a mail-text directly.

	<?php
		$data = Kdmail::direct(file_get_contents('path/to/mail.txt'))
			->getMail();
	?>

###2.stdin mode
This mode is to parse a mail via STDIN. It will be used at CLI.

##3.pop mode
This mode is to access a POP account, it can read, delete, count mails and use other POP operations.

	<?php
		
		$stream = Kdmail::pop('your_account_name')->connect();
		
		//counts your mails on the server.
		$stream->count();
		
		//gets all your mails.
		$all_mails = $stream->getMailAll();
		foreach($all_mails as $mail)
		{
			//some code...
		}
	?>

To access the POP account, setup the Kdmail config or pass args to the instance.

	<?php
		//give a string, then loads the account in the Kdmail config.
		$stream = Kdmail::pop('your_account_name');
		
		//or give a array of your account.
		$stream = Kdmail::pop(array(
			'host' => '',	//host name
			'port' => 110,	//port number
			'user' => '',	//user name
			'pass' => '',	//password
			'timeout' => 5, //seconds
		));
	?>