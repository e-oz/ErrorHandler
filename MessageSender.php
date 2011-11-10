<?php
namespace Jamm\ErrorHandler;

class MessageSender implements IMessageSender
{
	private $email;
	private $subject = 'Error';

	public function __construct($email)
	{
		$this->email = $email;
		if (empty($email)) throw new \Exception('email is empty in message sender');
	}

	public function SendMessage($message, $subject = '')
	{
		if (empty($subject)) $subject = $this->subject;
		return mail($this->email, $subject, $message);
	}

	public function setEmail($email)
	{
		$this->email = $email;
	}

	public function setSubject($subject)
	{
		$this->subject = $subject;
	}
}
