<?php

namespace Acquia\Zendesk;

use Acquia\Zendesk\MissingCredentialsException;
use Acquia\Zendesk\CurlErrorException;
use Acquia\Zendesk\TooManyRequestsException;
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
   * Gets a ticket by ticket ID.
   *
   * @param int $ticket_id
   *   The ID of the ticket to retrieve.
   *
   * @return object
   *   The ticket object with ticket values as direct object properties.
   */
  public function getTicket($ticket_id) {
    $data = $this->request('GET', "tickets/${ticket_id}");
    return $data->ticket;
  }

  /**
   * Gets multiple tickets associated with a given list of ticket IDs.
   *
   * @param array $ticket_ids
   *   An array of ticket IDs.
   *
   * @return object
   *   An object of tickets.
   */
  public function getTickets($ticket_ids) {
    $data = $this->request('GET', 'tickets/show_many', array(), NULL, array(
      'query' => array('ids' => implode(',', $ticket_ids))
    ));
    return $data->tickets;
  }

  /**
   * Gets all tickets in chronological order.
   *
   * As pointed out in the Zendesk API documentation, this method may not
   * actually return all of the tickets due to ticket archiving.
   *
   * @return
   *   The response object of the request containing all of the tickets.
   */
  public function getTicketsAll() {
    return $this->request('GET', 'tickets');
  }

  /**
   * Gets lists of tickets via the Zendesk Search API.
   *
   * @param string $search
   *   The search text to be matched or a search string. See the Search
   *   Reference: https://support.zendesk.com/entries/20239737
   * @param string $sort_by
   *   The possible values are "updated_at", "created_at", "priority", "status",
   *   and "ticket_type".
   * @param string $order
   *   One of "relevance", "asc", or "desc". Defaults to "relevance" when no
   *   criteria is specified.
   *
   * @return object
   *   The response object of the request containing tickets matching the
   *   search paramaters.
   */
  public function getTicketsSearch($search, $sort_by = NULL, $order = NULL) {
    $query = array(
      'query' => $search,
      'sort_by' => $sort_by,
      'sort_order' => $order,
    );
    return $this->request('GET', 'search', array(), NULL, array('query' => $query));
  }

  /**
   * Gets tickets associated with a given organization ID.
   *
   * @param int $organization_id
   *   The organization ID to get tickets for.
   *
   * @return object
   *   The response object of the request containing tickets associated with the
   *   given organization ID.
   */
  public function getTicketsOrganization($organization_id) {
    return $this->request('GET', "organizations/${organization_id}/tickets");
  }

  /**
   * Gets tickets associated with a given user ID.
   *
   * @param int $user_id
   *   The user ID to retrieve tickets for.
   * @param string $type
   *   One of "requested" and "ccd".
   *
   * @return object
   *   The response object of the request containing tickets associated with the
   *   given user ID.
   */
  public function getTicketsUser($user_id, $type = 'requested') {
    return $this->request('GET', "users/${user_id}/tickets/${type}");
  }

  /**
   * Gets a short list of the most recent tickets.
   *
   * @return object
   *   The response object containing the most recent tickets.
   */
  public function getTicketsRecent() {
    return $this->request('GET', 'tickets/recent');
  }

  /**
   * Gets lists of tickets via the Incremental Tickets API.
   *
   * The Incremental Tickets API is meant to be a lightweight, but heavily
   * rate-limited endpoint for listing tickets. It provides a way to easily
   * export tickets into an external system, and then poll for udpates.
   *
   * @param int $start_time
   *   The Unix time for when the search results should start. Defaults to 1
   *   year ago.
   */
  public function getTicketsIncremental($start_time = NULL) {
    if (empty($start_time)) {
      $start_time = time() - 86400 * 365;
    }
    return $this->request('GET', 'exports/tickets', array(), NULL, array(
      'query' => array('start_time' => $start_time)));
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
    curl_setopt($handle, CURLOPT_VERBOSE, TRUE);
    curl_setopt($handle, CURLOPT_HEADER, TRUE);

    $response = curl_exec($handle);
    if (curl_errno($handle) > 0) {
      $curl_error = sprintf('Curl error: %s', curl_error($handle));
      curl_close($handle);
      throw new CurlErrorException($curl_error, $curl_errno);
    }

    $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $data = json_decode($body);
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
          $retry_after = $this->parseResponseHeader('Retry-After', $header);
          $exception = new TooManyRequestsException('The rate limit has been reached. Please try again.');
          $exception->setRetryAfter($retry_after);
          throw $exception;
        }
        throw new ClientErrorException($error_message, $status);
      case '5':
        throw new ServerErrorException($error_message, $status);
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

  /**
   * Parses the value of a given response header.
   *
   * @param string $header_name
   *   The name of the response header.
   * @param string $header
   *   The response header string to parse.
   *
   * @return string
   *   The response header value.
   */
  public function parseResponseHeader($header_name, $header) {
    // Normalize line-endings.
    $header = str_replace(array("\r", "\r\n"), "\n", $header);
    foreach (explode("\n", $header) as $header_line) {
      if (preg_match("/^${header_name}: (?P<value>.+)/", $header_line, $matches)) {
        return $matches['value'];
      }
    }
  }
}

