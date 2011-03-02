<?php

require_once 'tiny_http.php';

class TwilioClient extends Resource {
  protected $http;
  protected $version;
  public function __construct(
    $sid,
    $token,
    $ver = '2010-04-01',
    $_http = NULL
  ) {
    $this->version = $ver;
    $this->http = (NULL === $_http)
      ? new TinyHttp("https://$sid:$token@api.twilio.com", array('debug' => TRUE))
      : $_http;
    $this->accounts = new ListResource('Accounts', $this);
    $this->account = new InstanceResource($sid, $this->accounts);
  }
  public function get($path) {
    list($status, $headers, $body) =
      $this->http->get("/$this->version/$path.json");
    if (200 <= $status && $status < 300) {
      if ($headers['Content-Type'] == 'application/json') {
        return json_decode($body);
      } else throw new ErrorException('not json');
    } else throw new ErrorException("$status: $body");
  }
}

class DataProxy {
  public function get($key) { throw new ErrorException; }
  public function set($key, $value) { throw new ErrorException; }
}

class Resource extends DataProxy {
  protected $proxy;
  public function __construct(DataProxy $proxy) {
    $this->proxy = $proxy;
  }
}

class ListResource extends DataProxy {
  public function __construct($name, $proxy) {
    $this->name = $name;
    $this->proxy = $proxy;
  }
  public function get($sid) {
    return $this->proxy->get("$this->name/$sid");
  }
}

class InstanceResource extends Resource {
  protected $sid;
  protected $object;
  public function __construct($sid, DataProxy $proxy) {
    $this->sid = $sid;
    $this->object = new stdClass;
    parent::__construct($proxy);
  }
  public function __set($key, $value) {
    $this->object->$key = $value;
    return parent::__set($key, $value);
  }
  public function __get($key) {
    if (!isset($this->object->$key)) {
      $this->object = $this->proxy->get($this->sid);
    }
    return $this->object->$key;
  }
}
