<?php
namespace Jamm\ErrorHandler;

interface IErrorLogger
{
	public function WriteError(Error $Error);

	public function getErrors();

	public function getNextError();
}
