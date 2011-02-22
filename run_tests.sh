#!/bin/sh

phpunit -ddisplay_errors=1 --bootstrap Twilio_Client.php \
  tests/Twilio_UnitTests.php
