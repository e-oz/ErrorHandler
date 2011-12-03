<?php
namespace Jamm\ErrorHandler;

class Watcher
{
	private $storage;
	private $key_point;
	private $key_line;
	private $line_length = 100;
	private $prev_point_memory = 0;
	private $line_memory_step = 20000;

	const point_time   = 'time';
	const point_memory = 'memory';
	const point_name   = 'point';

	public function __construct(\Jamm\Memory\IMemoryStorage $storage, $key_point = 'watch', $key_line = 'watch_line')
	{
		$this->storage   = $storage;
		$this->key_line  = $key_line;
		$this->key_point = $key_point;
		if (empty($this->key_point)) trigger_error('Key point should not be empty', E_USER_WARNING);
	}

	public function setPoint($point_name)
	{
		$data = $this->getDataForPoint($point_name);
		$this->storage->save($this->key_point, $data);
		$this->addLinePoint($data);
	}

	public function &setWatchedPoint($point_name)
	{
		$this->setPoint($point_name);
		$ExitWatcher = new \Jamm\Memory\KeyAutoUnlocker(array($this, 'watchFunctionExit'));
		$ExitWatcher->setKey($point_name);
		return $ExitWatcher;
	}

	public function watchFunctionExit(\Jamm\Memory\IKeyLocker $ExitWatcher = NULL)
	{
		$point_name = ':exit';
		if (!empty($ExitWatcher))
		{
			$point_name = $ExitWatcher->getKey().$point_name;
			$ExitWatcher->revoke();
		}
		$this->setPoint($point_name);
	}

	protected function addLinePoint($data)
	{
		if (!empty($this->key_line))
		{
			if (empty($this->line_memory_step) || (abs($data[self::point_memory]-$this->prev_point_memory) > $this->line_memory_step))
			{
				$this->storage->increment($this->key_line, array($data), $this->line_length);
				$this->prev_point_memory = $data[self::point_memory];
			}
		}
	}

	protected function getDataForPoint($point_name)
	{
		$data = array(
			self::point_name => $point_name,
			self::point_time => time(),
			self::point_memory => memory_get_usage()
		);
		return $data;
	}

	public function getCurrentPoint()
	{
		return $this->storage->read($this->key_point);
	}

	public function getPointsArray()
	{
		return $this->storage->read($this->key_line);
	}

	public function clean()
	{
		$this->storage->del($this->key_point);
		$this->storage->del($this->key_line);
	}

	public function getLineMemoryStep()
	{
		return $this->line_memory_step;
	}

	public function setLineMemoryStep($line_memory_step)
	{
		$this->line_memory_step = $line_memory_step;
	}
}
