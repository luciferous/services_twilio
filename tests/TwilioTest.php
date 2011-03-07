<?php

use \Mockery as m;

require_once 'Services/Twilio.php';

class TwilioTest extends PHPUnit_Framework_TestCase {
  function tearDown() {
    m::close();
  }
  function testNeedsRefining() {
    $http = m::mock();
    $http->shouldReceive('get')->once()
      ->with('/2010-04-01/Accounts/AC123.json')
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        json_encode(array(
          'sid' => 'AC123',
          'friendly_name' => 'Robert Paulson',
        ))
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
    $http = m::mock();
    $http->shouldReceive('get')->once()
      ->with('/2010-04-01/Accounts/AC123.json')
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        json_encode(array(
          'sid' => 'AC123',
          'friendly_name' => 'Robert Paulson',
          'status' => 'active',
        ))
      ));
    $client = new TwilioClient('AC123', '123', '2010-04-01', $http);
    $client->account->friendly_name;
    $client->account->friendly_name;
    $client->account->status;
  }

  function testSubresourceLoad() {
    $http = m::mock();
    $http->shouldReceive('get')->once()
      ->with('/2010-04-01/Accounts/AC123.json')
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        json_encode(array(
          'subresource_uris' =>
          array('calls' => '/2010-04-01/Accounts/AC123/Calls.json',
          )
        ))
      ));
    $http->shouldReceive('get')->once()
      ->with('/2010-04-01/Accounts/AC123/Calls/CA123.json')
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        json_encode(array('status' => 'Completed'))
      ));

    $client = new TwilioClient('AC123', '123', '2010-04-01', $http);
    $this->assertEquals(
      'Completed',
      $client->account->calls->get('CA123')->status
    );
  }

  function testSubresourceSubresource() {
    $http = m::mock();
    $http->shouldReceive('get')->once()
      ->with('/2010-04-01/Accounts/AC123.json')
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        json_encode(array(
          'subresource_uris' =>
          array('calls' => '/2010-04-01/Accounts/AC123/Calls.json')
        ))
      ));
    $http->shouldReceive('get')->once()
      ->with('/2010-04-01/Accounts/AC123/Calls/CA123.json')
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        json_encode(array(
          'status' => 'Completed',
          'subresource_uris' => array(
            'notifications' =>
            '/2010-04-01/Accounts/AC123/Calls/CA123/Notifications.json'
          )
        ))
      ));
    $http->shouldReceive('get')->once()
      ->with('/2010-04-01/Accounts/AC123/Calls/CA123/Notifications/NO123.json')
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        json_encode(array('message_text' => 'Foo'))
      ));

    $client = new TwilioClient('AC123', '123', '2010-04-01', $http);
    $notifs = $client->account->calls->get('CA123')->notifications;
    $this->assertEquals('Foo', $notifs->get('NO123')->message_text);
  }

  function testListResource() {
    $http = m::mock();
    $http->shouldReceive('get')->once()
      ->with('/2010-04-01/Accounts/AC123.json')
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        json_encode(array(
          'subresource_uris' =>
          array('calls' => '/2010-04-01/Accounts/AC123/Calls.json')
        ))
      ));
    $http->shouldReceive('get')->once()
      ->with('/2010-04-01/Accounts/AC123/Calls.json?Page=0&PageSize=10')
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        json_encode(array(
          'total' => 1,
          'calls' => array(array('status' => 'Completed'))
        ))
      ));
    $client = new TwilioClient('AC123', '123', '2010-04-01', $http);
    $page = $client->account->calls->getPage(0, 10);
    $call = current($page->getItems());
    $this->assertEquals('Completed', $call->status);
    $this->assertEquals(1, $page->total);
  }

  function testAsymmetricallyNamedResources() {
    $http = m::mock();
    $http->shouldReceive('get')->once()
      ->with('/2010-04-01/Accounts/AC123.json')
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        json_encode(array(
          'subresource_uris' => array(
            'sms_messages' => '/2010-04-01/Accounts/AC123/SMS/Messages.json')
        ))
      ));
    $http->shouldReceive('get')->once()
      ->with('/2010-04-01/Accounts/AC123/SMS/Messages.json?Page=0&PageSize=10')
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        json_encode(array('sms_messages' => array(array('status' => 'sent'))))
      ));
    $client = new TwilioClient('AC123', '123', '2010-04-01', $http);
    $sms = current($client->account->sms_messages->getPage()->getItems());
    $this->assertEquals('sent', $sms->status);
  }

  function testParams() {
    $http = m::mock();
    $qs = 'Page=0&PageSize=10&FriendlyName=foo&Status=active';
    $http->shouldReceive('get')
      ->with('/2010-04-01/Accounts.json?' . $qs)
      ->andReturn(array(
        200,
        array('Content-Type' => 'application/json'),
        '{"accounts":[]}'
      ));
    $client = new TwilioClient('AC123', '123', '2010-04-01', $http);
    $client->accounts->getPage(0, 10, array(
      'FriendlyName' => 'foo',
      'Status' => 'active',
    ));
  }

  function testUpdate() {
    $http = m::mock();
    $http->shouldReceive('get')->once()
      ->with('/2010-04-01/Accounts/AC123.json')
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        json_encode(array(
          'subresource_uris' =>
          array('calls' => '/2010-04-01/Accounts/AC123/Calls.json')
        ))
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
    $http = m::mock();
    $http->shouldReceive('get')->once()
      ->with('/2010-04-01/Accounts/AC123.json')
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        json_encode(array(
          'subresource_uris' =>
          array('calls' => '/2010-04-01/Accounts/AC123/Calls.json')
        ))
      ));
    $http->shouldReceive('post')->once()
      ->with('/2010-04-01/Accounts/AC123/Calls.json', m::any(), m::any())
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        '{"sid":"CA123"}'
      ));
    $http->shouldReceive('post')->once()
      ->with('/2010-04-01/Accounts/AC123/Calls/CA123.json', m::any(),
        'Status=completed')
      ->andReturn(array(200, array('Content-Type' => 'application/json'),
        '{"sid":"CA123"}'
      ));
    $client = new TwilioClient('AC123', '123', '2010-04-01', $http);
    $calls = $client->account->calls;
    $calls->create('123', '123', 'http://example.com')->hangup();
  }

  //function testAccessingNonExistentPropertiesErrorsOut
}

// vim: ai ts=2 sw=2 noet sta
