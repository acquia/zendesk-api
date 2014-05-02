<?php

namespace Acquia\Zendesk;

use Acquia\Zendesk\ZendeskRequest;
use Acquia\Zendesk\MissingCredentialsException;
use Acquia\Zendesk\CurlErrorException;
use Acquia\Zendesk\TooManyRequestsException;
use Acquia\Zendesk\ClientErrorException;
use Acquia\Zendesk\ServerErrorException;

class ZendeskApi {

  protected $client;

  /**
   * Initializes the Zendesk API service.
   *
   * @param string $subdomain
   *   The subdomain used to identify the account in Zendesk.
   * @param string $username
   *   The account username to use to authenticate with Zendesk.
   * @param string $api_key
   *   The account API key to use to authenticate with Zendesk.
   * @param ZendeskRequest $client
   *   The HTTP client object responsible for making HTTP requests.
   *
   * @throws MissingCredentialsException
   *   If any of the required Zendesk credentials are missing.
   */
  public function __construct($subdomain, $username, $api_key, ZendeskRequest $client = NULL) {
    if (empty($subdomain) || empty($username) || empty($api_key)) {
      throw new MissingCredentialsException('Missing Zendesk API credentials.');
    }
    if (empty($client)) {
      $this->client = new ZendeskRequest($subdomain, $username, $api_key);
    }
    else {
      // @todo Pass credentials into the injected client object.
      $this->client = $client;
    }
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
   *   Possible values: "agent", "admin", and/or "end-user".
   * @param int $custom_role_id
   *   A custom role ID to filter the listed users.
   *
   * @return object
   *   The response object of the request containing the defined set of users.
   */
  public function getUsers($roles = array(), $custom_role_id = NULL) {
    $parameters = array(
      'role' => $roles,
      'permission_set' => $custom_role_id,
    );
    return $this->request('GET', 'users', $parameters);
  }

  /**
   * Gets a list of users in a given group.
   *
   * @param int $group_id
   *   The group ID of the users to retrieve.
   * @param array $roles
   *   An array of machine-readable role names to filter the listed users.
   *   Possible values: "agent", "admin", and/or "end-user".
   * @param int $custom_role_id
   *   A custom role ID to filter the listed users.
   *
   * @return object
   *   The response object of the request containing the defined set of users.
   */
  public function getUsersByGroup($group_id, $roles = array(), $custom_role_id = NULL) {
    $parameters = array(
      'role' => $roles,
      'permission_set' => $custom_role_id,
    );
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
   * Ensure a user exists by creating it if necessary.
   *
   * This method will overwrite an existing user entity's properties by those
   * in the provided user entity.
   *
   * @param array|object $user
   *   The user information for the user to be created or modified. See the
   *   createUser method documentation for further details.
   *
   * @return object
   *   The created or modified user object.
   */
  public function ensureUser($user) {
    if (is_array($user)) {
      $user = (object) $user;
    }

    // Search for an existing user by email.
    $result = $this->getUsersSearch('email:' . $user->email);

    // If the user exists, modify the user based on the provided user entity.
    if (!empty($result->users[0])) {
      $existing_user = $result->users[0];
      $composite_user = array_merge($existing_user, $user);
      $this->modifyUser($existing_user->id, $composite_user);
    }
    else {
      // Otherwise, if the user does not exist, just go ahead and create the
      // user.
      $this->createUser($user);
    }
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
   * Gets lists of tickets, optionally associated with given ticket IDs.
   *
   * This method may not return all tickets due to ticket archiving.
   *
   * @param array $ticket_ids
   *   An array of ticket IDs.
   *
   * @return object
   *   An object of tickets in chronological order.
   */
  public function getTickets($ticket_ids = NULL) {
    if (!empty($ticket_ids) && is_array($ticket_ids)) {
      $data = $this->request('GET', 'tickets/show_many', array('ids' => implode(',', $ticket_ids)));
    }
    else {
      $data = $this->request('GET', 'tickets');
    }
    return $data;
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
   * Creates a ticket.
   *
   * @param array|object $ticket
   *   The ticket information for the ticket to be created.
   *   Example properties:
   *   - requester.name: The name of the requester e.g. "John Doe".
   *   - requester.email: The email address of the requester.
   *   - subject (required): The subject of the ticket.
   *   - comment.body (required): The body of the ticket.
   *   - type: The type of the ticket. Possible values: "problem", "incident",
   *     "question", and "task".
   *   - priority: The priority of the ticket. Possible values: "low", "normal",
   *     "high", and "urgent".
   *   - custom_fields: An array of the custom fields of the ticket.
   *
   * @return object
   *   The created ticket object.
   */
  public function createTicket($ticket) {
    $data = $this->request('POST', 'tickets', array(), array('ticket' => $ticket));
    return $data->ticket;
  }

  /**
   * Modifies a ticket.
   *
   * @param int $ticket_id
   *   The ID of the ticket to modify.
   * @param array|object $ticket_data
   *   The ticket data to modify.
   *
   * @return object
   *   The response object of the request.
   */
  public function modifyTicket($ticket_id, $ticket_data) {
    $ticket = array('ticket' => $ticket_data);
    $data = $this->request('PUT', "tickets/${ticket_id}", $ticket);
    return $data;
  }

  /**
   * Deletes a ticket.
   *
   * @param int $ticket_id
   *   The ID of the ticket to delete.
   *
   * @return bool
   *   Whether or not the delete request was successful.
   */
  public function deleteTicket($ticket_id) {
    $data = $this->request('DELETE', "tickets/${ticket_id}");
    $headers = $this->client->getResponseHeaders();
    return $headers['Status'] === '200 OK';
  }

  /**
   * Creates an upload.
   *
   * @param string $filename
   *   The name of the file.
   * @param string $filepath
   *   The path to the file.
   * @param string $token
   *   An optional token to use to identify the upload.
   *
   * @return object
   *   The response object of the request.
   */
  public function createUpload($filename, $filepath, $token = NULL) {
    $body = file_get_contents($filepath);
    $parameters = array(
      'filename' => $filename,
      'token' => $token,
    );
    $headers = array(
      // @todo Get the proper mime type of the file.
      'Content-Type' => 'application/binary',
    );
    // @todo Test the return value.
    return $this->request('POST', 'uploads', $parameters, $body, $headers);
  }

  /**
   * Deletes an upload.
   *
   * @param string $token
   *   The token of the upload to delete.
   *
   * @return object
   *   The response object of the request.
   */
  public function deleteUpload($token) {
    // @todo Test the return value.
    return $this->request('DELETE', "uploads/${token}");
  }

  /**
   * Gets an attachment.
   *
   * @param int $attachment_id
   *   The ID of the attachment to retrieve.
   *
   * @return object
   *   The attachment object.
   */
  public function getAttachment($attachment_id) {
    // @todo Test the return value.
    $data = $this->request('GET', "attachments/${attachment_id}");
    return $data->attachment;
  }

  /**
   * Deletes an attachment.
   *
   * @param int $attachment_id
   *   The ID of the attachment to delete.
   *
   * @return object
   *   The attachment object.
   */
  public function deleteAttachment($attachment_id) {
    // @todo Test the return value.
    $data = $this->request('DELETE', "attachments/${attachment_id}");
    return $data->attachment;
  }

  /**
   * Makes a request via ZendeskRequest.
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
   */
  public function request($method, $resource, $parameters = array(), $body = NULL, $headers = array(), $options = array()) {
    return $this->client->request($method, $resource, $parameters, $body, $headers, $options);
  }

}

