<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests _mukurtu_multilingual_apply_language_negotiation_defaults().
 *
 * @see _mukurtu_multilingual_apply_language_negotiation_defaults()
 */
#[Group('mukurtu_multilingual')]
class LanguageNegotiationDefaultsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['language']);

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_multilingual');
    require_once $module_path . '/mukurtu_multilingual.install';
  }

  /**
   * Applying the defaults sets the expected negotiation weights.
   */
  public function testAppliesNegotiationDefaults(): void {
    _mukurtu_multilingual_apply_language_negotiation_defaults();

    $config = $this->config('language.types');
    $this->assertSame(['language_interface', 'language_content'], $config->get('configurable'));
    $this->assertSame([
      'language-url' => -8,
      'language-interface' => 9,
      'language-selected' => 12,
    ], $config->get('negotiation.language_content.enabled'));
    $this->assertSame([
      'language-user-admin' => -10,
      'language-url' => -8,
      'language-user' => -4,
      'language-selected' => 12,
    ], $config->get('negotiation.language_interface.enabled'));
  }

  /**
   * Running it twice does not error and stays converged.
   */
  public function testIsIdempotent(): void {
    _mukurtu_multilingual_apply_language_negotiation_defaults();
    _mukurtu_multilingual_apply_language_negotiation_defaults();

    $config = $this->config('language.types');
    $this->assertSame(['language_interface', 'language_content'], $config->get('configurable'));
  }

  /**
   * Drifted settings (e.g. a site administrator changed the weights) are
   * reconverged to the Mukurtu defaults.
   */
  public function testReconvergesDriftedSettings(): void {
    $this->config('language.types')
      ->set('negotiation.language_content.enabled', ['language-url' => 0])
      ->save();

    _mukurtu_multilingual_apply_language_negotiation_defaults();

    $config = $this->config('language.types');
    $this->assertSame([
      'language-url' => -8,
      'language-interface' => 9,
      'language-selected' => 12,
    ], $config->get('negotiation.language_content.enabled'));
  }

}
