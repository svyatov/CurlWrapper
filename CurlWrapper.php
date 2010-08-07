<?php

/**
 * CurlWrapper - Flexible wrapper class for the PHP cURL extension
 *
 * PHP version 5
 *
 * @author    Leonid Svyatov <leonid@svyatov.ru>
 * @copyright 2010 Leonid Svyatov
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version   0.4 $Id$
 * @link      http://github.com/Svyatov/CurlWrapper
 */

class CurlWrapper
{
    /**
     * cURL handle
     *
     * @var cURL handle
     */
    private $_ch = null;

    /**
     * cURL _error number
     *
     * @var integer|true
     */
    private $_error = 0;

    /**
     * cURL _error message
     *
     * @var string
     */
    private $_error_msg = '';

    /**
     * cURL transfer info
     *
     * @var associative array
     */
    private $_transfer_info = array();

    /**
     * cURL _response data
     *
     * @var string
     */
    private $_response = '';

    /**
     * Filename of a writable file for cookie storage
     *
     * @var string
     */
    private $_cookie_file = '';

    /**
     * cURL options
     *
     * @var associative array
     */
    private $_options = array();

    /**
     * Headers to send
     *
     * @var associative array
     */
    private $_headers = array();

    /**
     * Cookies to send
     *
     * @var associative array
     */
    private $_cookies = array();

    /**
     * GET/POST data to send
     *
     * @var associative array
     */
    private $_req_data = array();

    /**
     * Some popular user agents
     *
     * The 'firefox' value is used by default
     *
     * @var associative array
     */
    private $_user_agents = array(
        // IE 8.0
        'explorer' => 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0)',
        // Firefox 3.6.4
        'firefox'  => 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.3) Gecko/20100401 Firefox/3.6.4',
        // Opera 10.6
        'opera'    => 'Opera/9.80 (Windows NT 5.1; U; en-US) Presto/2.5.24 Version/10.6',
        // Chrome 5.0.375.70
        'chrome'   => 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.375.70 Safari/533.4'
    );

    /**
     * CurlWrapper::__construct()
     *
     * Initiates cURL handle and sets default headers and options if needed
     *
     * @param boolean $setDefaults
     * @return void
     */
    public function __construct($setDefaults = true)
    {
        if (!function_exists('curl_init'))
        {
            $this->throwError('cURL library is not installed.');
        }

        $this->_ch = curl_init();

        if (!$this->_ch)
        {
            $this->throwError();
        }

        if ($setDefaults && !$this->_error)
        {
            $this->setDefaultHeaders();
            $this->setDefaultOptions();
        }
    }

    /**
     * CurlWrapper::__destruct()
     *
     * Closes and unallocates cURL handle
     *
     * @return void
     */
    public function __destruct()
    {
        curl_close($this->_ch);
        $this->_ch = null;
    }

    /**
     * CurlWrapper::get()
     *
     * Makes a 'GET' request to the $url with an optional request data $reqData
     *
     * @param string $url
     * @param associative array $reqData
     * @return string|null
     */
    public function get($url, $reqData = null)
    {
        return $this->request($url, 'GET', $reqData);
    }

    /**
     * CurlWrapper::post()
     *
     * Makes a 'POST' request to the $url with an optional request data $reqData
     *
     * @param string $url
     * @param associative array $reqData
     * @return string|null
     */
    public function post($url, $reqData = null)
    {
        return $this->request($url, 'POST', $reqData);
    }

    /**
     * CurlWrapper::head()
     *
     * Makes a 'HEAD' request to the $url with an optional request data $reqData
     *
     * @param string $url
     * @param associative array $reqData
     * @return string|null
     */
    public function head($url, $reqData = null)
    {
        return $this->request($url, 'HEAD', $reqData);
    }

    /**
     * CurlWrapper::request()
     *
     * Makes a request of the specified $method to the $url with an optional request data $reqData
     *
     * @param string $url
     * @param string $method
     * @param associative array $reqData
     * @return string|null
     */
    public function request($url, $method = 'GET', $reqData = null)
    {
        if ($this->_error)
        {
            return null;
        }

        $this->setURL($url);
        $this->setRequestMethod($method);
        $this->addRequestData($reqData);

        $this->initOptions();

        if (!$this->_response = curl_exec($this->_ch))
        {
            $this->throwError();
        }

        $this->_transfer_info = curl_getinfo($this->_ch);

        return $this->_response;
    }

    /**
     * CurlWrapper::getResponse()
     *
     * Returns last transfer's response data
     *
     * @return string|null
     */
    public function getResponse()
    {
        if (empty($this->_response))
        {
            return null;
        }

        return $this->_response;
    }

    /**
     * CurlWrapper::reset()
     *
     * Reinitiates cURL handle and sets default headers and options if needed,
     * headers, options, req_data, cookies and cookie_file remain untouchable!
     *
     * @param boolean $setDefaults
     * @return void
     */
    public function reset($setDefaults = false)
    {
        if ($this->_ch)
        {
            $this->__destruct();
        }

        $this->_error = false;
        $this->_error_msg = '';
        $this->_transfer_info = array();

        $this->__construct($setDefaults);
    }

    /**
     * CurlWrapper::resetAll()
     *
     * Reinitiates cURL handle and resets all data,
     * inlcuding headers, options, req_data, cookies and cookie_file
     *
     * @param boolean $setDefaults
     * @return void
     */
    public function resetAll($setDefaults = true)
    {
        $this->clearHeaders();
        $this->clearOpts();
        $this->clearRequestData();
        $this->clearCookies();
        $this->resetCookieFile();
        $this->reset($setDefaults);
    }

    /**
     * CurlWrapper::setCookieFile()
     *
     * Sets file's name to store cookies, throw exception if file is not writable or does'n exists
     *
     * @param string $filename
     * @return void
     */
    public function setCookieFile($filename)
    {
        if (!is_writable($filename))
        {
            $this->throwError('Cookie file "'.$filename.'" is not writable or does\'n exists!');
        }

        $this->_cookie_file = $filename;
    }

    /**
     * CurlWrapper::resetCookieFile()
     *
     * Resets $this->_cookie_file value to empty string
     *
     * @return void
     */
    public function resetCookieFile()
    {
        $this->_cookie_file = '';
    }

    /**
     * CurlWrapper::addOpt()
     *
     * Adds pair '$option' => '$value' to $this->_options array
     *
     * If $option is array, then it's merged with $this->_options
     * If arrays have the same field names, then the $options array value for that name will overwrite the $this->_options array one
     *
     * @param mixed $option
     * @param mixed $value
     * @return void
     */
    public function addOpt($option, $value = null)
    {
        if (is_array($option))
        {
            foreach ($option as $opt => $val)
            {
                $this->_options[$opt] = $val;
            }
        }
        else
        {
            $this->_options[$option] = $value;
        }
    }

    /**
     * CurlWrapper::delOpt()
     *
     * Removes '$option' from $this->_options array
     *
     * @param integer $option
     * @return void
     */
    public function delOpt($option)
    {
        if (isset($this->_options[$option]))
        {
            unset($this->_options[$option]);
        }
    }

    /**
     * CurlWrapper::clearOpts()
     *
     * Clears $this->_options array
     * Don't forget to set all necessary options before new request
     *
     * @return void
     */
    public function clearOpts()
    {
        $this->_options = array();
    }

    /**
     * CurlWrapper::addHeader()
     *
     * Adds pair '$header' => '$value' to $this->_headers array
     *
     * If $header is array, then it's merged with $this->_headers
     * If arrays have the same field names, then the $header array value for that name will overwrite the $this->_headers array one
     *
     * Examples:
     * -- $header = 'Accept-Charset', $value = 'windows-1251,utf-8;q=0.7,*;q=0.7'
     * -- $header = 'Pragma', $value = ''
     *
     * @param mixed $header
     * @param string $value
     * @return void
     */
    public function addHeader($header, $value = null)
    {
        if (is_array($header))
        {
            foreach ($header as $hdr => $val)
            {
                $this->_headers[$hdr] = $val;
            }
        }
        else
        {
            $this->_headers[$header_type] = $value;
        }
    }

    /**
     * CurlWrapper::delHeader()
     *
     * Removes '$header' from $this->_headers array
     *
     * @param string $header
     * @return void
     */
    public function delHeader($header)
    {
        if (isset($this->_headers[$header]))
        {
            unset($this->_headers[$header]);
        }
    }

    /**
     * CurlWrapper::clearHeaders()
     *
     * Clears $this->_headers array
     * Don't forget to set all necessary headers before new request
     *
     * @return void
     */
    public function clearHeaders()
    {
        $this->_headers = array();
    }

    /**
     * CurlWrapper::addCookie()
     *
     * Adds pair '$cookie' => '$value' to $this->_cookies array
     *
     * If $cookie is array, then it's merged with $this->_cookies
     * If arrays have the same field names, then the $cookie array value for that name will overwrite the $this->_cookies array one
     *
     * Examples:
     * -- $cookie = 'user', $value = 'admin'
     *
     * @param mixed $cookie
     * @param string $value
     * @return void
     */
    public function addCookie($cookie, $value = null)
    {
        if (is_array($cookie))
        {
            foreach ($cookie as $ck => $val)
            {
                $this->_cookies[$ck] = $val;
            }
        }
        else
        {
            $this->_cookies[$cookie] = $value;
        }
    }

    /**
     * CurlWrapper::delCookie()
     *
     * Removes '$cookie' from $this->_cookies array
     *
     * @param string $cookie
     * @return void
     */
    public function delCookie($cookie)
    {
        if (isset($this->_cookies[$cookie]))
        {
            unset($this->_cookies[$cookie]);
        }
    }

    /**
     * CurlWrapper::clearCookies()
     *
     * Clears $_cookies array
     *
     * @return void
     */
    public function clearCookies()
    {
        $this->_cookies = array();
    }

    /**
     * CurlWrapper::addRequestData()
     *
     * Adds pair '$name' => '$value' of GET/POST data to $this->_req_data array
     *
     * If $name is array, then it's merged with $this->_req_data
     * If arrays have the same field names, then the $name array value for that name will overwrite the $this->_req_data array one
     *
     * If $name is query string, then it's converted to associative array and merged with $this->_req_data
     *
     * @param mixed $name
     * @param string $value
     * @return void
     */
    public function addRequestData($name, $value = null)
    {
        if (is_array($name))
        {
            $this->_req_data = array_merge($this->_req_data, $name);
        }
        elseif (is_string($name) && $value === null)
        {
            parse_str($name, $req_data);

            if (!empty($req_data))
            {
                $this->_req_data = array_merge($this->_req_data, $req_data);
            }
        }
        else
        {
            $this->_req_data[$name] = $value;
        }
    }

    /**
     * CurlWrapper::delRequestData()
     *
     * Removes GET/POST data '$name' from $this->_req_data array
     *
     * @param string $name
     * @return void
     */
    public function delRequestData($name)
    {
        if (isset($this->_req_data[$name]))
        {
            unset($this->_req_data[$name]);
        }
    }

    /**
     * CurlWrapper::clearRequestData()
     *
     * Clears $this->_req_data array
     * Don't forget to set all necessary GET/POST data before new request
     *
     * @return void
     */
    public function clearRequestData()
    {
        $this->_req_data = array();
    }

    /**
     * CurlWrapper::setUserAgent()
     *
     * Sets user agent
     *
     * You can use 'magic' words: 'explorer', 'firefox', 'opera' and 'chrome'
     * to set default CurlWrapper's user agents defined in $this->_user_agents
     *
     * @param string $userAgent
     * @return void
     */
    public function setUserAgent($userAgent)
    {
        if (isset($this->_user_agents[$userAgent]))
        {
            $this->addOpt(CURLOPT_USERAGENT, $this->_user_agents[$userAgent]);
        }
        else
        {
            $this->addOpt(CURLOPT_USERAGENT, $userAgent);
        }
    }

    /**
     * CurlWrapper::isError()
     *
     * Returns cURL's error number
     *
     * @return integer
     */
    public function isError()
    {
        return $this->_error;
    }

    /**
     * CurlWrapper::errorMsg()
     *
     * Returns cURL's error message
     *
     * @return string|null
     */
    public function errorMsg()
    {
        if ($this->_error)
        {
            return $this->_error_msg;
        }

        return null;
    }

    /**
     * CurlWrapper::getTransferInfo()
     *
     * Gets information about the last transfer
     *
     * @param string $key
     * @return associative array|string|null
     * keys are:
     * -- ['url']                        - Last effective URL
     * -- ['content_type']                - Content-Type: of downloaded object, NULL indicates server did not send valid Content-Type: header
     * -- ['http_code']                    - Last received HTTP code
     * -- ['header_size']                - Total size of all headers received
     * -- ['request_size']                - Total size of issued requests, currently only for HTTP requests
     * -- ['filetime']                    - Remote time of the retrieved document, if -1 is returned the time of the document is unknown
     * -- ['ssl_verify_result']            - Result of SSL certification verification requested by setting CURLOPT_SSL_VERIFYPEER
     * -- ['redirect_count']            - Number of redirects it went through if CURLOPT_FOLLOWLOCATION was set
     * -- ['total_time']                - Total transaction time in seconds for last transfer
     * -- ['namelookup_time']            - Time in seconds until name resolving was complete
     * -- ['connect_time']                - Time in seconds it took to establish the connection
     * -- ['pretransfer_time']            - Time in seconds from start until just before file transfer begins
     * -- ['size_upload']                - Total number of bytes uploaded
     * -- ['size_download']                - Total number of bytes downloaded
     * -- ['speed_download']            - Average download speed
     * -- ['speed_upload']                - Average upload speed
     * -- ['download_content_length']    - content-length of download, read from Content-Length:  field
     * -- ['upload_content_length']        - Specified size of upload
     * -- ['starttransfer_time']        - Time in seconds until the first byte is about to be transferred
     * -- ['redirect_time']                - Time in seconds of all redirection steps before final transaction was started
     */
    public function getTransferInfo($key = null)
    {
        if (empty($this->_transfer_info))
        {
            return null;
        }

        if ($key === null)
        {
            return $this->_transfer_info;
        }

        if (isset($this->_transfer_info[$key]))
        {
            return $this->_transfer_info[$key];
        }

        return null;
    }

    /**
     * CurlWrapper::throwError()
     *
     * Sets error information and throws exception
     *
     * @return void
     */
    protected function throwError($msg = '')
    {
        if (empty($msg))
        {
            $this->_error = curl_errno($this->_ch);
            $this->_error_msg = 'cURL error: '.curl_error($this->_ch);
        }
        else
        {
            $this->_error = true;
            $this->_error_msg = $msg;
        }

        throw new Exception('CurlWrapper exception: '.$this->_error_msg, $this->_error);
    }

    /**
     * CurlWrapper::compileHeaders()
     *
     * Converts $this->_headers array to cURL's option format array
     *
     * @return array|null
     */
    protected function compileHeaders()
    {
        if (empty($this->_headers))
        {
            return null;
        }

        foreach ($this->_headers as $key => $value)
        {
            $headers[] = $key.': '.$value;
        }

        return $headers;
    }

    /**
     * CurlWrapper::compileCookies()
     *
     * Converts $this->_cookies array to string correct format
     *
     * @return
     */
    protected function compileCookies()
    {
        if (empty($this->_cookies))
        {
            return null;
        }

        $cookies = '';

        foreach ($this->_cookies as $ck => $val)
        {
            $cookies .= $ck.'='.$val.'; ';
        }

        return $cookies;
    }

    /**
     * CurlWrapper::setRequestMethod()
     *
     * Sets request method
     *
     * @param string $method
     * @return void
     */
    protected function setRequestMethod($method)
    {
        /*
         * Preventing request methods collision
         */
        $this->delOpt(CURLOPT_NOBODY);
        $this->delOpt(CURLOPT_HTTPGET);
        $this->delOpt(CURLOPT_POST);
        $this->delOpt(CURLOPT_CUSTOMREQUEST);

        switch (strtoupper($method))
        {
            case 'HEAD':
                $this->addOpt(CURLOPT_NOBODY, true);
            break;

            case 'GET':
                $this->addOpt(CURLOPT_HTTPGET, true);
            break;

            case 'POST':
                $this->addOpt(CURLOPT_POST, true);
            break;

            default:
                $this->addOpt(CURLOPT_CUSTOMREQUEST, $method);
        }
    }

    /**
     * CurlWrapper::setUrl()
     *
     * Sets url to request
     *
     * @param string $url
     * @return void
     */
    protected function setUrl($url)
    {
        $this->addOpt(CURLOPT_URL, $url);
    }

    /**
     * CurlWrapper::setDefaultHeaders()
     *
     * Sets default headers
     *
     * @return void
     */
    protected function setDefaultHeaders()
    {
        $this->_headers = array(
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Charset'  => 'windows-1251,utf-8;q=0.7,*;q=0.7',
            'Accept-Language' => 'ru,en-us;q=0.7,en;q=0.3',
            'Pragma'          => ''
        );
    }

    /**
     * CurlWrapper::setDefaultOptions()
     *
     * Sets default options
     *
     * @return void
     */
    protected function setDefaultOptions()
    {
        $this->_options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_ENCODING       => 'gzip,deflate',
            CURLOPT_USERAGENT      => $this->_user_agents['firefox'],
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 30
        );
    }

    /**
     * CurlWrapper::initOptions()
     *
     * Sets final options and initiates them by curl_setopt_array()
     *
     * @return void
     */
    protected function initOptions()
    {
        if (!empty($this->_req_data))
        {
            if (isset($this->_options[CURLOPT_HTTPGET]))
            {
                $parsed_url = parse_url($this->_options[CURLOPT_URL]);
                $query = http_build_query($this->_req_data, '', '&');

                if (isset($parsed_url['query']))
                {
                    $parsed_url['query'] .= '&'.$query;
                }
                else
                {
                    $parsed_url['query'] = $query;
                }

                $url = (isset($parsed_url['scheme'])   ?     $parsed_url["scheme"].'://' : '').
                       (isset($parsed_url['user'])     ?     $parsed_url["user"].':'     : '').
                       (isset($parsed_url['pass'])     ?     $parsed_url["pass"].'@'     : '').
                       (isset($parsed_url['host'])     ?     $parsed_url["host"]         : '').
                       (isset($parsed_url['port'])     ? ':'.$parsed_url["port"]         : '').
                       (isset($parsed_url['path'])     ?     $parsed_url["path"]         : '').
                       (isset($parsed_url['query'])    ? '?'.$parsed_url["query"]        : '').
                       (isset($parsed_url['fragment']) ? '#'.$parsed_url["fragment"]     : '');

                $this->setUrl($url);
            }
            else
            {
                $this->addOpt(CURLOPT_POSTFIELDS, $this->_req_data);
            }
        }

        if (!empty($this->_headers))
        {
            $this->addOpt(CURLOPT_HTTPHEADER, $this->compileHeaders());
        }

        if (!empty($this->_cookie_file))
        {
            $this->addOpt(CURLOPT_COOKIEFILE, $this->_cookie_file);
            $this->addOpt(CURLOPT_COOKIEJAR, $this->_cookie_file);
        }

        if (!empty($this->_cookies))
        {
            $this->addOpt(CURLOPT_COOKIE, $this->compileCookies());
        }

        if (!curl_setopt_array($this->_ch, $this->_options))
        {
            $this->throwError();
        }
    }

}