<?php

namespace Acquia\Zendesk;

use Acquia\Zendesk\MissingCredentialsException;
use Acquia\Zendesk\CurlErrorException;
use Acquia\Zendesk\ClientErrorException;
use Acquia\Zendesk\ServerErrorException;

class ZendeskApi {

  const ENDPOINT_PATTERN = 'https://%s.zendesk.com/api/v2';

  protected $subdomain;
  protected $username;
  protected $api_key;

  /**
   * Initialize object requirements.
   *
   * @param string $subdomain
   *   The subdomain used to identify the account in Zendesk.
   * @param string $username
   *   The account username to use to authenticate with Zendesk.
   * @param string $api_key
   *   The account API key to use to authenticate with Zendesk.
   *
   * @throws MissingCredentialsException
   *   If any of the required Zendesk credentials are missing.
   */
  public function __construct($subdomain, $username, $api_key) {
    if (empty($subdomain) || empty($username) || empty($api_key)) {
      throw new MissingCredentialsException('Missing Zendesk API credentials.');
    }
    $this->subdomain = $subdomain;
    $this->username = $username;
    $this->api_key = $api_key;
  }

  /**
   * Makes an HTTP request to the API.
   *
   * @param string $method
   *   The HTTP method e.g. GET.
   * @param string $resource
   *   The resource URI.
   * @param array $headers
   *   An array of additional HTTP headers.
   * @param mixed $body
   *   The body of the request.
   * @param array $options
   *   An array of request options:
   *   - query: An array of query string parameters to append to the URL.
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
   */
  public function request($method, $resource, $headers = array(), $body = NULL, $options = array()) {
    $handle = curl_init();

    curl_setopt($handle, CURLOPT_URL, $this->buildRequestUrl($resource, $options));
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
    curl_setopt($handle, CURLOPT_HTTPHEADER, $this->formatHeaders($headers));
    curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($handle, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($handle, CURLOPT_MAXREDIRS, 10);
    curl_setopt($handle, CURLOPT_USERAGENT, 'ZendeskApi/1.0');
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($handle, CURLOPT_TIMEOUT, 10);
    curl_setopt($handle, CURLOPT_ENCODING, '');

    $response = curl_exec($handle);
    if (curl_errno($handle) > 0) {
      $curl_error = sprintf('Curl error: %s', curl_error($handle));
      curl_close($handle);
      throw new CurlErrorException($curl_error, $curl_errno);
    }

    $data = json_decode($response);
    $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);

    $status_class = floor($status / 100);
    switch ($status_class) {
      case '4':
        throw new ClientErrorException($data->error, $status);
      case '5':
        throw new ServerErrorException($data->error, $status);
    }

    return $data;
  }

  /**
   * Gets the authentication string necessary for making API calls.
   *
   * @return string
   *   The authentication string.
   */
  private function getAuth() {
    return sprintf('%s/token:%s', $this->username, $this->api_key);
  }

  /**
   * Builds the request URL.
   *
   * @param string $resource
   *   The resource URI.
   * @param array $options
   *   An array with a "query" element containing the query string parameters.
   *
   * @return string
   *   The fully-formed URL for the request.
   */
  public function buildRequestUrl($resource, $options = array()) {
    $endpoint = sprintf(self::ENDPOINT_PATTERN, $this->subdomain);
    $query = '';
    if (!empty($options['query'])) {
      if (is_array($options['query']) || is_object($options['query'])) {
        $query = '?' . http_build_query($options['query']);
      }
      else {
        if (strpos($options['query'], '?') === 0) {
          $query = $options['query'];
        }
        else {
          $query = '?' . $options['query'];
        }
      }
    }
    return sprintf('%s/%s.json%s', $endpoint, $resource, $query);
  }

  /**
   * Format the HTTP headers in the format curl requires.
   *
   * @param array $headers
   *   An associative array of HTTP headers, keyed by header field name with the
   *   header field value as the value.
   *
   * @return array
   *   An indexed array of individual fully-formed HTTP headers, as expected by
   *   curl's CURLOPT_HTTPHEADER option.
   */
  public function formatHeaders($headers) {
    $formatted = array();
    foreach ($headers as $name => $value) {
      $formatted[] = sprintf('%s: %s', $name, $value);
    }
    return $formatted;
  }

}

