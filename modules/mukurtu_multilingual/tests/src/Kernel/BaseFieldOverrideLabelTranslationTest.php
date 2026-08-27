<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\locale\SourceString;

/**
 * Confirms that Drupal core's own locale config-sync mechanism, not any
 * Mukurtu code, is what makes a field label/description's interface
 * translation appear on core.base_field_override.* config.
 *
 * The Phase 1 t()-wrapping sweep (#1611) only matters on a multilingual
 * site for fields with no bundle-specific override (their label renders
 * directly from the code's t() call). For any field that HAS a
 * core.base_field_override.* - which is common in this codebase, and
 * Phase 3.2 (#1260) just added more of them for the 12 media/taxonomy/
 * block/paragraph bundles it covers - the override's plain-string label
 * shadows the code's t() call entirely on the edit form. This was
 * initially assumed to need new sync code (see the original Phase 3.3
 * plan), but core.base_field_override.*'s label/description are already
 * marked translatable in Drupal core's own schema
 * (core.data_types.schema.yml: field_config_base.label -> required_label
 * -> label, and .description -> text; both translatable: true), and
 * core's locale module already calls
 * LocaleConfigManager::updateConfigTranslations() - via
 * _locale_refresh_configuration() - both when a translator saves a string
 * at /admin/config/regional/translate (TranslateEditForm::submitForm())
 * and when a new language's .po translations are imported
 * (LocaleImportBatch, LocaleConfigBatch). No Mukurtu code is involved.
 *
 * LocaleConfigManager::getTranslatableDefaultConfig() reads from each
 * enabled module's actually-shipped config/install/*.yml (via
 * LocaleDefaultConfigStorage), not from an arbitrary active config entity,
 * so this test uses a small fixture module (mukurtu_multilingual_
 * translation_test) that ships one real core.base_field_override.*.yml,
 * rather than mukurtu_multilingual itself, whose real base field
 * overrides all live behind a heavy dependency chain (mukurtu_protocol,
 * mukurtu_collection, mukurtu_multipage_items).
 *
 * @group mukurtu_multilingual
 */
class BaseFieldOverrideLabelTranslationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'language',
    'locale',
    'content_translation',
    'mukurtu_multilingual_translation_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('locale', ['locales_source', 'locales_target', 'locales_location']);
    $this->installConfig(['field', 'node', 'mukurtu_multilingual_translation_test']);

    ConfigurableLanguage::createFromLangcode('es')->save();
  }

  /**
   * Translating a field label's source string via the standard locale
   * storage API - the same path /admin/config/regional/translate uses -
   * causes the translation to appear on the field's base field override
   * config, for the exact langcode translated, with no other langcode
   * affected and no Mukurtu code involved.
   */
  public function testInterfaceTranslationSyncsToBaseFieldOverride(): void {
    $config_name = 'core.base_field_override.node.mlt_test.title';
    $this->assertSame('MLT Test Label', \Drupal::config($config_name)->get('label'));

    $storage = \Drupal::service('locale.storage');
    $source = new SourceString();
    $source->setString('MLT Test Label');
    $source->setStorage($storage);
    $source->save();

    $translation = $storage->createTranslation([
      'lid' => $source->lid,
      'language' => 'es',
      'translation' => 'Etiqueta de prueba MLT',
    ]);
    $translation->save();

    // On a real site, _locale_refresh_configuration() - called by
    // TranslateEditForm::submitForm() after saving a translation in the UI -
    // resolves the lid to this config name via {locales_location}, a table
    // populated by locale's own config-translation processing (e.g. a prior
    // visit to /admin/config/regional/translate), which this test does not
    // exercise. Call the underlying sync directly with the known config
    // name instead - LocaleConfigManager::updateConfigTranslations() is the
    // actual translation-sync logic under test; the lid lookup is a mature,
    // separately-tested Drupal core concern.
    \Drupal::service('locale.config_manager')->updateConfigTranslations([$config_name], ['es']);

    $override_es = \Drupal::languageManager()->getLanguageConfigOverride('es', $config_name);
    $this->assertFalse($override_es->isNew(), 'Expected a language override to have been created for es.');
    $this->assertSame('Etiqueta de prueba MLT', $override_es->get('label'));

    // The base (default-language) config is untouched.
    $this->assertSame('MLT Test Label', \Drupal::config($config_name)->get('label'));

    // A langcode with no translation gets no override.
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $override_fr = \Drupal::languageManager()->getLanguageConfigOverride('fr', $config_name);
    $this->assertTrue($override_fr->isNew(), 'Did not expect an override for a langcode with no translation.');
  }

}
