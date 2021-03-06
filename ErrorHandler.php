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
	private $max_errors_count = 100;
	private $errors_count = 0;
	private $ToStringConverter;
	private $AssertException;

	public function __construct(IErrorLogger $Logger = null, IMessageSender $MessageSender = null)
	{
		$this->Logger        = $Logger;
		$this->MessageSender = $MessageSender;
		$this->errors_types  = E_ALL & ~E_NOTICE;
	}

	public function Register()
	{
		set_error_handler(array($this, 'HandleError'), $this->errors_types);
		$this->error_handler_registered = true;
		set_exception_handler(array($this, 'HandleException'));
		$this->exception_handler_registered = true;
		register_shutdown_function(array($this, 'shutdown_handler'));
	}

	public function shutdown_handler()
	{
		$last_error = error_get_last();
		if (!empty($last_error))
		{
			if ($last_error['type'] & $this->errors_types)
			{
				$this->HandleError($last_error['type'], $last_error['message'], $last_error['file'], $last_error['line']);
			}
		}
	}

	protected function getNewErrorObject()
	{
		return new Error();
	}

	protected function constructErrorObject($error_code, $error_message, $filepath = '', $line = 0)
	{
		$Error = $this->getNewErrorObject();
		$Error->setCode($error_code);
		$Error->setMessage($error_message);
		$Error->setFilepath($filepath);
		$Error->setLine($line);
		if ($error_code===E_USER_NOTICE || $error_code===E_NOTICE)
		{
			$Error->setDebugTrace(NULL);
		}
		else
		{
			$Error->setDebugTrace($this->getCurrentBacktrace());
		}
		if ($error_code===E_USER_ERROR || $error_code===E_USER_WARNING)
		{
			$Error->setRequest($this->getRequestData());
		}
		else
		{
			$Error->setRequest($this->getShortRequestData());
		}
		return $Error;
	}

	protected function getRequestData()
	{
		if (empty($_SERVER['REMOTE_ADDR'])) return 'command line';
		$data = $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']
				."\nIP: ".$_SERVER['REMOTE_ADDR']
				."\nUSER AGENT: ".$_SERVER['HTTP_USER_AGENT']
				."\nSERVER_NAME.PHP_SELF: ".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']
				."\nMETHOD: ".$_SERVER['REQUEST_METHOD'];
		if (!empty($_GET))
		{
			$data .= "\nGET:\n".$this->getToStringConverter()->getStringFrom($_GET);
		}
		if (!empty($_POST))
		{
			$data .= "\nPOST:\n".$this->getToStringConverter()->getStringFrom($_POST);
		}
		return $data;
	}

	protected function getShortRequestData()
	{
		if (empty($_SERVER['REMOTE_ADDR'])) return 'command line';
		$data = $_SERVER['HTTP_HOST']
				."\nIP: ".$_SERVER['REMOTE_ADDR']
				."\nUSER AGENT: ".$_SERVER['HTTP_USER_AGENT']
				."\nSERVER_NAME: ".$_SERVER['SERVER_NAME']
				."\nMETHOD: ".$_SERVER['REQUEST_METHOD'];
		return $data;
	}

	public function HandleError($error_code, $error_message, $filepath = '', $line = 0)
	{
		if ($this->isErrorsCountOverLimit()) return false;
		$Error = $this->constructErrorObject($error_code, $error_message, $filepath, $line);
		$this->HandleErrorObject($Error);
	}

	protected function isErrorsCountOverLimit()
	{
		if ($this->errors_count > $this->max_errors_count) return true;
		else
		{
			$this->errors_count++;
			return false;
		}
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
		if ($this->isErrorsCountOverLimit()) return false;
		$Error = $this->constructErrorObject($Exception->getCode(), $Exception->getMessage(), $Exception->getFile(), $Exception->getLine());
		$this->HandleErrorObject($Error);
	}

	protected function FormatErrorMessage(Error $Error)
	{
		return $Error->getTimestamp()
				.' ['.$Error->getCode().'] "'.$Error->getMessage()
				.'" in file: '.$Error->getFilepath()
				.', line: '.$Error->getLine()
				.PHP_EOL.$Error->getRequest();
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
		return $this->getDebugTracer()->getCurrentBacktrace();
	}

	public function setMaxErrorsCount($max_errors_count)
	{
		$this->max_errors_count = $max_errors_count;
	}

	public function getErrorsCount()
	{
		return $this->errors_count;
	}

	/**
	 * @return \Jamm\Tester\DebugTracer
	 */
	public function getDebugTracer()
	{
		if (empty($this->debug_tracer)) $this->debug_tracer = new \Jamm\Tester\DebugTracer();
		return $this->debug_tracer;
	}

	/**
	 * @param \Jamm\Tester\DebugTracer $DebugTracer
	 */
	public function setDebugTracer(\Jamm\Tester\DebugTracer $DebugTracer)
	{
		$this->debug_tracer = $DebugTracer;
	}

	public function setAssertBehavior($active = false, $bail = false)
	{
		assert_options(ASSERT_ACTIVE, $active);
		assert_options(ASSERT_BAIL, $bail);
	}

	public function setAssertException(\Exception $Exception)
	{
		$this->AssertException = $Exception;
		assert_options(ASSERT_CALLBACK, function($file, $line, $code) { $this->assertCallback($file, $line, $code); });
	}

	protected function assertCallback($file, $line, $code)
	{
		if (!empty($this->AssertException))
		{
			$exception_class = get_class($this->AssertException);
			$Exception       = new $exception_class('Internal error', 500);
		}
		else
		{
			$Exception = new \Exception("Internal error", 500);
		}
		trigger_error("Assertion failed in file $file line $line with code $code", E_USER_WARNING);
		throw $Exception;
	}

	/**
	 * @return ToStringConverter
	 */
	protected function getToStringConverter()
	{
		if (empty($this->ToStringConverter))
		{
			$this->ToStringConverter = new ToStringConverter();
		}
		return $this->ToStringConverter;
	}
}
