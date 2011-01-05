<?php
/**
 * CurlWrapper - Flexible wrapper class for PHP cURL extension
 *
 * @author Leonid Svyatov <leonid@svyatov.ru>
 * @copyright 2010-2011, Leonid Svyatov
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @version 1.0.8 / 05.01.2011
 * @link http://github.com/Svyatov/CurlWrapper
 */
class CurlWrapper
{
    /**
     * @var handle cURL handle
     */
    protected $ch = null;

    /**
     * @var string Filename of a writable file for cookies storage
     */
    protected $cookieFile = '';

    /**
     * @var array Cookies to send
     */
    protected $cookies = array();

    /**
     * @var array Headers to send
     */
    protected $headers = array();

    /**
     * @var array cURL options
     */
    protected $options = array();

    /**
     * @var array Predefined user agents. The 'firefox' value is used by default
     */
    protected $predefinedUserAgents = array(
        // IE 8.0
        'ie'       => 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0)',
        // Firefox 3.6.10
        'firefox'  => 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.10) Gecko/20100915 Firefox/3.6.10',
        // Opera 10.6
        'opera'    => 'Opera/9.80 (Windows NT 5.1; U; en-US) Presto/2.5.24 Version/10.6',
        // Chrome 5.0.375.70
        'chrome'   => 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.375.70 Safari/533.4',
        // Google Bot
        'bot'      => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
    );

    /**
     * @var array GET/POST params to send
     */
    protected $requestParams = array();

    /**
     * @var string cURL response data
     */
    protected $response = '';

    /**
     * @var array cURL transfer info
     */
    protected $transferInfo = array();

    /**
     * Initiates the cURL handle
     */
    public function __construct()
    {
        if (!function_exists('curl_init')) {
            $this->throwException('cURL library is not installed.');
        }

        $this->ch = curl_init();

        if (!$this->ch) {
            $this->throwException();
        }
    }

    /**
     * Closes and unallocates the cURL handle
     */
    public function __destruct()
    {
        curl_close($this->ch);
        $this->ch = null;
    }

    /**
     * Adds the pair $cookie=>$value to cookies array
     * If $cookie is array, then it's merged with cookies array
     *
     * Examples:
     * $curl->addCookie('user', 'admin');
     * $curl->addCookie(array('user'=>'admin', 'test'=>1));
     *
     * @param mixed $cookie
     * @param string $value
     */
    public function addCookie($cookie, $value = null)
    {
        if (is_array($cookie)) {
            $this->addCookiesAsArray($cookie);
        } else {
            $this->cookies[$cookie] = $value;
        }
    }

    /**
     * Merges $cookiesArray with cookies array
     * @param array $cookiesArray
     */
    public function addCookiesAsArray($cookiesArray)
    {
        foreach ($cookiesArray as $cookie => $value) {
            $this->cookies[$cookie] = $value;
        }
    }

    /**
     * Adds the pair $header=>$value to headers array
     * If $header is array, then it's merged with headers array
     *
     * Examples:
     * $curl->addHeader('Accept-Charset', 'windows-1251,utf-8;q=0.7,*;q=0.7');
     * $curl->addHeader('Pragma', '');
     * $curl->addHeader(array('Accept-Charset'=>'windows-1251,utf-8;q=0.7,*;q=0.7', 'Pragma'=>''));
     *
     * @param mixed $header
     * @param string $value
     */
    public function addHeader($header, $value = null)
    {
        if (is_array($header)) {
            $this->addHeadersAsArray($header);
        } else {
            $this->headers[$header] = $value;
        }
    }

    /**
     * Merges $headersArray with headers array
     * @param array $headersArray
     */
    public function addHeadersAsArray($headersArray)
    {
        foreach ($headersArray as $header => $value) {
            $this->headers[$header] = $value;
        }
    }

    /**
     * Adds the pair $option=>$value to options array
     * If $option is array, then it's merged with options array
     * @param mixed $option
     * @param mixed $value
     */
    public function addOption($option, $value = null)
    {
        if (is_array($option)) {
            $this->addOptionsAsArray($option);
        } else {
            $this->options[$option] = $value;
        }
    }

    /**
     * Merges $optionsArray with options array
     * @param array $optionArray
     */
    public function addOptionsAsArray($optionsArray)
    {
        foreach ($optionsArray as $option => $value) {
            $this->options[$option] = $value;
        }
    }

    /**
     * Adds the pair $name=>$value of GET/POST data to requestParams array
     * If $name is array, then it's merged with requestParams
     * If $name is query string, then it's converted to associative array and merged with requestParams
     *
     * Examples:
     * $curl->addRequestParam('param', 'test');
     * $curl->addRequestParam('param=test&otherparam=123');
     * $curl->addRequestParam(array('param'=>'test', 'otherparam'=>123));
     *
     * @param mixed $name
     * @param string $value
     */
    public function addRequestParam($name, $value = null)
    {
        if (is_array($name)) {
            $this->addRequestParamsAsArray($name);
        } elseif (is_string($name) && $value === null) {
            $this->addRequestParamsAsQueryString($name);
        } else {
            $this->requestParams[$name] = $value;
        }
    }

    /**
     * Merges $paramsArray with requestParams array
     * @param array $paramsArray
     */
    public function addRequestParamsAsArray($paramsArray)
    {
        $this->requestParams = array_merge($this->requestParams, $paramsArray);
    }

    /**
     * Converts $queryString to associative array and merges it with requestParams
     * @param array $paramsArray
     */
    public function addRequestParamsAsQueryString($queryString)
    {
        parse_str($queryString, $params);

        if (!empty($params)) {
            $this->requestParams = array_merge($this->requestParams, $params);
        }
    }

    /**
     * Clears the cookieFile
     */
    public function clearCookieFile()
    {
        if (file_put_contents($this->cookieFile, '') === false) {
            $this->throwException('Could not clear cookies file.');
        }
    }

    /**
     * Clears the cookies array
     */
    public function clearCookies()
    {
        $this->cookies = array();
    }

    /**
     * Clears the headers array
     */
    public function clearHeaders()
    {
        $this->headers = array();
    }

    /**
     * Clears the options array
     */
    public function clearOptions()
    {
        $this->options = array();
    }

    /**
     * Clears the requestParams array
     */
    public function clearRequestParams()
    {
        $this->requestParams = array();
    }

    /**
     * Makes the 'DELETE' request to the $url with an optional $requestParams
     * @param string $url
     * @param array $requestParams
     * @return string
     */
    public function delete($url, $requestParams = null)
    {
        return $this->request($url, 'DELETE', $requestParams);
    }

    /**
     * Makes the 'GET' request to the $url with an optional $requestParams
     * @param string $url
     * @param array $requestParams
     * @return string
     */
    public function get($url, $requestParams = null)
    {
        return $this->request($url, 'GET', $requestParams);
    }

    /**
     * Returns the last transfer's response data
     * @return string
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Gets the information about the last transfer
     * @param string $key
     * @return array|string
     * keys are:
     * -- 'url'                      - Last effective URL
     * -- 'content_type'             - Content-Type: of downloaded object, NULL indicates server did not send valid Content-Type: header
     * -- 'http_code'                - Last received HTTP code
     * -- 'header_size'              - Total size of all headers received
     * -- 'request_size'             - Total size of issued requests, currently only for HTTP requests
     * -- 'filetime'                 - Remote time of the retrieved document, if -1 is returned the time of the document is unknown
     * -- 'ssl_verify_result'        - Result of SSL certification verification requested by setting CURLOPT_SSL_VERIFYPEER
     * -- 'redirect_count'           - Number of redirects it went through if CURLOPT_FOLLOWLOCATION was set
     * -- 'total_time'               - Total transaction time in seconds for last transfer
     * -- 'namelookup_time'          - Time in seconds until name resolving was complete
     * -- 'connect_time'             - Time in seconds it took to establish the connection
     * -- 'pretransfer_time'         - Time in seconds from start until just before file transfer begins
     * -- 'size_upload'              - Total number of bytes uploaded
     * -- 'size_download'            - Total number of bytes downloaded
     * -- 'speed_download'           - Average download speed
     * -- 'speed_upload'             - Average upload speed
     * -- 'download_content_length'  - content-length of download, read from Content-Length:  field
     * -- 'upload_content_length'    - Specified size of upload
     * -- 'starttransfer_time'       - Time in seconds until the first byte is about to be transferred
     * -- 'redirect_time'            - Time in seconds of all redirection steps before final transaction was started
     */
    public function getTransferInfo($key = null)
    {
        if (empty($this->transferInfo)) {
            $this->throwException('There is no transfer info. Did you do the request?');
        }

        if ($key === null) {
            return $this->transferInfo;
        }

        if (isset($this->transferInfo[$key])) {
            return $this->transferInfo[$key];
        }

        $this->throwException('There is no such key: '.$key);
    }

    /**
     * Makes the 'HEAD' request to the $url with an optional $requestParams
     * @param string $url
     * @param array $requestParams
     * @return string
     */
    public function head($url, $requestParams = null)
    {
        return $this->request($url, 'HEAD', $requestParams);
    }

    /**
     * Makes the 'POST' request to the $url with an optional $requestParams
     * @param string $url
     * @param array $requestParams
     * @return string
     */
    public function post($url, $requestParams = null)
    {
        return $this->request($url, 'POST', $requestParams);
    }

    /**
     * Makes the 'PUT' request to the $url with an optional $requestParams
     * @param string $url
     * @param array $requestParams
     * @return string
     */
    public function put($url, $requestParams = null)
    {
        return $this->request($url, 'PUT', $requestParams);
    }

    /**
     * Removes the $cookie from cookies array
     * @param string $cookie
     */
    public function removeCookie($cookie)
    {
        if (isset($this->cookies[$cookie])) {
            unset($this->cookies[$cookie]);
        }
    }

    /**
     * Removes the $header from headers array
     * @param string $header
     */
    public function removeHeader($header)
    {
        if (isset($this->headers[$header])) {
            unset($this->headers[$header]);
        }
    }

    /**
     * Removes the $option from options array
     * @param integer $option
     */
    public function removeOption($option)
    {
        if (isset($this->options[$option])) {
            unset($this->options[$option]);
        }
    }

    /**
     * Removes the $name from requestParams array
     * @param string $name
     */
    public function removeRequestParam($name)
    {
        if (isset($this->requestParams[$name])) {
            unset($this->requestParams[$name]);
        }
    }

    /**
     * Makes the request of the specified $method to the $url with an optional $requestParams
     * @param string $url
     * @param string $method
     * @param array $requestParams
     * @return string
     */
    public function request($url, $method = 'GET', $requestParams = null)
    {
        $this->setURL($url);
        $this->setRequestMethod($method);

        if (!empty($requestParams)) {
            $this->addRequestParam($requestParams);
        }

        $this->initOptions();
        $this->response = curl_exec($this->ch);

        if ($this->response === false) {
            $this->throwException();
        }

        $this->transferInfo = curl_getinfo($this->ch);

        return $this->response;
    }

    /**
     * Reinitiates the cURL handle
     * headers, options, requestParams, cookies and cookieFile remain untouchable!
     */
    public function reset()
    {
        if ($this->ch) {
            $this->__destruct();
        }

        $this->transferInfo = array();
        $this->__construct();
    }

    /**
     * Reinitiates the cURL handle and resets all data,
     * inlcuding headers, options, requestParams, cookies and cookieFile
     */
    public function resetAll()
    {
        $this->clearHeaders();
        $this->clearOptions();
        $this->clearRequestParams();
        $this->clearCookies();
        $this->clearCookieFile();
        $this->reset();
    }

    /**
     * Sets the number of seconds to wait while trying to connect, use 0 to wait indefinitely
     * @param integer $seconds
     */
    public function setConnectTimeOut($seconds)
    {
        $this->addOption(CURLOPT_CONNECTTIMEOUT, $seconds);
    }

    /**
     * Sets the file's name to store cookies, throws exception if file is not writable or does'n exists
     * @param string $filename
     */
    public function setCookieFile($filename)
    {
        if (!is_writable($filename)) {
            $this->throwException('Cookie file "'.$filename.'" is not writable or does\'n exists!');
        }

        $this->cookieFile = $filename;
    }

    /**
     * Sets the default headers
     */
    public function setDefaultHeaders()
    {
        $this->headers = array(
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Charset'  => 'windows-1251,utf-8;q=0.7,*;q=0.7',
            'Accept-Language' => 'ru,en-us;q=0.7,en;q=0.3',
            'Accept-Encoding' => 'gzip,deflate',
            'Keep-Alive'      => '300',
            'Connection'      => 'keep-alive',
            'Cache-Control'   => 'max-age=0',
            'Pragma'          => ''
        );
    }

    /**
     * Sets the default options
     */
    public function setDefaultOptions()
    {
        $this->options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_ENCODING       => 'gzip,deflate',
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 30,
        );
    }

    /**
     * Sets default headers and options and user agent if $userAgent is given
     * @param string $userAgent Some predefined user agent name (ie, firefox, opera, etc.) or anything string you want
     */
    public function setDefaults($userAgent = null)
    {
        $this->setDefaultHeaders();
        $this->setDefaultOptions();

        if (!empty($userAgent)) {
            $this->setUserAgent($userAgent);
        }
    }

    /**
     * Sets the contents of the "Referer: " header to be used in a HTTP request
     * @param string $referer
     */
    public function setReferer($referer)
    {
        $this->addOption(CURLOPT_REFERER, $referer);
    }

    /**
     * Sets the maximum number of seconds to allow cURL functions to execute
     * @param integer $seconds
     */
    public function setTimeOut($seconds)
    {
        $this->addOption(CURLOPT_TIMEOUT, $seconds);
    }

    /**
     * Sets the contents of the "User-Agent: " header to be used in a HTTP request
     * You can use 'magic' words: 'ie', 'firefox', 'opera' and 'chrome'
     * to set default CurlWrapper's user agent defined in predefinedUserAgents array
     * @param string $userAgent
     */
    public function setUserAgent($userAgent)
    {
        if (isset($this->predefinedUserAgents[$userAgent])) {
            $this->addOption(CURLOPT_USERAGENT, $this->predefinedUserAgents[$userAgent]);
        } else {
            $this->addOption(CURLOPT_USERAGENT, $userAgent);
        }
    }

    /**
     * Sets the value of cookieFile to empty string
     */
    public function unsetCookieFile()
    {
        $this->cookieFile = '';
    }

    /**
     * Builds url from associative array made by parse_str()
     * @param array $parsedUrl
     * @return string
     */
    protected function buildUrl($parsedUrl)
    {
        return (isset($parsedUrl['scheme'])   ?     $parsedUrl["scheme"].'://' : '').
               (isset($parsedUrl['user'])     ?     $parsedUrl["user"].':'     : '').
               (isset($parsedUrl['pass'])     ?     $parsedUrl["pass"].'@'     : '').
               (isset($parsedUrl['host'])     ?     $parsedUrl["host"]         : '').
               (isset($parsedUrl['port'])     ? ':'.$parsedUrl["port"]         : '').
               (isset($parsedUrl['path'])     ?     $parsedUrl["path"]         : '').
               (isset($parsedUrl['query'])    ? '?'.$parsedUrl["query"]        : '').
               (isset($parsedUrl['fragment']) ? '#'.$parsedUrl["fragment"]     : '');
    }

    /**
     * Sets the final options and initiates them by curl_setopt_array()
     */
    protected function initOptions()
    {
        if (!empty($this->requestParams)) {
            if (isset($this->options[CURLOPT_HTTPGET])) {
                $this->prepareGetParams();
            } else {
                $this->addOption(CURLOPT_POSTFIELDS, $this->requestParams);
            }
        }

        if (!empty($this->headers)) {
            $this->addOption(CURLOPT_HTTPHEADER, $this->prepareHeaders());
        }

        if (!empty($this->cookieFile)) {
            $this->addOption(CURLOPT_COOKIEFILE, $this->cookieFile);
            $this->addOption(CURLOPT_COOKIEJAR, $this->cookieFile);
        }

        if (!empty($this->cookies)) {
            $this->addOption(CURLOPT_COOKIE, $this->prepareCookies());
        }

        if (!curl_setopt_array($this->ch, $this->options)) {
            $this->throwException();
        }
    }

    /**
     * Converts the cookies array to the string correct format
     * @return string
     */
    protected function prepareCookies()
    {
        $cookies_string = '';

        foreach ($this->cookies as $cookie => $value) {
            $cookies_string .= $cookie.'='.$value.'; ';
        }

        return $cookies_string;
    }

    /**
     * Converts requestParams array to the query string and adds it to the request url
     */
    protected function prepareGetParams()
    {
        $parsed_url = parse_url($this->options[CURLOPT_URL]);
        $query = http_build_query($this->requestParams, '', '&');

        if (isset($parsed_url['query'])) {
            $parsed_url['query'] .= '&'.$query;
        } else {
            $parsed_url['query'] = $query;
        }

        $this->setUrl($this->buildUrl($parsed_url));
    }

    /**
     * Converts the headers array to the cURL's option format array
     * @return array
     */
    protected function prepareHeaders()
    {
        $headers_array = array();

        foreach ($this->headers as $header => $value) {
            $headers_array[] = $header.': '.$value;
        }

        return $headers_array;
    }

    /**
     * Sets the HTTP request method
     * @param string $method
     */
    protected function setRequestMethod($method)
    {
        /*
         * Preventing request methods collision
         */
        $this->removeOption(CURLOPT_NOBODY);
        $this->removeOption(CURLOPT_HTTPGET);
        $this->removeOption(CURLOPT_POST);
        $this->removeOption(CURLOPT_CUSTOMREQUEST);

        switch (strtoupper($method)) {
            case 'HEAD':
                $this->addOption(CURLOPT_NOBODY, true);
            break;

            case 'GET':
                $this->addOption(CURLOPT_HTTPGET, true);
            break;

            case 'POST':
                $this->addOption(CURLOPT_POST, true);
            break;

            default:
                $this->addOption(CURLOPT_CUSTOMREQUEST, $method);
        }
    }

    /**
     * Sets the url
     * @param string $url
     */
    protected function setUrl($url)
    {
        $this->addOption(CURLOPT_URL, $url);
    }

    /**
     * Sets the error's details and throws the exception
     */
    protected function throwException($msg = '')
    {
        if (empty($msg)) {
            $error = curl_errno($this->ch);
            $errorMsg = 'cURL error: '.curl_error($this->ch);
        } else {
            $error = 1;
            $errorMsg = $msg;
        }

        throw new CurlWrapperException($errorMsg, $error);
    }
}

/**
 * CurlWrapper Exceptions class
 */
class CurlWrapperException extends Exception {}