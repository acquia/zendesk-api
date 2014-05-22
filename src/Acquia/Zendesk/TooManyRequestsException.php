<?php

/**
 * @file
 * Contains \Acquia\Zendesk\TooManyRequestsException.
 */

namespace Acquia\Zendesk;

class TooManyRequestsException extends \RuntimeException {

  /**
   * The number of seconds after which a retry should occur.
   *
   * @var int
   */
  private $retryAfter;

  /**
   * Sets the number of seconds after which a retry should occur.
   *
   * This value is parsed from the Retry-After header of the response.
   *
   * @param int $retry_after
   *   The number of seconds.
   */
  public function setRetryAfter($retry_after) {
    $this->retryAfter = $retry_after;
  }

  /**
   * Gets the number of seconds after which a retry should occur.
   *
   * @return int
   *   The number of seconds.
   */
  public function getRetryAfter() {
    return $this->retryAfter;
  }

}
