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
	public function get($sid) {
		return new InstanceResource($sid, substr($this->name, 0, -1), $this);
	}
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
  public function __get($key) {
    if (!isset($this->object->$key)) {
      $this->object = $this->proxy->receive($this->sid);
			if (isset($this->object->subresource_uris)) {
				foreach ($this->object->subresource_uris as $res => $uri) {
					$type = basename($uri, '.' . pathinfo($uri, PATHINFO_EXTENSION));
					$name = strtolower($type);
					$this->$name = new ListResource($type, $this);
				}
			}
    }
		return isset($this->$key)
			? $this->$key
			: (
				isset($this->object->$key)
				? $this->object->$key
				: NULL
			);
  }
	public function receive($path) {
    return $this->proxy->receive("$this->sid/$path");
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
        $object = json_decode($body);
				//var_export($object);
				return $object;
      } else throw new ErrorException('not json');
    } else throw new ErrorException("$status: $body");
  }
}
