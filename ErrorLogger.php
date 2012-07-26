<?php
namespace Jamm\ErrorHandler;

class ErrorLogger implements IErrorLogger
{
	/** @var \Jamm\Memory\IMemoryStorage */
	protected $storage;
	private $cache_key = 'messages';
	private $errors_count_limit = 100;
	private $log_ttl = 604800;

	public function WriteError(Error $Error)
	{
		$this->storage->increment($this->cache_key, array($Error),
			$this->errors_count_limit, $this->log_ttl);
	}

	public function __construct(\Jamm\Memory\IMemoryStorage $storage)
	{
		$this->storage = $storage;
	}

	public function getErrors()
	{
		return $this->storage->read($this->cache_key);
	}

	public function getNextError()
	{
		if ($this->storage->acquire_key($this->cache_key, $auto_unlocker))
		{
			$messages = $this->storage->read($this->cache_key);
			if (empty($messages)) return false;
			if (!is_array($messages))
			{
				$this->FlushLog();
				return $messages;
			}
			$message = array_shift($messages);
			$this->storage->save($this->cache_key, $messages, $this->log_ttl);
			return $message;
		}
		return false;
	}

	public function FlushLog()
	{
		$this->storage->del($this->cache_key);
	}

	public function setErrorsCountLimit($errors_count_limit)
	{
		$this->errors_count_limit = $errors_count_limit;
	}

	public function setLogTtl($log_ttl)
	{
		$this->log_ttl = $log_ttl;
	}
}
