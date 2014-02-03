<?php

use Acquia\Zendesk\ZendeskApi;
use Acquia\Zendesk\MissingCredentialsException;

class ZendeskUnitTest extends PHPUnit_Framework_TestCase {

  protected $zendesk;

  public function setUp() {
    $this->zendesk = new ZendeskApi('username', 'password', 'api_key');
  }

  public function tearDown() {
    $this->zendesk = null;
  }

  /**
   * Test instantiation with a missing subdomain parameter.
   *
   * @expectedException Acquia\Zendesk\MissingCredentialsException
   */
  public function testMissingSubdomain() {
    new ZendeskApi(null, 'password', 'api_key');
  }

  /**
   * Test instantiation with a missing password parameter.
   *
   * @expectedException Acquia\Zendesk\MissingCredentialsException
   */
  public function testMissingPassword() {
    new ZendeskApi('username', null, 'api_key');
  }

  /**
   * Test instantiation with a missing api_key parameter.
   *
   * @expectedException Acquia\Zendesk\MissingCredentialsException
   */
  public function testMissingApiKey() {
    new ZendeskApi('username', 'password', null);
  }

  /**
   * Tests buildRequestUrl() with an empty query value.
   */
  public function testBuildRequestUrlEmptyQuery() {
    $actual = $this->zendesk->buildRequestUrl('resource', array('query' => null));
    $expected = 'https://username.zendesk.com/api/v2/resource.json';
    $this->assertEquals($actual, $expected);
  }

  /**
   * Tests buildRequestUrl() with a literal string query value.
   */
  public function testBuildRequestUrlStringQuery() {
    $query = http_build_query(array('k1' => 'v1', 'k2' => 'v2'));
    $actual = $this->zendesk->buildRequestUrl('resource', array('query' => $query));
    $expected = 'https://username.zendesk.com/api/v2/resource.json?k1=v1&k2=v2';
    $this->assertEquals($actual, $expected);
  }

  /**
   * Tests buildRequestUrl() with a query string starting with a question mark.
   */
  public function testBuildRequestUrlPrefixedStringQuery() {
    $query = '?' . http_build_query(array('k1' => 'v1', 'k2' => 'v2'));
    $actual = $this->zendesk->buildRequestUrl('resource', array('query' => $query));
    $expected = 'https://username.zendesk.com/api/v2/resource.json?k1=v1&k2=v2';
    $this->assertEquals($actual, $expected);
  }

  /**
   * Tests buildRequestUrl() with an object of parameters.
   */
  public function testBuildRequestUrlObjectQuery() {
    $query = new stdClass();
    $query->k1 = 'v1';
    $query->k2 = 'v2';
    $actual = $this->zendesk->buildRequestUrl('resource', array('query' => $query));
    $expected = 'https://username.zendesk.com/api/v2/resource.json?k1=v1&k2=v2';
    $this->assertEquals($actual, $expected);
  }

  /**
   * Tests buildRequestUrl() with an array of parameters.
   */
  public function testBuildRequestUrlArrayQuery() {
    $query = array(
      'k1' => 'v1',
      'k2' => 'v2',
    );
    $actual = $this->zendesk->buildRequestUrl('resource', array('query' => $query));
    $expected = 'https://username.zendesk.com/api/v2/resource.json?k1=v1&k2=v2';
    $this->assertEquals($actual, $expected);
  }

  /**
   * Tests formatHeaders().
   */
  public function testFormatHeaders() {
    $headers = array(
      'Content-Type' => 'application/json; charset=utf-8',
      'Accept' => 'application/json',
      'Accept-Encoding' => 'gzip, deflate',
    );
    $actual = $this->zendesk->formatHeaders($headers);
    $expected = array(
      'Content-Type: application/json; charset=utf-8',
      'Accept: application/json',
      'Accept-Encoding: gzip, deflate',
    );
    $this->assertEquals($actual, $expected);
  }

}

