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
	private $do_exit_on_stop_signal = true;
	private $memory_reserve = 10485760; //10Mb
	private $max_memory_limit;
	private $in_throw = false;

	const point_time   = 'time';
	const point_memory = 'memory';
	const point_name   = 'point';
	const stop_key     = 'stop';

	public function __construct(\Jamm\Memory\IMemoryStorage $storage, $key_point = 'watch', $key_line = 'watch_line')
	{
		$this->storage   = $storage;
		$this->key_line  = $key_line;
		$this->key_point = $key_point;
		if (empty($this->key_point)) trigger_error('Key point should not be empty', E_USER_WARNING);
		$this->max_memory_limit = intval(ini_get('memory_limit'))*1024*1024;
	}

	public function setPoint($point_name)
	{
		$this->CheckStopKey();
		$data = $this->getDataForPoint($point_name);
		$this->storage->save($this->key_point, $data);
		$this->addLinePoint($data);
	}

	/**
	 * @param string $point_name
	 * @param \Jamm\Memory\KeyAutoUnlocker $ExitWatcher set variable here to get Watcher into scope.
	 *                                                  When this object will be destructed, event will be generated, to catch exiting from scope of function or loop.
	 * @see      http://en.wikipedia.org/wiki/Resource_Acquisition_Is_Initialization
	 * @return \Jamm\Memory\KeyAutoUnlocker
	 */
	public function setWatchedPoint($point_name, \Jamm\Memory\KeyAutoUnlocker &$ExitWatcher = NULL)
	{
		$this->setPoint($point_name);
		if (empty($ExitWatcher))
		{
			$ExitWatcher = new \Jamm\Memory\KeyAutoUnlocker(array($this, 'watchFunctionExit'));
		}
		$ExitWatcher->setKey($point_name);
		return $ExitWatcher;
	}

	public function watchFunctionExit(\Jamm\Memory\IKeyLocker $ExitWatcher = NULL)
	{
		if ($this->in_throw)
		{
			$ExitWatcher->revoke();
			return false;
		}
		$point_name = ':exit';
		if (!empty($ExitWatcher))
		{
			$point_name = $ExitWatcher->getKey().$point_name;
			$ExitWatcher->revoke();
		}
		$this->setPoint($point_name);
	}

	protected function checkMemoryLimit()
	{
		if (!$this->do_exit_on_stop_signal) return false;
		$current_memory_usage = memory_get_usage();
		if (($this->max_memory_limit-$current_memory_usage) < $this->memory_reserve)
		{
			$this->in_throw = true;
			throw new \Exception('Memory limit, max: '.round($this->max_memory_limit/1048576).'Mb, current: '.round($current_memory_usage/1048576).'Mb, minimum: '.round($this->memory_reserve/1048576).'Mb ');
		}
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
			self::point_name   => $point_name,
			self::point_time   => time(),
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

	public function setDoExitOnStopSignal($do_exit_on_stop_signal = true)
	{
		$this->do_exit_on_stop_signal = $do_exit_on_stop_signal;
	}

	public function setMemoryReserve($min_memory_limit)
	{
		$this->memory_reserve = $min_memory_limit;
	}

	private function CheckStopKey()
	{
		if (!$this->do_exit_on_stop_signal) return false;
		$this->checkMemoryLimit();
		if ($this->readStopKey())
		{
			$this->StopProcessAndExit();
		}
	}

	public function readStopKey()
	{
		return $this->storage->read(self::stop_key);
	}

	private function StopProcessAndExit()
	{
		if (!$this->do_exit_on_stop_signal) return false;
		$this->DelStopKey();
		$msg = PHP_EOL.'process terminated by the STOP signal'.PHP_EOL;
		print $msg;
		exit;
	}

	public function StopWatchedProcess()
	{
		return $this->storage->add(self::stop_key, 1);
	}

	public function DelStopKey()
	{
		return $this->storage->del(self::stop_key);
	}

	public function getMemoryReserve()
	{
		return $this->memory_reserve;
	}

}
