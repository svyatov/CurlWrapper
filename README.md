# CurlWrapper

Flexible wrapper class for PHP cURL extension (see [http://php.net/curl](http://php.net/curl) for more information about the libcurl extension for PHP)


## Installation

Click the `Downloads` link above or `git clone git://github.com/Svyatov/CurlWrapper.git`


## Usage

### Initialization

Simply require and initialize the `CurlWrapper` class like so:

```php
<?php
...
require_once 'CurlWrapper.php';

try {
    $curl = new CurlWrapper();
} catch (CurlWrapperException $e) {
    echo $e->getMessage();
}
...
```

### Performing a Request

The CurlWrapper object supports 5 types of requests: HEAD, GET, POST, PUT, and DELETE. You must specify a url to request and optionally specify an associative array or string of variables to send along with it.

```php
<?php
...
$response = $curl->head($url, $params);
$response = $curl->get($url, $params); # The CurlWrapper object will append the array of $params to the $url as a query string
$response = $curl->post($url, $params);
$response = $curl->put($url, $params);
$response = $curl->delete($url, $params);
...
```

To use a custom request methods, you can call the `request` method:

```php
<?php
...
$response = $curl->request('ANY_CUSTOM_REQUEST_TYPE', $url, $params);
...
```

All of the built in request methods like `put` and `get` simply wrap the `request` method. For example, the `post` method is implemented like:

```php
<?php
...
public function post($url, $requestParams = null)
{
    return $this->request($url, 'POST', $requestParams);
}
...
```

Examples:

```php
<?php
...
$response = $curl->get('google.com?q=test');

// The CurlWrapper object will append '&some_variable=some_value' to the url
$response = $curl->get('google.com?q=test', array('some_variable' => 'some_value'));

$response = $curl->post('test.com/posts', array('title' => 'Test', 'body' => 'This is a test'));
...
```

All requests return response as is or throw a CurlWrapperException if an error occurred.


### Cookie Sessions

To maintain a session across requests and cookies support you must set file's name where cookies to store.

```php
<?php
...
$curl->setCookieFile('some_file_name.txt');
...
```

This file must be writeble or the CurlWrapperException will be thrown.


### Basic Configuration Options

You can easily set the referer or user-agent

```php
<?php
...
$curl->setReferer('http://google.com');
$curl->setUserAgent('some user agent string');
...
```

You may even set these headers manually if you wish (see below)


### Setting Custom Headers

You can set custom headers to send with the request

```php
<?php
...
$curl->addHeader('Host', '98.52.78.243');
$curl->addHeader('Some-Custom-Header', 'Some Custom Value');
...
```

Or use single array

```php
<?php
...
$curl->addHeader(array('Host'=>'98.52.78.243', 'Some-Custom-Header'=>'Some Custom Value'));
...
```


### Setting Custom cURL request options

You can set/override many different options for cURL requests (see the [curl_setopt documentation](http://php.net/curl_setopt) for a list of them)

```php
<?php
...
$curl->addOption(CURLOPT_AUTOREFERER, true);
...
```


## Contact

Problems, comments, and suggestions all welcome: [leonid@svyatov.ru](mailto:leonid@svyatov.ru)