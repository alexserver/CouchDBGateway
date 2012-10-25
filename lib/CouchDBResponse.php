<?php

class CouchDBResponse {

    private $raw_response = '';
    private $headers = '';
    private $body = '';

    function __construct($response = '') {
        $this->raw_response = $response;
        list($this->headers, $this->body) = explode("\r\n\r\n", $response);
    }

    function getRawResponse() {
        return $this->raw_response;
    }

    function getHeaders() {
        return $this->headers;
    }

    function getBody($decode_json = false) {
        return $decode_json ? json_decode($this->body, true) : $this->body;
    }
}