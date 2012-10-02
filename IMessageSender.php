<?php
namespace Jamm\ErrorHandler;
interface IMessageSender
{
	public function SendMessage($message, $subject = '');
}
