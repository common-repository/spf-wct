<?php
namespace SPF_WCT;
use Exception;

class EmptyApiKeyException extends Exception{

	public function __construct()
	{
		parent::__construct('api keyに空が設定されました',1);
	}
}
?>