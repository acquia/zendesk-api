<?php

namespace Acquia\Zendesk;

use Acquia\Zendesk\CurlErrorException;
use Acquia\Zendesk\ClientErrorException;
use Acquia\Zendesk\TooManyRequestsException;
use Acquia\Zendesk\ServerErrorException;

class ZendeskRequest {

  const ENDPOINT_PATTERN = 'https://%s.zendesk.com/api/v2';

  protected $subdomain;
  protected $username;
  protected $api_key;
  protected $headers;

  /**
   * Initializes a Zendesk Request object.
   *
   * @param string $subdomain
   *   The subdomain used to identify the account in Zendesk.
   * @param string $username
   *   The account username to use to authenticate with Zendesk.
   * @param string $api_key
   *   The account API key to use to authenticate with Zendesk.
   */
  public function __construct($subdomain, $username, $api_key) {
    $this->subdomain = $subdomain;
    $this->username = $username;
    $this->api_key = $api_key;
    $this->endpoint = sprintf(self::ENDPOINT_PATTERN, $subdomain);
  }

  /**
   * Builds the request URL.
   *
   * @param string $resource
   *   The resource URI.
   * @param array|object|string $parameters
   *   An array, object, or string representing the query string parameters.
   *
   * @return string
   *   The fully-formed URL for the request.
   */
  public function buildRequestUrl($resource, $parameters = array()) {
    $query_string = '';
    if (!empty($parameters)) {
      if (is_array($parameters) || is_object($parameters)) {
        // Make sure http_build_query uses a plain ampersand character as the
        // query argument separator (rather than the HTML entity equivalent).
        $http_query = http_build_query($parameters, '', '&');
        // Unfortunately, http_build_query adds numerical indexes to arrays so
        // we have to strip those out. If an equals sign exists in any string
        // values, it will already have been encoded (as %3D) so using one in
        // the pattern ensures that the replacement is accurate.
        $query_string = '?' . preg_replace('/%5B[0-9]+%5D=/U', '%5B%5D=', $http_query);
      }
      else {
        if (strpos($parameters, '?') === 0) {
          $query_string = $parameters;
        }
        else {
          $query_string = '?' . $parameters;
        }
      }
    }
    return sprintf('%s/%s.json%s', $this->endpoint, $resource, $query_string);
  }

  /**
   * Formats the HTTP headers in the format curl requires.
   *
   * @param array $headers
   *   An associative array of HTTP headers, keyed by header field name with the
   *   header field value as the value.
   *
   * @return array
   *   An indexed array of individual fully-formed HTTP headers, as expected by
   *   curl's CURLOPT_HTTPHEADER option.
   */
  public function formatRequestHeaders($headers) {
    $formatted = array();
    foreach ($headers as $name => $value) {
      $formatted[] = sprintf('%s: %s', $name, $value);
    }
    return $formatted;
  }

  /**
   * Parses the response headers to an array.
   *
   * @param string $response
   *   The raw response to parse parse the headers from.
   *
   * @return array
   *   An array of response headers.
   */
  public function parseResponseHeaders($response, $header_length = NULL) {
    if (empty($header_length)) {
      $header_length = strpos($response, "\r\n\r\n");
    }
    $header = substr($response, 0, $header_length);

    // Normalize line-endings.
    $header = str_replace(array("\r\n", "\r"), "\n", $header);

    $headers = array();
    foreach (explode("\n", $header) as $header_line) {
      if ($delimiter_position = strpos($header_line, ':')) {
        $header_name = trim(substr($header_line, 0, $delimiter_position));
        $header_value = trim(substr($header_line, $delimiter_position + 1));
        $headers[$header_name] = $header_value;
      }
    }
    return $headers;
  }

  /**
   * Gets the response headers associated with the previous request.
   */
  public function getResponseHeaders() {
    return $this->headers;
  }

  /**
   * Gets the value of a given response header by name.
   *
   * @param string $name
   *   The name of the response header.
   *
   * @return string
   *   The response header value.
   */
  public function getResponseHeader($name) {
    if (!empty($this->headers[$name])) {
      return $this->headers[$name];
    }
    return FALSE;
  }

  /**
   * Gets the authentication string necessary for making API calls.
   *
   * @return string
   *   The authentication string.
   */
  protected function getAuth() {
    return sprintf('%s/token:%s', $this->username, $this->api_key);
  }

  /**
   * Makes an HTTP request to the API.
   *
   * @param string $method
   *   The HTTP method e.g. GET.
   * @param string $resource
   *   The resource URI.
   * @param array $parameters
   *   An array of request parameters to generate the URL query string for the
   *   request.
   * @param array $headers
   *   An array of additional HTTP headers.
   * @param mixed $body
   *   The body of the request.
   * @param array $options
   *   An array of request options.
   *
   * @return object
   *   The response object.
   *
   * @throws CurlErrorException
   *   If an error occurred with the curl call.
   * @throws ClientErrorException
   *   If a client error was received from the API.
   * @throws ServerErrorException
   *   If a server error was received from the API.
   * @throws TooManyRequestsException
   *   If the request triggered Zendesk's rate limiting feature. See the
   *   custom exception class for how to retrieve the value of the Retry-After
   *   header.
   */
  public function request($method, $resource, $parameters = array(), $body = NULL, $headers = array(), $options = array()) {
    $handle = curl_init();

    curl_setopt($handle, CURLOPT_URL, $this->buildRequestUrl($resource, $parameters));
    curl_setopt($handle, CURLOPT_USERPWD, $this->getAuth());
    if (!empty($body)) {
      if (!is_string($body)) {
        $body = json_encode($body);
      }
      curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
      $headers['Content-Length'] = strlen($body);
    }
    $headers['Accept'] = 'application/json';
    if (empty($headers['Content-Type'])) {
      $headers['Content-Type'] = 'application/json; charset=utf-8';
    }
    curl_setopt($handle, CURLOPT_HTTPHEADER, $this->formatRequestHeaders($headers));
    curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($handle, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($handle, CURLOPT_MAXREDIRS, 10);
    curl_setopt($handle, CURLOPT_USERAGENT, 'ZendeskApi/1.0');
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($handle, CURLOPT_TIMEOUT, 10);
    curl_setopt($handle, CURLOPT_ENCODING, '');
    curl_setopt($handle, CURLOPT_HEADER, TRUE);

    if ($options['debug']) {
      curl_setopt($handle, CURLOPT_VERBOSE, TRUE);
    }

    $response = curl_exec($handle);
    $curl_errno = curl_errno($handle);
    if ($curl_errno > 0) {
      $curl_error = sprintf('Curl error: %s', curl_error($handle));
      curl_close($handle);
      throw new CurlErrorException($curl_error, $curl_errno);
    }

    $header_length = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    $this->headers = $this->parseResponseHeaders($response, $header_length);
    $data = json_decode(substr($response, $header_length));
    $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);

    $status_class = floor($status / 100);
    if ($status_class >= 4) {
      if (!empty($data->error)) {
        $error_message = sprintf('%s: %s', $status, $data->error);
      }
      else {
        $error_message = sprintf('%s: %s', $status, $data);
      }
    }
    switch ($status_class) {
      case '4':
        if ($status === 429) {
          // Handle rate limiting by throwing a custom exception. Set the
          // Retry-After response header as an instance variable so client code
          // may react appropriately.
          $exception = new TooManyRequestsException('The rate limit has been reached.');
          $exception->setRetryAfter($this->getResponseHeader('Retry-After'));
          throw $exception;
        }
        throw new ClientErrorException($error_message, $status);
      case '5':
        throw new ServerErrorException($error_message, $status);
    }

    return $data;
  }

}

