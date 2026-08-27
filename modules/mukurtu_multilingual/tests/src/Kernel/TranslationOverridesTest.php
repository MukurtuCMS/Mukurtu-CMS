<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Kernel;

use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\NodeType;

/**
 * Tests _mukurtu_multilingual_apply_translation_overrides().
 *
 * A handful of base field overrides and one content language settings
 * object are shipped, with different values, by the content-type module
 * that owns the bundle (e.g. mukurtu_person ships
 * core.base_field_override.node.person.title with translatable: true).
 * mukurtu_multilingual can no longer ship its own config/install/*.yml for
 * these, since Drupal's ConfigInstaller refuses to install a module whose
 * config/install ships a config name that already exists in active
 * storage - which previously blocked the entire module from installing on
 * any stock Mukurtu site. These tests simulate the owning module's
 * pre-existing config and verify the imperative override is applied
 * instead.
 *
 * @see _mukurtu_multilingual_apply_translation_overrides()
 * @group mukurtu_multilingual
 */
class TranslationOverridesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['field', 'node']);

    // The bundles referenced by the overrides under test must exist as
    // real node_type entities - ConfigEntityBase::calculateDependencies()
    // (called from BaseFieldOverride/ContentLanguageSettings preSave())
    // throws a LogicException otherwise.
    foreach (['dictionary_word', 'landing_page', 'person', 'place', 'word_list'] as $bundle) {
      NodeType::create(['type' => $bundle, 'name' => $bundle])->save();
    }

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_multilingual');
    require_once $module_path . '/mukurtu_multilingual.install';
  }

  /**
   * Creates a base field override simulating the owning module's default.
   */
  protected function createOverride(string $entity_type_id, string $bundle, string $field_name, string $field_type, bool $translatable): BaseFieldOverride {
    $override = BaseFieldOverride::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type_id,
      'bundle' => $bundle,
      'field_type' => $field_type,
      'label' => $field_name,
      'translatable' => $translatable,
    ]);
    $override->save();

    return $override;
  }

  /**
   * The five title/promote base field overrides are flipped untranslatable.
   */
  public function testFlipsTitleAndPromoteToUntranslatable(): void {
    $fields = [
      ['node', 'dictionary_word', 'title'],
      ['node', 'landing_page', 'promote'],
      ['node', 'person', 'title'],
      ['node', 'place', 'title'],
      ['node', 'word_list', 'promote'],
    ];

    foreach ($fields as [$entity_type_id, $bundle, $field_name]) {
      $this->createOverride($entity_type_id, $bundle, $field_name, $field_name === 'promote' ? 'boolean' : 'string', TRUE);
    }

    _mukurtu_multilingual_apply_translation_overrides();

    foreach ($fields as [$entity_type_id, $bundle, $field_name]) {
      $override = BaseFieldOverride::loadByName($entity_type_id, $bundle, $field_name);
      $this->assertNotNull($override, "Override for $entity_type_id.$bundle.$field_name should still exist.");
      $this->assertFalse($override->isTranslatable(), "$entity_type_id.$bundle.$field_name should have been made untranslatable.");
    }
  }

  /**
   * Enables content translation and language_alterable for landing pages.
   */
  public function testEnablesLandingPageContentTranslation(): void {
    // Simulate the owning module's shipped default: no content_translation
    // third-party setting, language_alterable explicitly false.
    ContentLanguageSettings::create([
      'target_entity_type_id' => 'node',
      'target_bundle' => 'landing_page',
      'language_alterable' => FALSE,
    ])->save();

    _mukurtu_multilingual_apply_translation_overrides();

    /** @var \Drupal\content_translation\ContentTranslationManagerInterface $content_translation_manager */
    $content_translation_manager = \Drupal::service('content_translation.manager');
    $this->assertTrue($content_translation_manager->isEnabled('node', 'landing_page'));
    $this->assertSame(
      ['untranslatable_fields_hide' => '0'],
      $content_translation_manager->getBundleTranslationSettings('node', 'landing_page')
    );

    $settings = ContentLanguageSettings::loadByEntityTypeBundle('node', 'landing_page');
    $this->assertTrue($settings->isLanguageAlterable());
  }

  /**
   * Running the override twice does not error and stays converged.
   */
  public function testIsIdempotent(): void {
    $this->createOverride('node', 'person', 'title', 'string', TRUE);

    _mukurtu_multilingual_apply_translation_overrides();
    _mukurtu_multilingual_apply_translation_overrides();

    $override = BaseFieldOverride::loadByName('node', 'person', 'title');
    $this->assertFalse($override->isTranslatable());
  }

  /**
   * A missing override (no owning module installed yet) is a safe no-op.
   */
  public function testIsNoOpWithoutExistingOverride(): void {
    $this->assertNull(BaseFieldOverride::loadByName('node', 'person', 'title'));

    _mukurtu_multilingual_apply_translation_overrides();

    $this->assertNull(BaseFieldOverride::loadByName('node', 'person', 'title'));
  }

}
