<?php
namespace Jamm\ErrorHandler;

class WatchPointsPrinter
{
	private $new_line = "\n";
	private $point_name_prefix = 'Point: ';
	private $point_memory_prefix = 'Memory: ';

	public function setNewLine($new_line)
	{
		$this->new_line = $new_line;
	}

	public function setPointMemoryPrefix($point_memory_prefix = 'Memory: ')
	{
		$this->point_memory_prefix = $point_memory_prefix;
	}

	public function setPointNamePrefix($point_name_prefix = 'Point: ')
	{
		$this->point_name_prefix = $point_name_prefix;
	}

	public function printCurrentPoint(Watcher $Watcher)
	{
		$point = $Watcher->getCurrentPoint();
		if (!empty($point))
		{
			$this->printPoint($point, $Watcher);
		}
	}

	protected function printPoint($point, Watcher $Watcher)
	{
		print $this->point_name_prefix.$point[$Watcher::point_name].$this->new_line;
		if ($point[$Watcher::point_memory] > 1048576)
		{
			print $this->point_memory_prefix.round($point[$Watcher::point_memory]/1048576, 2).' Mb'.$this->new_line;
		}
		else
		{
			print $this->point_memory_prefix.round($point[$Watcher::point_memory]/1024, 2).' Kb'.$this->new_line;
		}
		print date('d.m H:i:s', $point[$Watcher::point_time]).$this->new_line;
		print $this->new_line;
	}

	public function printLine(Watcher $Watcher, $length = 0)
	{
		$points = $Watcher->getPointsArray();
		if (empty($points)) return false;
		$i = 0;
		foreach ($points as $point)
		{
			$i++;
			if ($length > 0 and $i > $length) break;
			$this->printPoint($point, $Watcher);
		}
	}

	public function getGDImageOfMemoryLine(Watcher $Watcher, $width, $height)
	{
		$points = $Watcher->getPointsArray();
		if (empty($points)) return false;
		
		$max_y        = $height;
		$max_x        = $width;
		$img          = imagecreatetruecolor($max_x, $max_y);
		$point_color  = imagecolorallocate($img, 0, 0, 255);
		$line_color   = imagecolorallocate($img, 0, 255, 0);
		$string_color = imagecolorallocate($img, 0, 0, 0);
		$white        = imagecolorallocate($img, 255, 255, 255);
		imagefill($img, 10, 10, $white);
		$x      = 0;
		$y      = 0;
		$x_step = intval(($max_x)/100);
		foreach ($points as $point)
		{
			$value = round($point[$Watcher::point_memory]/1048576, 2); //1048576 = 1Mb
			$new_x = $x+$x_step;
			$new_y = $value*2;
			imageline($img, $x+1, $max_y-$y, $new_x, $max_y-$new_y, $line_color);
			$x = $new_x;
			$y = $new_y;
			imagesetpixel($img, $x, $max_y-$y, $point_color);
			imagestringup($img, 1, $x, $max_y-$y-5, $point[$Watcher::point_name], $string_color);
			imagestringup($img, 1, $x, $max_y-$y+30, $value, $string_color);
		}
		return $img;
	}
}
