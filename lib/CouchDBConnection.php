<?php

/*
 * The Gateway class to connect through couchdb
 *
 * Version: 1.0
 * By: alexserver
 *
 */
class CouchDBConnection extends CComponent {

    private $host;
    private $port;
    private $db;
    private $username;
    private $password;
    private $ssl;

    protected $defaultProperties = array(
        'host'=>'localhost',
        'port'=>5984,
        'db'=>null,
        'username'=>null,
        'password'=>null,
        'ssl'=>false,
    );

    function __construct(array $config=array()) {
        foreach ($config as $key => $value) {
            $this->$key = ($value!=null && $value!=='') ? $value : $this->defaultProperties[$key];
        }
    }

    /*
     *  This is the main query sent to couchdb
     *
     */
    public function rawsend($url, $method = 'get', $data = NULL) {
        //$url must be /dbname/url
        $url = (preg_match('/^\//', $url) ? $url : '/'.$url);
        if (gettype($data)=="array" || gettype($data)=="object") {
            $data = json_encode($data);
        }
        $request = new CouchDBRequest(array(
            'host'=> $this->host,
            'port'=> $this->port,
            'url'=> $url,
            'method'=> $method,
            'data'=> $data,
            'username'=> $this->username,
            'password'=> $this->password,
            'ssl'=>$this->ssl,
        ));
        return $request->send();
    }

    public function send($url, $method = 'get', $data = NULL) {
        $url = '/'.$this->db.(preg_match('/^\//', $url) ? $url : '/'.$url);
        return $this->rawsend($url, $method, $data);
    }

    public function get($query) {
        $response = $this->send($query,'GET');
        return $response->getBody(true);
    }

    public function put($id, $data) {
        $response = $this->send("/".$id, 'PUT', $data);
        return $response->getBody(true);
    }

    public function post($id, $data) {
        $response = $this->send("/".$id, 'POST', $data);
        return $response->getBody(true);
    }

    public function exists($id) {
        $response = $this->send("/".$id,'GET');
        $response = $response->getBody(true);
        return (isset($response['error']));
    }

    public function getUUID($count = 1) {
        $url = "/_uuids".(($count>1)? ("?count=".$count) : "");
        $response = $this->rawsend($url,'GET');
        $response =$response->getBody(true);
        if(!isset($response['error'])) {
            $result = ($count==1)? $response['uuids'][0] : $response['uuids'];
        }
        else {
            $result = null;
        }
        return $result;
    }

    public function getDocument($id) {
        return $this->get('/'.$id);
    }

    public function getAllDocuments($view='', $include_docs=false) {
        $url = ($view)? $view : '/_all_docs';
        $url = $url.($include_docs ? '?include_docs=true':'');
        $response = $this->send($url,'GET');
        $response = $response->getBody(true);
        $docs = array();
        if (!isset($response['error'])) {
            if ($include_docs) {
                foreach ($response['rows'] as $key => $value) {
                    $docs[] = $value['doc'];
                }
            }
            else {
                $docs = $response['rows'];
            }
        }
        else {
            throw new Exception("Error: ".$response['error']);
        }
        return $docs;
    }

    public function saveDocument($data=array()) {
        //if $data is string, do a json_decode
        if (gettype($data)=="string") {
            $data = json_decode($data, true);
        }
        elseif(gettype($data)=="object") {
            //ensure we are treating with an associative array.
            $data = (array) $data;
        }
        if(!isset($data['_id'])) {
            // get uuid
            $data['_id'] = $this->getUUID();
        }
        // store data
        $response = $this->put($data['_id'], $data);
        return $response;
    }

    public function deleteDocument($id) {
        $doc = $this->getDocument($id);
        if (isset($doc['error'])) {
            throw new Exception("the id: {$id} doesn't exist");
        }
        $url = "/".$id."?rev=".$doc['_rev'];
        $response = $this->send($url,'DELETE');
        return $response->getBody(true);
    }

    public function saveBulkDocuments(array $documents=array()) {
        //TODO >> in order to improve, check the count of docs and set a limit, this will be useful for largerarrays
        if (gettype($documents)=='array' && count($documents)>0) {
            //create the url and build the json object
            $url = '/_bulk_docs';
            //get the ids
            $ids = $this->getUUID(count($documents));
            foreach ($documents as $k=>$v) {
                if (!isset($documents[$k]['_id'])) {
                    $documents[$k]['_id'] = array_shift($ids);
                }
            }
            $data = array(
                'docs'=>$documents,
            );
            $response = $this->post($url, $data);
        }
        else {
            $response = false;
        }
        return $response;
    }

    public function deleteBulkDocuments(array $documents=array()) {
        //TODO >> check the count of docs as same as with saveBulkDocuments
        if (gettype($documents)=='array' && count($documents)>0) {
            foreach ($documents as $k => $v) {
                $documents[$k]['_deleted'] = true;
            }
            $response = $this->saveBulkDocuments($documents);
        }
        else {
            $response = false;
        }
        return $response;
    }

    public function getViewDesign($designId) {
        $url = "/_design/".$designId;
        $response = $this->get($url);
        if (!isset($response['error'])) {
            $result = $response['views'];
        }
        else {
            $result = null;
        }
        return $result;
    }

    public function updateViewDesign($designId, $data) {
        $url = "/_design/".$designId;
        //read views in string mode in order to compare with new data
        $viewDesign = $this->get($url);
        //if view doesnt exist then we need to create it by the first time
        if (isset($viewDesign['error'])) {
            $viewDesign = array(
                '_id'=>$url,
            );
        }
        else {
            //if exists, we need to compare before it have to be saved
            if (json_encode($viewDesign['views']) == json_encode($data)) {
                return false;
            }
        }
        $viewDesign['views'] = $data;
        $response = $this->put($url, $viewDesign);
        if (isset($response['error'])) {
            return false;
        }
        else {
            return $response;
        }

    }
}