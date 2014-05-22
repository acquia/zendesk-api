<?php

/**
 * @file
 * Contains \Acquia\Zendesk\ClientErrorException.
 */

namespace Acquia\Zendesk;

class ClientErrorException extends \RuntimeException {

  /**
   * The response data that contains a description of the errors.
   *
   * @var object|string
   */
  private $errors;

  /**
   * Creates a new ClientErrorException instance.
   *
   * @param string $message
   *   The error message.
   * @param int $code
   *   The HTTP response code.
   * @param object|string $errors
   *   The error data from the response.
   */
  public function __construct($message, $code = 400, $errors = NULL) {
    $this->errors = $errors;
    parent::__construct($message, $code);
  }

  /**
   * Gets the response data containing a description of the errors.
   *
   * @return object|string
   *   An object or string, whichever was returned from the API call.
   */
  public function getErrors() {
    return $this->errors;
  }

}
