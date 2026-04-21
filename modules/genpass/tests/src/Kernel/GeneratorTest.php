<?php

declare(strict_types=1);

namespace Drupal\Tests\genpass\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\genpass\GenpassPasswordGenerator;
use Drupal\genpass\InvalidCharacterSetsException;

/**
 * Tests functionality of the Genpass Password Generator.
 *
 * @group genpass
 */
class GeneratorTest extends KernelTestBase {

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
  ];

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Password generator service.
   *
   * @var \Drupal\Core\Password\PasswordGeneratorInterface
   */
  protected $passwordGenerator;

  /**
   * Drupal core allowed characters for the password.
   *
   * @var string
   *
   * @see https://git.drupalcode.org/project/drupal/-/blob/11.x/core/lib/Drupal/Core/Password/DefaultPasswordGenerator.php#L20
   */
  protected $coreAllowedChars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

  /**
   * Genpass allowed characters for password. Built from characterSets.
   *
   * @var string
   */
  protected $genpassAllowedChars;

  /**
   * Genpass allowed character sets.
   *
   * @var array
   *
   * @see https://git.drupalcode.org/project/genpass/-/blob/2.0.x/src/GenpassPasswordGenerator.php#L192-L197
   */
  protected $genpassCharacterSets = [
    'lower_letters' => 'abcdefghijkmnopqrstuvwxyz',
    'upper_letters' => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
    'digits' => '23456789',
    'special' => '!"#$%&\'()*+,-./:;<=>?@[\]^_{}~',
  ];

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
    $this->configFactory = $this->container->get('config.factory');

    // Initiate genpassAllowedChars.
    $this->genpassAllowedChars = implode('', $this->genpassCharacterSets);
  }

  /**
   * Tests that the password_generator service is decorated by Genpass.
   */
  public function testGeneratorServiceDecoration() {
    // Returned service password_generator must be the genpass service.
    $this->assertInstanceOf(
      GenpassPasswordGenerator::class,
      $this->passwordGenerator
    );
  }

  /**
   * Test the returned length of passwords.
   */
  public function testGeneratorServicePasswordLength() {
    // Get the module config in read-only mode.
    $config = $this->configFactory->get('genpass.settings');

    // Minimum default password length is at least 12.
    $genpass_length = $config->get('genpass_length');
    $this->assertGreaterThanOrEqual(12, $genpass_length);

    // A generated password without specifying length will be exactly the same.
    $password = $this->passwordGenerator->generate();
    $this->assertEquals($genpass_length, strlen($password));

    // An attempt to generate a password less than 5 will return a password of
    // the configured length silently.
    $shortest_password = $this->passwordGenerator->generate(4);
    $this->assertEquals($genpass_length, strlen($shortest_password));

    // Changing password length returns a password of that length.
    $config = $this->configFactory->getEditable('genpass.settings');
    $config->set('genpass_length', 8);
    $config->save();
    $short_password = $this->passwordGenerator->generate();
    $this->assertEquals(8, strlen($short_password));

    // Asking for a different length password gives that length.
    $longer_password = $this->passwordGenerator->generate(22);
    $this->assertEquals(22, strlen($longer_password));

    // Known upper limit works. May mean different upper limit now.
    $upper_limit = strlen($this->genpassAllowedChars);
    $upper_limit_password = $this->passwordGenerator->generate($upper_limit);
    $this->assertEquals($upper_limit, strlen($upper_limit_password));

    // One over the upper limit fails. May mean different upper limit now.
    $upper_limit_plus_one = $upper_limit + 1;
    $this->expectException(InvalidCharacterSetsException::class);
    // NB: This will throw an exception. MUST be the last call of this test as
    // nothing below this line will be executed. Discard returned value.
    $this->passwordGenerator->generate($upper_limit_plus_one);

    // Do not add code to the end of this test due to expectException above.
    throw new \Exception('This will never be executed.');
  }

  /**
   * Tests that passthru generation to core occurs when configured.
   */
  public function testGeneratorServiceOverride() {
    // Get the module config in editable mode.
    $config = $this->configFactory->getEditable('genpass.settings');

    // Generate a password using GenpassPasswordGenerator.
    $password = $this->passwordGenerator->generate();

    // Check the password length and character set.
    $genpass_length = $config->get('genpass_length');
    $this->assertGreaterThanOrEqual($genpass_length, strlen($password));
    $this->assertTrue($this->isGenpassPassword($password));

    // Intentionally cause the password to fail to make sure the test is ok.
    $password = str_replace(
      str_split($this->genpassCharacterSets['digits'], 1),
      'X',
      $password
    );
    $this->assertFalse($this->isGenpassPassword($password));

    // Configure to pass through to core.
    $config->set('genpass_override_core', FALSE);
    $config->save();

    // Generate a password using Drupal core's PasswordGenerator.
    $password = $this->passwordGenerator->generate();

    // Check the password length and character set.
    $this->assertGreaterThanOrEqual($genpass_length, strlen($password));
    $this->assertTrue($this->isCorePassword($password));

    // Intentionally cause the password to fail to make sure the test is ok.
    $password .= '!!';
    $this->assertFalse($this->isCorePassword($password));
  }

  /**
   * Confirms a password is generated by core password generator.
   *
   * @param string $password
   *   A password to check.
   *
   * @return bool
   *   Returns TRUE if the password only contains core characters.
   */
  protected function isCorePassword($password) {
    // Match all characters except those which are allowed in core.
    $result = preg_match(
      '/[^' . preg_quote($this->coreAllowedChars, '/') . ']/',
      $password
    );

    // A match means there is an unexpected character. Or the preg_match not
    // working is also a failure.
    return !($result == 1 || $result === FALSE);
  }

  /**
   * Confirms a password is generated by genpass password generator.
   *
   * Password will include a character from each set.
   *
   * @param string $password
   *   A password to check.
   *
   * @return bool
   *   Returns TRUE if the password meets genpass generator expected result.
   */
  protected function isGenpassPassword($password) {
    // Match against each set in turn to ensure a match found in it.
    foreach ($this->genpassCharacterSets as $set_characters) {
      $result = preg_match(
        '/[' . preg_quote($set_characters, '/') . ']/',
        $password
      );

      if ($result == 0 || $result === FALSE) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
