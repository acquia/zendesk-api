<?php

use Acquia\Zendesk\ZendeskRequest;
use Acquia\Zendesk\ZendeskApi;
use Acquia\Zendesk\MissingCredentialsException;

class ZendeskUnitTest extends PHPUnit_Framework_TestCase {

  protected $client;

  public function setUp() {
    $this->client = new ZendeskRequest('username', 'password', 'api_key');
  }

  public function tearDown() {
    $this->client = null;
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
    $actual = $this->client->buildRequestUrl('resource');
    $expected = 'https://username.zendesk.com/api/v2/resource.json';
    $this->assertEquals($actual, $expected);
  }

  /**
   * Tests buildRequestUrl() with a literal string query value.
   */
  public function testBuildRequestUrlStringQuery() {
    $query_string = http_build_query(array('k1' => 'v1', 'k2' => 'v2'));
    $actual = $this->client->buildRequestUrl('resource', $query_string);
    $expected = 'https://username.zendesk.com/api/v2/resource.json?k1=v1&k2=v2';
    $this->assertEquals($actual, $expected);
  }

  /**
   * Tests buildRequestUrl() with a query string starting with a question mark.
   */
  public function testBuildRequestUrlPrefixedStringQuery() {
    $query_string = '?' . http_build_query(array('k1' => 'v1', 'k2' => 'v2'));
    $actual = $this->client->buildRequestUrl('resource', $query_string);
    $expected = 'https://username.zendesk.com/api/v2/resource.json?k1=v1&k2=v2';
    $this->assertEquals($actual, $expected);
  }

  /**
   * Tests buildRequestUrl() with an object of parameters.
   */
  public function testBuildRequestUrlObjectQuery() {
    $parameters = new stdClass();
    $parameters->k1 = 'v1';
    $parameters->k2 = 'v2';
    $actual = $this->client->buildRequestUrl('resource', $parameters);
    $expected = 'https://username.zendesk.com/api/v2/resource.json?k1=v1&k2=v2';
    $this->assertEquals($actual, $expected);
  }

  /**
   * Tests buildRequestUrl() with an array of parameters.
   */
  public function testBuildRequestUrlArrayQuery() {
    $parameters = array(
      'k1' => 'v1',
      'k2' => 'v2',
    );
    $actual = $this->client->buildRequestUrl('resource', $parameters);
    $expected = 'https://username.zendesk.com/api/v2/resource.json?k1=v1&k2=v2';
    $this->assertEquals($actual, $expected);
  }

  /**
   * Tests formatRequestHeaders().
   */
  public function testFormatRequestHeaders() {
    $headers = array(
      'Content-Type' => 'application/json; charset=utf-8',
      'Accept' => 'application/json',
      'Accept-Encoding' => 'gzip, deflate',
    );
    $actual = $this->client->formatRequestHeaders($headers);
    $expected = array(
      'Content-Type: application/json; charset=utf-8',
      'Accept: application/json',
      'Accept-Encoding: gzip, deflate',
    );
    $this->assertEquals($actual, $expected);
  }

  /**
   * Tests parseResponseHeaders() with Unix-style line-endings.
   */
  public function testParseResponseHeadersUnix() {
    $header = "HTTP/1.1 429\nServer: nginx/1.4.4\nDate: Mon, 03 Feb 2014 04:25:31 GMT\nContent-Type: application/json; charset=UTF-8\nContent-Length: 76\nConnection: keep-alive\nStatus: 429\nRetry-After: 59\n";
    $headers = $this->client->parseResponseHeaders($header, strlen($header));
    $actual = $headers['Retry-After'];
    $this->assertEquals($actual, '59');
  }

  /**
   * Tests parseResponseHeaders() with Mac-style line-endings.
   */
  public function testParseResponseHeadersMac() {
    $header = "HTTP/1.1 429\rServer: nginx/1.4.4\rDate: Mon, 03 Feb 2014 04:25:31 GMT\rContent-Type: application/json; charset=UTF-8\rContent-Length: 76\rConnection: keep-alive\rStatus: 429\rRetry-After: 59\r";
    $headers = $this->client->parseResponseHeaders($header, strlen($header));
    $actual = $headers['Retry-After'];
    $this->assertEquals($actual, '59');
  }

  /**
   * Tests parseResponseHeaders() with Windows-style line-endings.
   */
  public function testParseResponseHeadersWindows() {
    $header = "HTTP/1.1 429\r\nServer: nginx/1.4.4\r\nDate: Mon, 03 Feb 2014 04:25:31 GMT\r\nContent-Type: application/json; charset=UTF-8\r\nContent-Length: 76\r\nConnection: keep-alive\r\nStatus: 429\r\nRetry-After: 59\r\n";
    $headers = $this->client->parseResponseHeaders($header, strlen($header));
    $actual = $headers['Retry-After'];
    $this->assertEquals($actual, '59');
  }

}

