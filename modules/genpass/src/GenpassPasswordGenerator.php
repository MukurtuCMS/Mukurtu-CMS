<?php

namespace Drupal\genpass;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides the genpass password generator.
 */
class GenpassPasswordGenerator implements PasswordGeneratorInterface {

  /**
   * Character sets used to generate the password. Can be altered by hook.
   *
   * @var array
   */
  protected $characterSets = NULL;

  /**
   * The allowed characters for the password, combined from characterSets.
   *
   * @var string
   */
  protected $allowedChars = NULL;

  /**
   * Constructs a new GenpassPasswordGenerator object.
   */
  public function __construct(
    protected PasswordGeneratorInterface $corePasswordGenerator,
    #[Autowire(service: 'cache.default')]
    protected CacheBackendInterface $cache,
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * Generates a password from characters sets of requested length.
   *
   * If not enabled, the inner service with be used to generate the password
   * with the addition of using the configured password length.
   * When Genpass override enabled, password is guaranteed to have at least one
   * character from each character set. Minimum password length is the number of
   * character sets used or 5, whichever is the greater.
   *
   * @param int $length
   *   (optional) The length of the password.
   *
   * @return string
   *   The generated password.
   *
   * @throws Drupal\genpass\InvalidCharacterSetsException
   *   After allowing other modules to alter the character sets, an error is
   *   thrown if they are no longer viable for random passwords in a very
   *   simplistic way & check.
   */
  public function generate(int $length = -1): string {

    // If the length provided is below minimum length, like if a value was not
    // provided, or someone has requested one below 5 chars, use config length.
    $settings = $this->configFactory->get('genpass.settings');
    if ($length < 5) {
      $length = $settings->get('genpass_length') ?? 12;
    }

    // If Genpass is not configured to be the generator to use, fall back to
    // using the inner service.
    if (!$settings->get('genpass_override_core')) {
      return $this->corePasswordGenerator->generate($length);
    }

    // Initialize characterSets and allowedChars if not yet done.
    $this->initCharacterSets($length);

    // Always include at least 1 character of each class to ensure generated
    // passwords meet simplistic password strength rules.
    $password = [];
    foreach ($this->characterSets as $character_set) {
      $max = strlen($character_set) - 1;
      $password[] = $character_set[random_int(0, $max)];
    }

    // Add remaining length as characters from any set.
    $max = strlen($this->allowedChars) - 1;

    for ($c = count($password); $c < $length; $c++) {
      $password[] = $this->allowedChars[random_int(0, $max)];
    }

    // Shuffle the characters around to avoid the first 4 chars always being
    // the same four character sets in order. Using shuffle() will suffice as
    // the contents of the array are already random.
    shuffle($password);

    return implode('', $password);
  }

  /**
   * Initialise and allow altering of the allowedChars for passwords.
   *
   * @param int $length
   *   The length of password being requested so that minimum can be checked.
   *
   * @throws Drupal\genpass\InvalidCharacterSetsException
   *   After allowing other modules to alter the character sets, an error is
   *   thrown if they are no longer viable for random passwords in a very
   *   simplistic way & check.
   */
  protected function initCharacterSets(int $length): void {

    // Check if already setup.
    if (!empty($this->allowedChars)) {
      // Must check length is sane if different from initial setup.
      if (strlen($this->allowedChars) < $length) {
        throw new InvalidCharacterSetsException(
          'Not enough source characters to generate a password.'
        );
      }

      return;
    }

    // Use cached character sets if possible. Using cache.default as this is
    // not a critical or often called, and discovery is of limited size.
    $cid = 'genpass:character_sets';
    if (($item = $this->cache->get($cid, FALSE)) !== FALSE) {

      // Set values from cache.
      $this->characterSets = $item->data;
      $this->allowedChars = implode('', $this->characterSets);

      // Re-run init with values set to cause length exception if needed.
      $this->initCharacterSets($length);

      // Use the cached values.
      return;
    }

    // The allowed characters for the password divided into classes. Note that
    // the number 0 and the letter 'O' have been removed to avoid confusion
    // between the two. The same is true of 'I', 1, and 'l'. The symbols `
    // and | are likewise excluded. More special characters are included here
    // than in Drupal\Core\Password\DefaultPasswordGenerator. Always start with
    // a known set of characters instead of the current state of the service.
    $character_sets = [
      'lower_letters' => 'abcdefghijkmnopqrstuvwxyz',
      'upper_letters' => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
      'digits' => '23456789',
      'special' => '!"#$%&\'()*+,-./:;<=>?@[\]^_{}~',
    ];

    // Allow another module to alter the character sets before they are used
    // to generate a password. Do sanity checks on the altered characters sets.
    $this->moduleHandler->alter(
      'genpass_character_sets',
      $character_sets
    );

    // Do sanity checks on the altered characters sets.
    if (!is_array($character_sets)) {
      throw new InvalidCharacterSetsException(
        'Characters sets altered to longer be a keyed array of character sets.'
      );
    }
    $allowed_chars = implode('', $character_sets);
    if (strlen($allowed_chars) < $length) {
      throw new InvalidCharacterSetsException(
        'Not enough source characters to generate a password.'
      );
    }

    // Set internal service state.
    $this->characterSets = $character_sets;
    $this->allowedChars = $allowed_chars;

    // Cache character sets.
    $tags = ['genpass'];
    $this->cache->set(
      $cid,
      $this->characterSets,
      CacheBackendInterface::CACHE_PERMANENT,
      $tags
    );
  }

}
