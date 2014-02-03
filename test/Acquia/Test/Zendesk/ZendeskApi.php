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

  /**
   * Tests parseResponseHeader() with Unix-style line-endings.
   */
  public function testParseResponseHeaderUnix() {
    $header = "HTTP/1.1 429\nServer: nginx/1.4.4\nDate: Mon, 03 Feb 2014 04:25:31 GMT\nContent-Type: application/json; charset=UTF-8\nContent-Length: 76\nConnection: keep-alive\nStatus: 429\nRetry-After: 59\n";
    $actual = $this->zendesk->parseResponseHeader('Retry-After', $header);
    $this->assertEquals($actual, '59');
  }

  /**
   * Tests parseResponseHeader() with Mac-style line-endings.
   */
  public function testParseResponseHeaderMac() {
    $header = "HTTP/1.1 429\rServer: nginx/1.4.4\rDate: Mon, 03 Feb 2014 04:25:31 GMT\rContent-Type: application/json; charset=UTF-8\rContent-Length: 76\rConnection: keep-alive\rStatus: 429\rRetry-After: 59\r";
    $actual = $this->zendesk->parseResponseHeader('Retry-After', $header);
    $this->assertEquals($actual, '59');
  }

  /**
   * Tests parseResponseHeader() with Windows-style line-endings.
   */
  public function testParseResponseHeaderWindows() {
    $header = "HTTP/1.1 429\r\nServer: nginx/1.4.4\r\nDate: Mon, 03 Feb 2014 04:25:31 GMT\r\nContent-Type: application/json; charset=UTF-8\r\nContent-Length: 76\r\nConnection: keep-alive\r\nStatus: 429\r\nRetry-After: 59\r\n";
    $actual = $this->zendesk->parseResponseHeader('Retry-After', $header);
    $this->assertEquals($actual, '59');
  }

}

