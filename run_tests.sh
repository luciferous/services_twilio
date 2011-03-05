#!/bin/sh

phpunit -ddisplay_errors=1 --bootstrap Twilio.php \
  tests/TwilioTest.php

phpunit -ddisplay_errors=1 --bootstrap Twiml.php \
  tests/TwimlTest.php
