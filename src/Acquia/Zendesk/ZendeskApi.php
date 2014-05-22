<?php

/**
 * @file
 * Contains \Acquia\Zendesk\ZendeskApi.
 */

namespace Acquia\Zendesk;

use Acquia\Zendesk\ZendeskRequest;
use Acquia\Zendesk\MissingCredentialsException;

class ZendeskApi {

  protected $client;
  private $debug = FALSE;

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
   * @throws \Acquia\Zendesk\MissingCredentialsException
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
   * Enables debugging mode.
   */
  public function enableDebug() {
    $this->debug = TRUE;
  }

  /**
   * Gets the status of a job.
   *
   * @param int $job_id
   *   The ID of the job to retrieve.
   *
   * @return object
   *   The job status object from the response.
   */
  public function getJobStatus($job_id) {
    $data = $this->request('GET', "job_statuses/${job_id}");
    return $data->job_status;
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
  public function getUsers(array $roles = array(), $custom_role_id = NULL) {
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
  public function getUsersByGroup($group_id, array $roles = array(), $custom_role_id = NULL) {
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
   *   - verified (bool): Whether the account should be considered already
   *     verified.
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
   * If the given user entity contains an organization_id field, an organization
   * membership will be created (meaning the existing user will be assigned into
   * the specified organization).
   *
   * @param array|object $user
   *   The user information for the user to be created or modified. See the
   *   createUser method documentation for further details.
   *
   * @throws \Acquia\Zendesk\ClientErrorException
   *   If a ClientErrorException was caught but wasn't a 422 Unprocessible
   *   Entity error.
   */
  public function ensureUserExists($user) {
    if (is_array($user)) {
      $user = (object) $user;
    }

    // Search for an existing user by email.
    $result = $this->getUsersSearch('email:' . $user->email);
    if (!empty($result->users[0])) {
      $existing_user = $result->users[0];

      // If an organization_id has been specified in the given user entity
      // object, handle the assignment of the user into the organization using
      // the membership API.
      if (!empty($user->organization_id)) {
        try {
          $this->createMembership($existing_user->id, $user->organization_id);
        }
        catch (ClientErrorException $e) {
          // If the membership already exists, a 422 Unprocessible Entity is
          // returned. We could go further and string-match the returned error
          // message but we already know for sure that the user ID is correct
          // and in all likelihood the client error was returned because the
          // user is already a member of the specified organization.
          if ($e->getCode() !== 422) {
            // If this isn't what we're looking for, re-throw the exception.
            throw new ClientErrorException($e->getMessage(), $e->getCode(), $e->getErrors());
          }
        }
      }
    }
    else {
      // If the user entity doesn't exist, create it.
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
   * If attempting to delete a non-existent record, it is the caller's
   * responsibility to catch the exception. The HTTP response code and message
   * are stored in the exception instance.
   *
   * @param int $user_id
   *   The ID of the user to delete.
   *
   * @throws \Acquia\Zendesk\RecordNotFoundException
   *   If attempting to delete a non-existent record.
   */
  public function deleteUser($user_id) {
    $this->request('DELETE', "users/${user_id}");
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
   *   The response object of the request containing the list of groups.
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
   * If attempting to delete a non-existent record, it is the caller's
   * responsibility to catch the exception. The HTTP response code and message
   * are stored in the exception instance.
   *
   * @param int $group_id
   *    The ID of the group to delete.
   *
   * @throws \Acquia\Zendesk\RecordNotFoundException
   *   If attempting to delete a non-existent record.
   */
  public function deleteGroup($group_id) {
    $this->request('DELETE', "groups/${group_id}");
  }

  /**
   * Gets an organization.
   *
   * @param int $organization_id
   *   The organization ID to retrieve.
   *
   * @return object
   *   The organization object from the response.
   */
  public function getOrganization($organization_id) {
    $data = $this->request('GET', "organizations/${organization_id}");
    return $data->organization;
  }

  /**
   * Gets all organizations.
   *
   * @param int $page
   *   The page of results to fetch.
   *
   * @return object
   *   The response object of the request.
   */
  public function getOrganizations($page = 1, $limit = 100) {
    return $this->requestCollection('organizations', array(), $page, $limit);
  }

  /**
   * Gets a user's organizations.
   *
   * @param int $user_id
   *   The user ID for which to retrieve the organizations.
   * @param int $page
   *   The page of results to fetch.
   * @param int $limit
   *   The number of items to fetch per page.
   *
   * @return object
   *   The response object of the request.
   */
  public function getOrganizationsByUser($user_id, $page = 1, $limit = 100) {
    return $this->requestCollection("users/${user_id}/organizations", array(), $page, $limit);
  }

  /**
   * Autocompletes organizations.
   *
   * @param int $search_term
   *   The search term to autocomplete.
   * @param int $page
   *   The page of results to fetch.
   * @param int $limit
   *   The number of items to fetch per page.
   *
   * @return object
   *   The response object of the request.
   */
  public function autocompleteOrganizations($search_term, $page = 1, $limit = 100) {
    $parameters = array(
      'name' => $search_term,
      'page' => $page,
      'limit' => $limit,
    );
    // This should technically be a GET request since no data is changed on the
    // server but using that returns a 400 Bad Request response.
    return $this->request('POST', 'organizations/autocomplete', $parameters);
  }

  /**
   * Gets an organization's meta data.
   *
   * @param int $organization_id
   *   The organization ID whose meta data to retrieve.
   *
   * @return object
   *   The meta data object from the response.
   */
  public function getOrganizationMetadata($organization_id) {
    $data = $this->request('GET', "organizations/${organization_id}/related");
    return $data->organization_related;
  }

  /**
   * Creates an organization.
   *
   * @param string $name
   *   The name of the organization to create.
   *
   * @return object
   *   The created organization object.
   */
  public function createOrganization($name) {
    $parameters = array(
      'organization' => array(
        'name' => $name,
      ),
    );
    $data = $this->request('POST', 'organizations', $parameters);
    return $data->organization;
  }

  /**
   * Create many organizations.
   *
   * @param string[] $names
   *   The names of the organizations to create.
   *
   * @return object
   *   The job status object from the response.
   */
  public function createOrganizations(array $names) {
    $body = array(
      'organizations' => array(),
    );
    foreach ($names as $name) {
      $body['organizations'][]['name'] = $name;
    }
    $data = $this->request('POST', 'organizations/create_many', array(), $body);
    return $data->job_status;
  }

  /**
   * Updates an organization.
   *
   * @param int $organization_id
   *   The organization ID to update.
   * @param array $organization
   *   The data to update on the organization.
   *
   * @return object
   *   The updated organization object.
   */
  public function updateOrganization($organization_id, array $organization) {
    $body = array(
      'organization' => $organization,
    );
    $data = $this->request('PUT', "organizations/${organization_id}", array(), $body);
    return $data->organization;
  }

  /**
   * Deletes an organization.
   *
   * If attempting to delete a non-existent record, it is the caller's
   * responsibility to catch the exception. The HTTP response code and message
   * are stored in the exception instance.
   *
   * @param int $organization_id
   *   The organization ID to delete.
   *
   * @throws \Acquia\Zendesk\RecordNotFoundException
   *   If attempting to delete a non-existent record.
   */
  public function deleteOrganization($organization_id) {
    $this->request('DELETE', "organizations/${organization_id}");
  }

  /**
   * Search organizations.
   *
   * @param string $search_term
   *   The search term.
   * @param int $page
   *   The page of results to fetch.
   *
   * @return object
   *   The response object of the request.
   */
  public function searchOrganizations($search_term, $page = 1) {
    $parameters = array(
      'external_id' => $search_term,
      'page' => $page,
    );
    // This doesn't seem to work, despite it being correct as per the beta
    // documentation. Then again, the documentation is inconsistent about the
    // endpoint to use. The "external_id" parameter doesn't seem like the
    // correct name for a search term field.
    $data = $this->request('GET', 'organizations/search', $parameters);
    return $data;
  }

  /**
   * Gets an individual organization membership.
   *
   * @param int $user_id
   *   The user ID on which the membership exists.
   * @param int $membership_id
   *   The ID of the membership to retrieve.
   *
   * @return object
   *   The response object of the request.
   */
  public function getMembership($user_id, $membership_id) {
    $data = $this->request('GET', "users/${user_id}/organization_memberships/${membership_id}");
    return $data->organization_membership;
  }

  /**
   * Gets a list of organization memberships by user ID.
   *
   * @param int $user_id
   *   The user ID on which the memberships exist.
   * @param int $page
   *   The page of results to fetch.
   * @param int $limit
   *   The number of items to fetch per page.
   *
   * @return object
   *   The response object of the request.
   */
  public function getMemberships($user_id, $page = 1, $limit = 100) {
    $resource = "users/${user_id}/organization_memberships";
    return $this->requestCollection($resource, array(), $page, $limit);
  }

  /**
   * Creates an organization membership.
   *
   * @param int $user_id
   *   The user ID on which to create the membership.
   * @param int $organization_id
   *   The organization ID to assign to the user.
   *
   * @return object
   *   The response object of the request.
   */
  public function createMembership($user_id, $organization_id) {
    $body = array(
      'organization_membership' => array(
        'user_id' => $user_id,
        'organization_id' => $organization_id,
      ),
    );
    $data = $this->post("users/${user_id}/organization_memberships", $body);
    return $data->organization_membership;
  }

  /**
   * Deletes an organization membership.
   *
   * If attempting to delete a non-existent record, it is the caller's
   * responsibility to catch the exception. The HTTP response code and message
   * are stored in the exception instance.
   *
   * @param int $user_id
   *   The user ID on which to delete the membership.
   * @param int $membership_id
   *   The ID of the membership to delete.
   *
   * @throws \Acquia\Zendesk\RecordNotFound
   *   If attempting to delete a non-existent record.
   */
  public function deleteMembership($user_id, $membership_id) {
    $this->request('DELETE', "users/${user_id}/organization_memberships/${membership_id}");
  }

  /**
   * Sets the default organization membership for a user.
   *
   * @param int $user_id
   *   The user ID to set the default membership on.
   * @param int $membership_id
   *   The ID of the membership to set as the default.
   *
   * @return object
   *   The organization memberships from the response.
   */
  public function setDefaultMembership($user_id, $membership_id) {
    $data = $this->request('PUT', "users/${user_id}/organization_memberships/${membership_id}/make_default");
    return $data->organization_memberships;
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
  public function getTickets(array $ticket_ids = NULL) {
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
   * If attempting to delete a non-existent record, it is the caller's
   * responsibility to catch the exception. The HTTP response code and message
   * are stored in the exception instance.
   *
   * @param int $ticket_id
   *   The ID of the ticket to delete.
   *
   * @throws \Acquia\Zendesk\RecordNotFoundException
   *   If attempting to delete a non-existent record.
   */
  public function deleteTicket($ticket_id) {
    $this->request('DELETE', "tickets/${ticket_id}");
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
   * If attempting to delete a non-existent record, it is the caller's
   * responsibility to catch the exception. The HTTP response code and message
   * are stored in the exception instance.
   *
   * @param string $token
   *   The token of the upload to delete.
   *
   * @throws \Acquia\Zendesk\RecordNotFoundException
   *   If attempting to delete a non-existent record.
   */
  public function deleteUpload($token) {
    $this->request('DELETE', "uploads/${token}");
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
   * If attempting to delete a non-existent record, it is the caller's
   * responsibility to catch the exception. The HTTP response code and message
   * are stored in the exception instance.
   *
   * @param int $attachment_id
   *   The ID of the attachment to delete.
   *
   * @throws \Acquia\Zendesk\RecordNotFoundException
   *   If attempting to delete a non-existent record.
   */
  public function deleteAttachment($attachment_id) {
    $this->request('DELETE', "attachments/${attachment_id}");
  }

  /**
   * Makes a POST request.
   *
   * @param string $resource
   *   The resource URI.
   * @param mixed $body
   *   The body of the request.
   *
   * @return object
   *   The response object of the request.
   */
  public function post($resource, $body) {
    return $this->request('POST', $resource, array(), $body);
  }

  /**
   * Makes a GET request for a collection.
   *
   * Collections are paged and as such the current page and limit of items per
   * page may be specified.
   *
   * @param string $resource
   *   The resource URI.
   * @param array $parameters
   *   An array of request parameters to generate the URL query string for the
   *   request.
   * @param int $page
   *   The page of results to fetch.
   * @param int $limit
   *   The number of items to fetch per page.
   *
   * @return object
   *   The response object of the request.
   */
  public function requestCollection($resource, array $parameters, $page = 1, $limit = 100) {
    $parameters += array(
      'page' => $page,
      'per_page' => $limit,
    );
    return $this->request('GET', $resource, $parameters);
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
   * @param mixed $body
   *   The body of the request.
   * @param array $headers
   *   An array of additional HTTP headers.
   * @param array $options
   *   An array of request options.
   *
   * @return object
   *   The response object of the request.
   */
  public function request($method, $resource, array $parameters = array(), $body = NULL, array $headers = array(), array $options = array()) {
    if ($this->debug) {
      $options['debug'] = TRUE;
    }
    return $this->client->request($method, $resource, $parameters, $body, $headers, $options);
  }

}
