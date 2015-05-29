<?php

namespace OndraKoupil\Models\Exceptions;

class DatabaseException extends \RuntimeException {
	function __construct($message, $previous=null) {
		parent::__construct($message, 1, $previous);
	}
}