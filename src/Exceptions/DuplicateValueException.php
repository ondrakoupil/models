<?php

namespace OndraKoupil\Models\Exceptions;

class DuplicateValueException extends \RuntimeException {
	function __construct($message, $previous=null) {
		parent::__construct($message, 1, $previous);
	}
}