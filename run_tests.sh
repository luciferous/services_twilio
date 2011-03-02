#!/bin/sh

phpunit -ddisplay_errors=1 --bootstrap Twilio.php \
  tests/TwilioTest.php
