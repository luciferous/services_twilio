<?php

use \Mockery as m;
require_once 'Mockery/Loader.php';
$loader = new m\Loader;
$loader->register();

class TwilioTest extends PHPUnit_Framework_TestCase {
  function tearDown() {
    m::close();
  }

  function testNeedsRefining() {
    $account = (object) array(
      'sid' => 'AC123',
      'friendly_name' => 'Robert Paulson',
    );
    $http = m::mock();
    $http->shouldReceive('get')
      ->once()
      ->with('/2010-04-01/Accounts/AC123.json')
      ->andReturn(array(
        200,
        array('Content-Type' => 'application/json'),
        json_encode($account)
      ));

    $client = new TwilioClient('AC123', '123', '2010-04-01', $http);
    $this->assertEquals('AC123', $client->account->sid);
    $this->assertEquals('Robert Paulson', $client->account->friendly_name);
  }

  function testAccessSidAvoidsNetworkCall() {
    $http = m::mock();
    $http->shouldReceive('get')->never();
    $client = new TwilioClient('AC123', '123', '2010-04-01', $http);
    $client->account->sid;
  }

  function testObjectLoadsOnlyOnce() {
    $account = (object) array(
      'sid' => 'AC123',
      'friendly_name' => 'Robert Paulson',
      'status' => 'active',
    );
    $http = m::mock();
    $http->shouldReceive('get')
      ->once()
      ->with('/2010-04-01/Accounts/AC123.json')
      ->andReturn(array(
        200,
        array('Content-Type' => 'application/json'),
        json_encode($account)
      ));
    $client = new TwilioClient('AC123', '123', '2010-04-01', $http);
    $client->account->friendly_name;
    $client->account->friendly_name;
    $client->account->status;
  }

  function testSubresourceLoad() {
    $acct = new stdClass;
    $call = new stdClass;
    $acct->subresource_uris->calls = '/2010-04-01/Accounts/AC123/Calls.json';
    $call->status = 'Completed';

    $http = m::mock();
    $http->shouldReceive('get')
      ->once()
      ->with('/2010-04-01/Accounts/AC123.json')
      ->andReturn(array(
        200,
        array('Content-Type' => 'application/json'),
        json_encode($acct)
      ));
    $http->shouldReceive('get')
      ->once()
      ->with('/2010-04-01/Accounts/AC123/Calls/CA123.json')
      ->andReturn(array(
        200,
        array('Content-Type' => 'application/json'),
        json_encode($call)
      ));

    $client = new TwilioClient('AC123', '123', '2010-04-01', $http);
    $this->assertEquals('Completed', $client->account->calls->get('CA123')->status);
  }

  function testSubresourceSubresource() {
    $acct = new stdClass;
    $call = new stdClass;
    $notif = new stdClass;
    $acct->subresource_uris->calls = '/2010-04-01/Accounts/AC123/Calls.json';
    $call->status = 'Completed';
    $call->subresource_uris->notifications = '/2010-04-01/Accounts/AC123/Calls/CA123/Notifications.json';
    $notif->message_text = 'Foo';

    $http = m::mock();
    $http->shouldReceive('get')
      ->once()
      ->with('/2010-04-01/Accounts/AC123.json')
      ->andReturn(array(
        200,
        array('Content-Type' => 'application/json'),
        json_encode($acct)
      ));
    $http->shouldReceive('get')
      ->once()
      ->with('/2010-04-01/Accounts/AC123/Calls/CA123.json')
      ->andReturn(array(
        200,
        array('Content-Type' => 'application/json'),
        json_encode($call)
      ));
    $http->shouldReceive('get')
      ->once()
      ->with('/2010-04-01/Accounts/AC123/Calls/CA123/Notifications/NO123.json')
      ->andReturn(array(
        200,
        array('Content-Type' => 'application/json'),
        json_encode($notif)
      ));

    $client = new TwilioClient('AC123', '123', '2010-04-01', $http);
    $this->assertEquals('Foo',
      $client->account->calls->get('CA123')->notifications->get('NO123')->message_text);
  }

  function testListResource() {
    $acct = new stdClass;
    $acct->subresource_uris->calls = '/2010-04-01/Accounts/AC123/Calls.json';
    $page = new stdClass;
    $page->calls = array((object) array('status' => 'Completed'));

    $http = m::mock();
    $http->shouldReceive('get')
      ->once()
      ->with('/2010-04-01/Accounts/AC123.json')
      ->andReturn(array(
        200,
        array('Content-Type' => 'application/json'),
        json_encode($acct)
      ));
    $http->shouldReceive('get')
      ->once()
      ->with('/2010-04-01/Accounts/AC123/Calls.json')
      ->andReturn(array(
        200,
        array('Content-Type' => 'application/json'),
        json_encode($page)
      ));
    $client = new TwilioClient('AC123', '123', '2010-04-01', $http);
    $call = current($client->account->calls->getList());
    $this->assertEquals('Completed', $call->status);
  }

  function testAsymmetricallyNamedResources() {
    $acct = new stdClass;
    $acct->subresource_uris->sms_messages = '/2010-04-01/Accounts/AC123/SMS/Messages.json';
    $page = new stdClass;
    $page->sms_messages = array((object) array('status' => 'sent'));
    $http = m::mock();
    $http->shouldReceive('get')
      ->once()
      ->with('/2010-04-01/Accounts/AC123.json')
      ->andReturn(array(
        200,
        array('Content-Type' => 'application/json'),
        json_encode($acct)
      ));
    $http->shouldReceive('get')
      ->once()
      ->with('/2010-04-01/Accounts/AC123/SMS/Messages.json')
      ->andReturn(array(
        200,
        array('Content-Type' => 'application/json'),
        json_encode($page)
      ));
    $client = new TwilioClient('AC123', '123', '2010-04-01', $http);
    $sms = current($client->account->sms_messages->getList());
    $this->assertEquals('sent', $sms->status);
  }

  function testParams() {
    $http = m::mock();
    $http->shouldReceive('get')
      ->with('/2010-04-01/Accounts.json?FriendlyName=foo&Status=active')
      ->andReturn(array(
        200,
        array('Content-Type' => 'application/json'),
        '{"accounts":[]}'
      ));
    $client = new TwilioClient('AC123', '123', '2010-04-01', $http);
    $client->accounts->getList(array(
      'FriendlyName' => 'foo',
      'Status' => 'active',
    ));
  }

  function testUpdate() {
    $acct = new stdClass;
    $acct->subresource_uris->calls = '/2010-04-01/Accounts/AC123/Calls.json';
    $http = m::mock();
    $http->shouldReceive('get')->once()
      ->with('/2010-04-01/Accounts/AC123.json')
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        json_encode($acct)
      ));
    $http->shouldReceive('post')->once()
      ->with('/2010-04-01/Accounts/AC123/Calls.json', m::any(), m::any())
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        '{"sid":"CA123"}'
      ));
    $client = new TwilioClient('AC123', '123', '2010-04-01', $http);
    $client->account->calls->create('123', '123', 'http://example.com');
  }

  function testModifyLiveCall() {
    $acct = new stdClass;
    $acct->subresource_uris->calls = '/2010-04-01/Accounts/AC123/Calls.json';
    $http = m::mock();
    $http->shouldReceive('get')->once()
      ->with('/2010-04-01/Accounts/AC123.json')
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        json_encode($acct)
      ));
    $http->shouldReceive('post')->once()
      ->with('/2010-04-01/Accounts/AC123/Calls.json', m::any(), m::any())
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        '{"sid":"CA123"}'
      ));
    $http->shouldReceive('post')->once()
      ->with(
        '/2010-04-01/Accounts/AC123/Calls/CA123.json',
        m::any(),
        'Status=completed'
      )
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        '{"sid":"CA123"}'
      ));
    $client = new TwilioClient('AC123', '123', '2010-04-01', $http);
    $call = $client->account->calls->create('123', '123', 'http://example.com');
    $call->hangup();
  }

  //function testAccessingNonExistentPropertiesErrorsOut
}
