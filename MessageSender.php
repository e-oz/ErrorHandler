<?php
namespace Jamm\ErrorHandler;
class MessageSender implements IMessageSender
{
	private $emails = [];
	private $subject = 'Error';

	/**
	 * @param string|array $email at least one email should be set
	 */
	public function __construct($email)
	{
		if (empty($email)) {
			trigger_error('email is empty in message sender', E_USER_WARNING);
		}
		$this->addEmail($email);
	}

	public function SendMessage($message, $subject = '')
	{
		if (empty($subject)) $subject = $this->subject;
		foreach ($this->getEmails() as $email) {
			$this->sendEmail($email, $message, $subject);
		}
	}

	/**
	 * @param string|array $email
	 */
	public function addEmail($email)
	{
		if (!is_array($email)) {
			$email = [$email];
		}
		$this->emails = array_unique(array_merge($this->emails, $email));
	}

	public function setSubject($subject)
	{
		$this->subject = $subject;
	}

	/**
	 * @return mixed
	 */
	public function getEmails()
	{
		return $this->emails;
	}

	/**
	 * @return string
	 */
	public function getSubject()
	{
		return $this->subject;
	}

	/**
	 * @param $email
	 * @param $message
	 * @param $subject
	 * @return bool
	 */
	protected function sendEmail($email, $message, $subject)
	{
		return mail($email, $subject, $message);
	}
}
