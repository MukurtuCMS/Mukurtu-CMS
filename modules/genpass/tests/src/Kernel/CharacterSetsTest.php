<?php

declare(strict_types=1);

namespace Drupal\Tests\genpass\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\genpass\InvalidCharacterSetsException;

/**
 * Tests functionality of altering the Character Sets of Genpass passwords.
 *
 * @group genpass
 */
class CharacterSetsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'genpass',
    'genpass_test',
  ];

  /**
   * Password generator service.
   *
   * @var \Drupal\Core\Password\PasswordGeneratorInterface
   */
  protected $passwordGenerator;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Add config for genpass.
    $this->installConfig([
      'genpass',
    ]);

    // Required services.
    $this->passwordGenerator = $this->container->get('password_generator');
  }

  /**
   * Test that character sets caching is working.
   */
  public function testGeneratorCharSetsCache() {
    // Initiate cached values. Uses decorated public method.
    $this->passwordGenerator->initCharacterSets(12);

    // Wipe out static cache. Uses decorated public method.
    $this->passwordGenerator->clearInternalStatics();

    // Generate a password, causing initCharacterSets result to be cached.
    $this->passwordGenerator->generate();

    // The alter call should be been made once now.
    $this->assertEquals(1, genpass_test_charset_get_alter_count());

    // Generate another password, causing initCharacterSets to used cached val.
    $this->passwordGenerator->generate();

    // The alter call should be still be one.
    $this->assertEquals(1, genpass_test_charset_get_alter_count());
  }

  /**
   * Test that altering the character sets into a string causes an exception.
   */
  public function testGeneratorCharSetsAlterBadArray() {
    // Set alter mode to fail with an exception: not array.
    genpass_test_charset_set_alter_mode('not_array');

    // Trigger the call which will result in exception.
    $this->expectException(InvalidCharacterSetsException::class);
    $this->passwordGenerator->generate();
  }

  /**
   * Test that altering the char sets to be too short causes an exception.
   */
  public function testGeneratorCharSetsAlterShortArray() {
    // Set alter mode to fail with an exception: not array.
    genpass_test_charset_set_alter_mode('too_short');

    // Trigger the call which will result in exception.
    $this->expectException(InvalidCharacterSetsException::class);
    $this->passwordGenerator->generate();
  }

}
