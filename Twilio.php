<?php

require_once dirname(__FILE__) . '/' . 'tiny_http.php';

interface DataProxy {
  function send($key, $value);
  function receive($key, array $params = array());
}

abstract class Resource implements DataProxy {
  protected $proxy;
  protected $name;
  public function __construct($name, DataProxy $proxy) {
    $this->name = $name;
    $this->proxy = $proxy;
  }
  public function receive($sid, array $params = array()) {
    return $this->proxy->receive("$this->name/$sid", $params);
  }
  public function send($key, $value) {
    throw new ErrorException('not implemented');
  }
  public static function decamelize($word) {
    return preg_replace(
      '/(^|[a-z])([A-Z])/e',
      'strtolower(strlen("\\1") ? "\\1_\\2" : "\\2")',
      $word
    );
  }
  public static function camelize($word) {
    return preg_replace('/(^|_)([a-z])/e', 'strtoupper("\\2")', $word);
  }
}

class ListResource extends Resource {
  public function get($sid) {
    return new InstanceResource($sid, $this->getInstanceName(), $this);
  }

  public function getList(array $params = array()) {
    $page = $this->proxy->receive($this->name, $params);
    $schema = $this->getSchema();
    $name = $schema['list'];
    return $page->$name;
  }

  public function getInstanceName() {
    return substr($this->name, 0, -1);
  }

  public function getSchema() {
    return array(
      'name' => $this->name,
      'basename' => $this->name,
      'list' => self::decamelize($this->name),
    );
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
  public function setObject($object) {
    $this->object = $object;
  }
  public function __get($key) {
    if (!isset($this->object->$key)) {
      $this->load($key);
    }
    return isset($this->$key)
      ? $this->$key
      : (
        isset($this->object->$key)
        ? $this->object->$key
        : NULL
      );
  }
  public function receive($path, array $params = array()) {
    return $this->proxy->receive("$this->sid/$path", $params);
  }
  private function load($key) {
    $this->object = $this->proxy->receive($this->sid);
    if (empty($this->object->subresource_uris)) return;
    foreach ($this->object->subresource_uris as $res => $uri) {
      $type = self::camelize($res);
      $this->$res = class_exists($type)
        ? new $type($this)
        : new ListResource($type, $this);
    }
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
  public function receive($path, array $params = array()) {
    list($status, $headers, $body) = empty($params)
      ? $this->http->get("/$this->version/$path.json")
      : $this->http->get("/$this->version/$path.json?"
        . http_build_query($params, '', '&'));
    if (200 <= $status && $status < 300) {
      if ($headers['Content-Type'] == 'application/json') {
        $object = json_decode($body);
        //var_export($object);
        return $object;
      } else throw new ErrorException('not json');
    } else throw new ErrorException("$status: $body");
  }
}

class SmsMessages extends ListResource {
  public function __construct(DataProxy $proxy) {
    parent::__construct('SMS/Messages', $proxy);
  }
  public function getSchema() {
    return array(
      'class' => 'SmsMessages',
      'basename' => 'SMS/Messages',
      'list' => 'sms_messages',
    );
  }
}

class SmsMessage extends InstanceResource {
  public function __construct($sid, SmsMessages $list) {
    parent::__construct($sid, 'Sms/Message', $list);
  }
}
