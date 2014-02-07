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
  protected $headers;

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
   * Gets a user by user ID.
   *
   * @param int $user_id
   *   The ID of the user to retrieve.
   *
   * @return object
   *   The user object associated with the given user ID.
   */
  public function getUser($user_id) {
    $data = $this->request('GET', "users/${user_id}");
    return $data->user;
  }

  /**
   * Gets related information for a given user ID.
   *
   * @param int $user_id
   *   The ID of the user to retrieve.
   *
   * @return object
   *   The related information object associated with the given user ID.
   */
  public function getUserInformation($user_id) {
    $data = $this->request('GET', "users/${user_id}/related");
    return $data->user_related;
  }

  /**
   * Gets a list of users, optionally filtered by role.
   *
   * @param array $roles
   *   An array of machine-readable role names to filter the listed users.
   *   Poassible values: "agent", "admin", and/or "end-user".
   * @param int $custom_role_id
   *   A custom role ID to filter the listed users.
   *
   * @return object
   *   The response object of the request containing the defined set of users.
   */
  public function getUsers($roles = array(), $custom_role_id = NULL) {
    $parameters = array(
      'role' => $roles,
      'permission_set' => $role_id,
    );
    return $this->request('GET', 'users', $parameters);
  }

  /**
   * Gets a list of users in a given group.
   *
   * @param int $group_id
   *   The group ID of the users to retrieve.
   *
   * @return object
   *   The response object of the request containing the defined set of users.
   */
  public function getUsersByGroup($group_id) {
    return $this->request('GET', "groups/${group_id}/users", $parameters);
  }

  /**
   * Gets the users in a given organization.
   *
   * @param int $organization_id
   *   The organization ID of the users to retrieve.
   *
   * @return object
   *   The response object of the request containing the defined set of users.
   */
  public function getUsersByOrganization($organization_id) {
    return $this->request('GET', "organizations/${organization_id}/users");
  }

  /**
   * Gets lists of users via the Zendesk Search API.
   *
   * @param string $search
   *   The search text to be matched or a search string. See the Search
   *   Reference: https://support.zendesk.com/entries/20239737
   *
   * @return object
   *   The response object of the request containing users matching the
   *   search parameters.
   */
  public function getUsersSearch($search) {
    return $this->request('GET', 'users/search', array('query' => $search));
  }

  /**
   * Creates a user.
   *
   * @param array|object $user
   *   The user information for the user to be created. Possible properties:
   *   - name (required): The name of the user e.g. "John Doe".
   *   - email (required): The email address of the user.
   *   - verified (bool): Whether the account should be considered already verified
   *     (setting this to true will create a user without sending out a
   *     verification email.
   *   - role: The role of the user.
   *   - identities: An array of identities (email address, Twitter handle,
   *     etc.) with "type" and "value" properties.
   *
   * @return object
   *   The created user object.
   */
  public function createUser($user) {
    $data = $this->request('POST', 'users', array('user' => $user));
    return $data->user;
  }

  /**
   * Modifies a user.
   *
   * @param int $user_id
   *   The ID of the user to modify.
   * @param array|object $user
   *   The user data to modify.
   *
   * @return object
   *   The modified user object.
   */
  public function modifyUser($user_id, $user) {
    $data = $this->request('PUT', "users/${user_id}", array(), array('user' => $user));
    return $data->user;
  }

  /**
   * Deletes a user.
   *
   * @param int $user_id
   *   The ID of the user to delete.
   *
   * @return object
   *   The deleted user object with the "active" field set to false.
   */
  public function deleteUser($user_id) {
    $data = $this->request('DELETE', "users/${user_id}");
    return $data->user;
  }

  /**
   * Gets a group.
   *
   * @param int $group_id
   *   The ID of the group to retrieve.
   *
   * @return object
   *   The group object associated with the given group ID.
   */
  public function getGroup($group_id) {
    $data = $this->request('GET', "groups/${group_id}");
    return $data->group;
  }

  /**
   * Gets a list of groups, optionally filtered by whether they're assignable.
   *
   * @param bool $assignable
   *   Whether or not to list only assignable groups.
   *
   * @return object
   *  The response object of the request containing the list of groups.
   */
  public function getGroups($assignable = FALSE) {
    $resource = 'groups';
    if ($assignable) {
      $resource .= '/assignable';
    }
    return $this->request('GET', $resource);
  }

  /**
   * Gets a list of the given user's groups.
   *
   * @param int $user_id
   *   The ID of the user for which to retrieve the list of groups.
   */
  public function getGroupsByUser($user_id) {
    return $this->request('GET', "users/${user_id}/groups");
  }

  /**
   * Creates a group.
   *
   * @param string $group_name
   *   The name of the group to create.
   *
   * @return object
   *   The response object of the request containing the created group.
   */
  public function createGroup($group_name) {
    $data = $this->request('POST', 'groups', array('group' => array('name' => $group_name)));
    return $data->group;
  }

  /**
   * Modifies a group name.
   *
   * @param int $group_id
   *   The ID of the group to modify.
   * @param string $group_name
   *   The new name of the group.
   *
   * @return object
   *   The response object of the request containing the modified group.
   */
  public function modifyGroupName($group_id, $group_name) {
    $group = array('group' => array('name' => $group_name));
    $data = $this->request('PUT', "groups/${group_id}", NULL, $group);
    return $data->group;
  }

  /**
   * Deletes a group.
   *
   * @param int $group_id
   *    The ID of the group to delete.
   *
   * @return object
   *   The response object of the request containing the deleted group.
   */
  public function deleteGroup($group_id) {
    $data = $this->request('DELETE', "groups/${group_id}");
    return $data;
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
    $data = $this->request('GET', 'tickets/show_many', array('ids' => implode(',', $ticket_ids)));
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
   *   search parameters.
   */
  public function getTicketsSearch($search, $sort_by = NULL, $order = NULL) {
    $parameters = array(
      'query' => $search,
      'sort_by' => $sort_by,
      'sort_order' => $order,
    );
    return $this->request('GET', 'search', $parameters);
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
    return $this->request('GET', 'exports/tickets', array('start_time' => $start_time));
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
    curl_setopt($handle, CURLOPT_VERBOSE, TRUE);
    curl_setopt($handle, CURLOPT_HEADER, TRUE);

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
          $retry_after_seconds = $this->getResponseHeader('Retry-After');
          $exception = new TooManyRequestsException('The rate limit has been reached.');
          $exception->setRetryAfter($retry_after_seconds);
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
   * @param array|object|string $parameters
   *   An array, object, or string representing the query string parameters.
   *
   * @return string
   *   The fully-formed URL for the request.
   */
  public function buildRequestUrl($resource, $parameters = array()) {
    $endpoint = sprintf(self::ENDPOINT_PATTERN, $this->subdomain);
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
    return sprintf('%s/%s.json%s', $endpoint, $resource, $query_string);
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

}

