<?php
namespace Jamm\ErrorHandler;

class ToStringConverter
{
	private $max_arr_key_length = 300;

	public function getStringFrom($mixed_value)
	{
		if (is_scalar($mixed_value))
		{
			return $mixed_value;
		}
		if (is_object($mixed_value))
		{
			$mixed_value = $this->getArrayOfObjectValues($mixed_value);
		}
		if (is_array($mixed_value))
		{
			return $this->getStringFromArray($mixed_value);
		}
		return (string)$mixed_value;
	}

	private function getArrayOfObjectValues($object)
	{
		$result_array = array();
		try
		{
			$reflection = new \ReflectionClass($object);
			$properties = $reflection->getProperties();
			foreach ($properties as $property)
			{
				$property->setAccessible(true);
				$value = $property->getValue($object);
				if (is_object($value))
				{
					$value = $this->getArrayOfObjectValues($value);
				}
				$result_array[$property->getName()] = $value;
				if (!$property->isPublic()) $property->setAccessible(false);
			}
		}
		catch (\ReflectionException $exception)
		{
			trigger_error($exception->getMessage(), E_USER_WARNING);
			return false;
		}

		return $result_array;
	}

	private function getStringFromArray(array $array)
	{
		if (empty($array)) return '()';
		foreach ($array as $key=> $value)
		{
			$new_value = $this->getStringFrom($value);
			if (strlen($new_value) > $this->max_arr_key_length)
			{
				$new_value = '>>'.substr($new_value, 0, $this->max_arr_key_length-7).'...>>';
			}
			$array[$key] = $new_value;
		}
		return print_r($array, 1);
	}

	public function setMaxArrKeyLength($max_arr_key_length)
	{
		$this->max_arr_key_length = $max_arr_key_length;
	}
}
