<?php

namespace Acquia\Zendesk;

class TooManyRequestsException extends \RuntimeException {

  protected $retry_after;

  public function setRetryAfter($retry_after) {
    $this->retry_after = $retry_after;
  }

  public function getRetryAfter() {
    return $this->retry_after;
  }

}

