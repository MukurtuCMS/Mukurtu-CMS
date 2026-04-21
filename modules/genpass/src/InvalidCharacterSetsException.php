<?php

namespace Drupal\genpass;

/**
 * Exception thrown when character sets used to generate a password are invalid.
 *
 * This exception is thrown when the character sets provided to generate
 * a password are too small to be used, with the minimum size being the
 * length of the password.
 */
class InvalidCharacterSetsException extends \Exception {}
