<?php
namespace Jamm\ErrorHandler;
class Error extends \Jamm\Tester\Error
{
	protected $request;

	public function getRequest()
	{
		return $this->request;
	}

	public function setRequest($request)
	{
		$this->request = $request;
	}
}
