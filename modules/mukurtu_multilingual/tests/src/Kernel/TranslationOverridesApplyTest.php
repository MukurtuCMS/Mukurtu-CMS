<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests _mukurtu_multilingual_apply_translation_overrides().
 *
 * @see _mukurtu_multilingual_apply_translation_overrides()
 */
#[Group('mukurtu_multilingual')]
class TranslationOverridesApplyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'language', 'content_translation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_multilingual');
    require_once $module_path . '/mukurtu_multilingual.install';
  }

  /**
   * The overrides turn on translation support for an existing stub config.
   */
  public function testAppliesOverridesToExistingConfig(): void {
    $config_factory = \Drupal::configFactory();

    $stubs = [
      'core.base_field_override.node.dictionary_word.title' => ['translatable' => FALSE],
      'core.base_field_override.node.word_list.promote' => ['translatable' => FALSE],
      'core.base_field_override.node.landing_page.promote' => ['translatable' => FALSE],
      'core.base_field_override.node.person.title' => ['translatable' => FALSE],
      'core.base_field_override.node.place.title' => [
        'translatable' => FALSE,
        'description' => 'The place name, as it should be primarily identified. Maximum 255 characters.',
      ],
      'language.content_settings.node.landing_page' => [
        'language_alterable' => FALSE,
        'dependencies' => ['config' => ['node.type.landing_page']],
      ],
    ];
    foreach ($stubs as $name => $data) {
      $config_factory->getEditable($name)->setData($data)->save();
    }

    _mukurtu_multilingual_apply_translation_overrides();

    $this->assertTrue($config_factory->get('core.base_field_override.node.dictionary_word.title')->get('translatable'));
    $this->assertTrue($config_factory->get('core.base_field_override.node.word_list.promote')->get('translatable'));
    $this->assertTrue($config_factory->get('core.base_field_override.node.landing_page.promote')->get('translatable'));
    $this->assertTrue($config_factory->get('core.base_field_override.node.person.title')->get('translatable'));

    $place = $config_factory->get('core.base_field_override.node.place.title');
    $this->assertTrue($place->get('translatable'));
    $this->assertSame('The place, as it should be primarily identified. Maximum 255 characters.', $place->get('description'));

    $landing_page_settings = $config_factory->get('language.content_settings.node.landing_page');
    $this->assertTrue($landing_page_settings->get('language_alterable'));
    $this->assertTrue($landing_page_settings->get('third_party_settings.content_translation.enabled'));
    $this->assertSame('0', $landing_page_settings->get('third_party_settings.content_translation.bundle_settings.untranslatable_fields_hide'));
    $this->assertContains('content_translation', $landing_page_settings->get('dependencies.module') ?? []);
  }

  /**
   * The function is a no-op when the owning module's config is missing.
   */
  public function testNoOpWhenOwningConfigMissing(): void {
    $config_factory = \Drupal::configFactory();
    $this->assertTrue($config_factory->get('core.base_field_override.node.dictionary_word.title')->isNew());

    _mukurtu_multilingual_apply_translation_overrides();

    $this->assertTrue($config_factory->get('core.base_field_override.node.dictionary_word.title')->isNew());
  }

}
