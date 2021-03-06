<?php

namespace Tuxed\Http;

class HttpRequest {

    protected $_uri;
    protected $_method;
    protected $_headers;
    protected $_content;
    protected $_pathInfo;
    protected $_patternMatch;
    protected $_methodMatch;

    public function __construct($requestUri, $requestMethod = "GET") {
        $this->setRequestUri(new Uri($requestUri));
        $this->setRequestMethod($requestMethod);
        $this->_headers = array();
        $this->_content = NULL;
        $this->_pathInfo = NULL;
        $this->_patternMatch = FALSE;
        $this->_methodMatch = array();
    }

    public static function fromIncomingHttpRequest(IncomingHttpRequest $i) {
        $request = new static($i->getRequestUri(), $i->getRequestMethod());
        $request->setHeaders($i->getRequestHeaders());
        $request->setContent($i->getContent());
        $request->setPathInfo($i->getPathInfo());
        return $request;
    }

    public function setRequestUri(Uri $u) {
        $this->_uri = $u;
    }

    public function getRequestUri() {
        return $this->_uri;
    }

    public function setRequestMethod($method) {
        if (!in_array($method, array("GET", "POST", "PUT", "DELETE", "HEAD", "OPTIONS"))) {
            throw new HttpRequestException("invalid or unsupported request method");
        }
        $this->_method = $method;
    }

    public function getRequestMethod() {
        return $this->_method;
    }

    public function setPostParameters(array $parameters) {
        if ($this->getRequestMethod() !== "POST") {
            throw new HttpRequestException("request method should be POST");
        }
        $this->setHeader("Content-Type", "application/x-www-form-urlencoded");
        $this->setContent(http_build_query($parameters));
    }

    public function getQueryParameters() {
        if ($this->_uri->getQuery() === NULL) {
            return array();
        }
        $parameters = array();
        parse_str($this->_uri->getQuery(), $parameters);
        return $parameters;
    }

    public function getQueryParameter($key) {
        $parameters = $this->getQueryParameters();
        return (array_key_exists($key, $parameters) && !empty($parameters[$key])) ? $parameters[$key] : NULL;
    }

    public function getPostParameter($key) {
        $parameters = $this->getPostParameters();
        return (array_key_exists($key, $parameters) && !empty($parameters[$key])) ? $parameters[$key] : NULL;
    }

    public function getPostParameters() {
        if ($this->getRequestMethod() !== "POST") {
            throw new HttpRequestException("request method should be POST");
        }
        $parameters = array();
        parse_str($this->getContent(), $parameters);
        return $parameters;
    }

    public function setHeaders(array $headers) {
        foreach ($headers as $k => $v) {
            $this->setHeader($k, $v);
        }
    }

    public function setHeader($headerKey, $headerValue) {
        $foundHeaderKey = $this->_getHeaderKey($headerKey);
        if ($foundHeaderKey === NULL) {
            $this->_headers[$headerKey] = $headerValue;
        } else {
            $this->_headers[$foundHeaderKey] = $headerValue;
        }
    }

    public function getHeader($headerKey) {
        $headerKey = $this->_getHeaderKey($headerKey);
        return $headerKey !== NULL ? $this->_headers[$headerKey] : NULL;
    }

    /**
     * Look for a header in a case insensitive way. It is possible to have a 
     * header key "Content-type" or a header key "Content-Type", these should
     * be treated as the same.
     * 
     * @param headerName the name of the header to search for
     * @returns The name of the header as it was set (original case)
     *
     */
    protected function _getHeaderKey($headerKey) {
        $headerKeys = array_keys($this->_headers);
        $keyPositionInArray = array_search(strtolower($headerKey), array_map('strtolower', $headerKeys));
        if(FALSE === $keyPositionInArray) {
            // replaces dashes with underscores and search again
            $keyPositionInArray = array_search(str_replace('-', '_', strtolower($headerKey)), array_map('strtolower', $headerKeys));
        }
        return ($keyPositionInArray === FALSE) ? NULL : $headerKeys[$keyPositionInArray];
    }

    public function getHeaders($formatted = FALSE) {
        if (!$formatted) {
            return $this->_headers;
        }
        $hdrs = array();
        foreach ($this->_headers as $k => $v) {
            array_push($hdrs, $k . ": " . $v);
        }
        return $hdrs;
    }

    public function setContent($content) {
        $this->_content = $content;
    }

    public function getContent() {
        return $this->_content;
    }

    public function setContentType($contentType) {
        $this->setHeader("Content-Type", $contentType);
    }

    public function getContentType() {
        return $this->getHeader("Content-Type");
    }

    public function setPathInfo($pathInfo) {
        $this->_pathInfo = $pathInfo;
    }

    public function getPathInfo() {
        return $this->_pathInfo;
    }

    public function getBasicAuthUser() {
        try { 
            $userpass = Utils::parseBasicAuthHeader($this->getHeader("Authorization"));
            return $userpass[0];
        } catch (UtilsException $e) {
            return NULL;
        }
    }

    public function getBasicAuthPass() {
        try { 
            $userpass = Utils::parseBasicAuthHeader($this->getHeader("Authorization"));
            return $userpass[1];
        } catch (UtilsException $e) {
            return NULL;
        }
    }

    public function matchRest($requestMethod, $requestPattern, $callback) {
        if(!in_array($requestMethod, $this->_methodMatch)) {
            array_push($this->_methodMatch, $requestMethod);
        }

        if($requestMethod !== $this->getRequestMethod()) {
            return FALSE;
        }
        $pi = $this->getPathInfo();
        if(!is_string($pi) || empty($pi) || FALSE === strpos($pi, "/")) {
            return FALSE;
        }
        $f = explode("/", $pi);

        if(!is_string($requestPattern) || empty($requestPattern) || FALSE === strpos($requestPattern, "/")) {
            return FALSE;
        }
        $e = explode("/", $requestPattern);

        if(count($e) !== count($f)) {
            return FALSE;
        }
        $parameters = array();
        for($i = 0; $i < count($e); $i++) {
            $z = !empty($e[$i]) ? strpos($e[$i], ":") : FALSE;
            if(FALSE === $z || 0 !== $z) {
                if($f[$i] !== $e[$i]) {
                    return FALSE;
                }
            } else {
                if(empty($f[$i])) {
                    return FALSE;
                } else {
                    array_push($parameters, $f[$i]);
                }
            }
        }

        $this->_patternMatch = TRUE;
        call_user_func_array($callback, $parameters);
        return TRUE;
    }

    public function matchRestDefault($callback) {
        $callback($this->_methodMatch, $this->_patternMatch);
    }

    public function __toString() {
        $s  = PHP_EOL;
        $s .= "*HttpRequest*" . PHP_EOL;
        $s .= "Request Method: " . $this->getRequestMethod() . PHP_EOL;
        $s .= "Request URI: " . $this->getRequestUri()->getUri() . PHP_EOL;
        $s .= "Headers:" . PHP_EOL;
        foreach($this->getHeaders(TRUE) as $v) {
            $s .= "\t" . $v . PHP_EOL;
        }
        if(1 === preg_match("|^application/json|", $this->getContentType())) {
            // format JSON
            $s .= "Content (formatted JSON):" . PHP_EOL;
            $s .= Utils::json_format($this->getContent());
        } else {
            $s .= "Content:" . PHP_EOL;
            $s .= $this->getContent();
        }
        return $s;
    }

}
