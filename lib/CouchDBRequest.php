<?php

class CouchDBRequest extends CComponent {

    static $VALID_HTTP_METHODS = array('DELETE', 'GET', 'POST', 'PUT');
    private $sock = NULL;

    private $username;
    private $password;
    private $host;
    private $port;
    private $method;
    private $data;
    private $url;
    private $response;
    private $ssl;

    protected $defaultProperties = array(
        'host'=>'localhost',
        'port'=>5984,
        'method'=>'GET',
        'data'=>null,
        'username'=>null,
        'password'=>null,
        'ssl'=>false,
    );

    function __construct(array $config=array()) {
        foreach ($config as $key => $value) {
            $this->$key = ($value!=null && $value!=='') ? $value : $this->defaultProperties[$key];
        }
        //overriding the port (if ssl, set to 443, else, set to 5984)
        if ($this->ssl) {
            $this->port = 443;
        }
        $this->method = strtoupper($this->method);

        if(!in_array($this->method, self::$VALID_HTTP_METHODS)) {
            throw new Exception('Invalid HTTP method: '.$this->method);
        }
    }

    function getRequest() {
        $req = "{$this->method} {$this->url} HTTP/1.0\r\nHost: {$this->host}\r\n";

        if($this->username || $this->password)
            $req .= 'Authorization: Basic '.base64_encode($this->username.':'.$this->password)."\r\n";

        if($this->data) {
            $req .= 'Content-Length: '.strlen($this->data)."\r\n";
            $req .= 'Content-Type: application/json'."\r\n\r\n";
            $req .= $this->data."\r\n";
        } else {
            $req .= "\r\n";
        }

        return $req;
    }

    private function connect() {
        $url = (($this->ssl)? 'ssl://':'').$this->host;
        $this->sock = @fsockopen($url, $this->port, $err_num, $err_string);
        if(!$this->sock) {
            throw new Exception('Could not open connection to '.$this->host.':'.$this->port.' ('.$err_string.')');
        }
    }

    private function disconnect() {
        fclose($this->sock);
        $this->sock = NULL;
    }

    private function execute() {
        $result = @fwrite($this->sock, $this->getRequest());
        if(!$result) {
            throw new Exception('Could not send data to '.$this->host.':'.$this->port);
        }
        $response = '';
        while(!feof($this->sock)) {
            $response .= @fgets($this->sock);
        }
        $this->response = new CouchDBResponse($response);
        return $this->response;
    }

    public function send() {
        $this->connect();
        $this->execute();
        $this->disconnect();
        return $this->response;
    }

    public function getResponse() {
        return $this->response;
    }

}