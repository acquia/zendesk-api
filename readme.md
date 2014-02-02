Zendesk API
===========

[![Build Status](https://travis-ci.org/psynaptic/zendesk-api.png?branch=master)](https://travis-ci.org/psynaptic/zendesk-api)

This package is a PHP interface to the Zendesk REST API.

Installation
------------

Run the following commands to install the required dependencies:

```bash
cd <project root>
composer install --no-dev
```

Usage
-----

```php
require 'vendor/autoload.php';
use Acquia\Zendesk\ZendeskApi;

$zendesk = new ZendeskApi('subdomain', 'username', 'api_key');
print_r($zendesk->request('GET', 'tickets'));
```

Running the tests
-----------------

The following commands can be used to run the test suite locally:

```bash
cd <project root>
composer update
phpunit
```

Using `composer update` without the `--no-dev` flag will download the phpunit
dependency.

