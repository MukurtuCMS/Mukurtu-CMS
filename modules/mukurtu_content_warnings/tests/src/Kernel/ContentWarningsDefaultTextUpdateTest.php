<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_content_warnings\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests mukurtu_content_warnings_update_40801().
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1301
 */
class ContentWarningsDefaultTextUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'mukurtu_content_warnings',
    'system',
  ];

  const OLD_SINGLE = 'Warning: [name] is deceased. Click through to access content.';
  const OLD_MULTIPLE = 'Warning: The following people are deceased. Click through to access content. [names]';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('module_handler')->loadInclude('mukurtu_content_warnings', 'install');
  }

  /**
   * Text still at the old defaults moves to the new defaults.
   */
  public function testOldDefaultsAreReplaced(): void {
    $this->setPeopleWarnings(self::OLD_SINGLE, self::OLD_MULTIPLE);
    $message = (string) mukurtu_content_warnings_update_40801();

    $config = $this->config('mukurtu_content_warnings.settings');
    $this->assertSame('[name] is deceased.', $config->get('people_warnings.warning_single'));
    $this->assertSame('The following people are deceased: [names]', $config->get('people_warnings.warning_multiple'));
    $this->assertStringStartsWith('Updated', $message);
  }

  /**
   * Customized text is kept; only the setting still at the default changes.
   */
  public function testCustomizedTextIsKept(): void {
    $this->setPeopleWarnings('[name] has passed on.', self::OLD_MULTIPLE);
    mukurtu_content_warnings_update_40801();

    $config = $this->config('mukurtu_content_warnings.settings');
    $this->assertSame('[name] has passed on.', $config->get('people_warnings.warning_single'));
    $this->assertSame('The following people are deceased: [names]', $config->get('people_warnings.warning_multiple'));
  }

  /**
   * Nothing is saved when every setting was customized.
   */
  public function testAllCustomizedReportsKept(): void {
    $this->setPeopleWarnings('[name] has passed on.', 'These people have passed on: [names]');
    $message = (string) mukurtu_content_warnings_update_40801();

    $config = $this->config('mukurtu_content_warnings.settings');
    $this->assertSame('[name] has passed on.', $config->get('people_warnings.warning_single'));
    $this->assertSame('These people have passed on: [names]', $config->get('people_warnings.warning_multiple'));
    $this->assertStringStartsWith('Kept', $message);
  }

  /**
   * Translations of the warning text are left alone.
   */
  public function testTranslationsAreKept(): void {
    ConfigurableLanguage::createFromLangcode('es')->save();
    $this->setPeopleWarnings(self::OLD_SINGLE, self::OLD_MULTIPLE);
    $override = $this->container->get('language_manager')->getLanguageConfigOverride('es', 'mukurtu_content_warnings.settings');
    $override->set('people_warnings.warning_single', 'Aviso: [name] ha fallecido.')->save();

    mukurtu_content_warnings_update_40801();

    $override = $this->container->get('language_manager')->getLanguageConfigOverride('es', 'mukurtu_content_warnings.settings');
    $this->assertSame('Aviso: [name] ha fallecido.', $override->get('people_warnings.warning_single'));
  }

  /**
   * Sets the people warning texts in base config.
   */
  protected function setPeopleWarnings(string $single, string $multiple): void {
    $this->config('mukurtu_content_warnings.settings')
      ->set('people_warnings', [
        'enabled' => TRUE,
        'warning_single' => $single,
        'warning_multiple' => $multiple,
      ])
      ->save();
  }

}
