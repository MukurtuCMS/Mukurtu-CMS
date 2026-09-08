<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\MediaType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_multilingual_update_40008(), which closes the 12 bundle
 * translation gaps found by ConfigTranslationCoverageTest - most notably
 * that none of the 7 media bundles had content translation enabled at all,
 * so media names and other translatable fields (already correctly marked
 * translatable in each bundle's own field definitions) could never actually
 * be translated on a multilingual site.
 *
 * media.image is used here as the representative case; the same
 * language.content_settings.<type>.<bundle> import logic applies uniformly
 * to all 12 bundles the update hook covers.
 *
 * @see mukurtu_multilingual_update_40008()
 * @see ConfigTranslationCoverageTest
 */
#[Group('mukurtu_multilingual')]
class BundleTranslationEnablementTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('media');
    $this->installConfig(['field', 'media']);

    // The bundle referenced by the config under test must exist as a real
    // entity - ContentLanguageSettings::calculateDependencies() throws a
    // LogicException otherwise (same lesson as TranslationOverridesTest).
    MediaType::create(['id' => 'image', 'label' => 'Image', 'source' => 'image'])->save();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_multilingual');
    require_once $module_path . '/mukurtu_multilingual.install';
  }

  /**
   * Before the update hook runs, the bundle has no translation support.
   */
  public function testMediaImageIsNotTranslatableBeforeUpdate(): void {
    $this->assertFalse(\Drupal::service('content_translation.manager')->isEnabled('media', 'image'));
  }

  /**
   * The update hook enables content translation for the bundle.
   */
  public function testUpdateEnablesMediaImageTranslation(): void {
    mukurtu_multilingual_update_40008();

    $this->assertTrue(\Drupal::service('content_translation.manager')->isEnabled('media', 'image'));
  }

  /**
   * Running the update twice does not error and stays converged.
   */
  public function testUpdateIsIdempotent(): void {
    mukurtu_multilingual_update_40008();
    mukurtu_multilingual_update_40008();

    $this->assertTrue(\Drupal::service('content_translation.manager')->isEnabled('media', 'image'));
  }

}
