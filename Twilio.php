<?php

require_once dirname(__FILE__) . '/' . 'tiny_http.php';

interface DataProxy {
  function receive($key);
  function send($key, $value);
}

abstract class Resource implements DataProxy {
  protected $proxy;
	protected $name;
  public function __construct($name, DataProxy $proxy) {
		$this->name = $name;
    $this->proxy = $proxy;
  }
  public function receive($sid) {
    return $this->proxy->receive("$this->name/$sid");
  }
	public function send($key, $value) {
		throw new ErrorException('not implemented');
	}
}

class ListResource extends Resource {
}

class InstanceResource extends Resource {
  protected $sid;
  protected $object;
  public function __construct($sid, $name, DataProxy $proxy) {
    $this->sid = $sid;
    $this->object = new stdClass;
		$this->object->sid = $sid;
    parent::__construct($name, $proxy);
  }
  public function __set($key, $value) {
    $this->object->$key = $value;
    return parent::__set($key, $value);
  }
  public function __get($key) {
    if (!isset($this->object->$key)) {
      $this->object = $this->proxy->receive($this->sid);
    }
    return $this->object->$key;
  }
}

class TwilioClient extends Resource {
  protected $http;
  protected $version;
  public function __construct(
    $sid,
    $token,
    $version = '2010-04-01',
    $_http = NULL
  ) {
    $this->version = $version;
    $this->http = (NULL === $_http)
      ? new TinyHttp("https://$sid:$token@api.twilio.com", array('debug' => TRUE))
      : $_http;
    $this->accounts = new ListResource('Accounts', $this);
    $this->account = new InstanceResource($sid, 'Account', $this->accounts);
  }
  public function receive($path) {
    list($status, $headers, $body) =
      $this->http->get("/$this->version/$path.json");
    if (200 <= $status && $status < 300) {
      if ($headers['Content-Type'] == 'application/json') {
        return json_decode($body);
      } else throw new ErrorException('not json');
    } else throw new ErrorException("$status: $body");
  }
}
