<?php
namespace Jamm\ErrorHandler;

class ErrorHandler
{
	private $errors_types = E_ALL;
	private $MessageSender;
	private $Logger;
	private $error_handler_registered = false;
	private $exception_handler_registered = false;
	/** @var \Jamm\Tester\DebugTracer */
	private $debug_tracer;

	public function __construct(IErrorLogger $Logger = null, IMessageSender $MessageSender = null)
	{
		$this->Logger = $Logger;
		$this->MessageSender = $MessageSender;
		$this->errors_types = E_ALL & ~E_NOTICE;
	}

	public function Register()
	{
		if (set_error_handler(array($this, 'HandleError'), $this->errors_types))
		{
			$this->error_handler_registered = true;
		}

		if (set_exception_handler(array($this, 'HandleException')))
		{
			$this->exception_handler_registered = true;
		}
	}

	protected function getNewErrorObject()
	{
		return new Error();
	}

	private function constructErrorObject($error_code, $error_message, $filepath = '', $line = 0)
	{
		$Error = $this->getNewErrorObject();
		$Error->setCode($error_code);
		$Error->setMessage($error_message);
		$Error->setFilepath($filepath);
		$Error->setLine($line);
		$Error->setDebugTrace($this->getCurrentBacktrace());
		$Error->setRequest($this->getRequestData());
		return $Error;
	}

	private function getRequestData()
	{
		return "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']
				."\nIP: ".$_SERVER['REMOTE_ADDR']
				."\nUSER AGENT: ".$_SERVER['HTTP_USER_AGENT']
				."\nSERVER_NAME.PHP_SELF: ".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']
				."\nGET:\n".print_r($_GET, 1)
				."\nPOST:\n".print_r($_POST, 1);
	}

	public function HandleError($error_code, $error_message, $filepath = '', $line = 0)
	{
		$Error = $this->constructErrorObject($error_code, $error_message, $filepath, $line);
		$this->HandleErrorObject($Error);
	}

	private function HandleErrorObject(Error $Error)
	{
		if (!empty($this->MessageSender))
		{
			$message = $this->FormatErrorMessage($Error);
			$this->MessageSender->SendMessage($message);
		}

		if (!empty($this->Logger))
		{
			$this->Logger->WriteError($Error);
		}
	}

	public function HandleException(\Exception $Exception)
	{
		$Error = $this->constructErrorObject($Exception->getCode(), $Exception->getMessage(), $Exception->getFile(), $Exception->getLine());
		$this->HandleErrorObject($Error);
	}

	protected function FormatErrorMessage(Error $Error)
	{
		return '['.$Error->getCode().'] "'.$Error->getMessage().'" in file: '.$Error->getFilepath().', line: '.$Error->getLine();
	}

	public function setHandledErrorsTypes($errors_types)
	{
		$this->errors_types = $errors_types;
	}

	public function getMessageSender()
	{
		return $this->MessageSender;
	}

	public function setMessageSender($reporter)
	{
		$this->MessageSender = $reporter;
	}

	public function getLogger()
	{
		return $this->Logger;
	}

	public function setLogger($logger)
	{
		$this->Logger = $logger;
	}

	public function __destruct()
	{
		if ($this->error_handler_registered) restore_error_handler();
		if ($this->exception_handler_registered) restore_exception_handler();
	}

	private function getCurrentBacktrace()
	{
		if (empty($this->debug_tracer)) $this->debug_tracer = new \Jamm\Tester\DebugTracer();
		return $this->debug_tracer->getCurrentBacktrace();
	}
}
