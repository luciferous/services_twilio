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
}
