<?php

namespace Acquia\Zendesk;

class ClientErrorException extends \RuntimeException {

  private $errors;

  public function __construct($message, $code = 400, $errors = NULL) {
    $this->errors = $errors;
    parent::__construct($message, $code);
  }

  public function getErrors() {
    return $this->errors;
  }

}
