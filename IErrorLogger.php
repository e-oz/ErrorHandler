<?php
namespace Jamm\ErrorHandler;
interface IErrorLogger
{
	public function WriteError(Error $Error);

	public function getErrors();

	public function getNextError();

	public function setErrorsCountLimit($errors_count_limit);

	public function setLogTtl($log_ttl);
}
