<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests mukurtu_import_update_40031/_40032/_40033(), which correct the
 * langcode source label and add missing "Default translation"/"Multipage
 * parent" ignore mappings to existing sites' saved import strategy configs.
 *
 * @see mukurtu_import_update_40031()
 * @see mukurtu_import_update_40032()
 * @see mukurtu_import_update_40033()
 */
class ImportTemplateMappingUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The hooks operate on hand-seeded fixture config, not the real schema.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_import');
    require_once $module_path . '/mukurtu_import.install';
  }

  /**
   * Seeds a mukurtu_import_strategy config with a given mapping array.
   */
  private function seedConfig(string $id, array $mapping): void {
    \Drupal::configFactory()->getEditable("mukurtu_import.mukurtu_import_strategy.{$id}")
      ->setData([
        'id' => $id,
        'label' => $id,
        'target_entity_type_id' => 'node',
        'target_bundle' => 'collection',
        'mapping' => $mapping,
      ])
      ->save();
  }

  /**
   * Reads back a seeded config's mapping array.
   */
  private function getMapping(string $id): array {
    return \Drupal::config("mukurtu_import.mukurtu_import_strategy.{$id}")->get('mapping');
  }

  /**
   * The langcode fix rewrites a stale "Language (langcode)" source to
   * "Locale" for a node-bundle template.
   */
  public function testLangcodeCorrectionRewritesStaleDefault(): void {
    $this->seedConfig('collection_all_fields', [
      ['source' => 'Title', 'target' => 'title'],
      ['source' => 'Language (langcode)', 'target' => 'langcode'],
    ]);

    mukurtu_import_update_40031();

    $mapping = $this->getMapping('collection_all_fields');
    $langcode_entry = current(array_filter($mapping, fn (array $m) => $m['target'] === 'langcode'));
    $this->assertSame('Locale', $langcode_entry['source']);
  }

  /**
   * The langcode fix uses "Language" (bare) for multipage_item and
   * "Language code" (bare) for paragraph-bundle templates, not "Locale".
   */
  public function testLangcodeCorrectionHandlesNonLocaleVariants(): void {
    $this->seedConfig('multipage_item_all_fields', [
      ['source' => 'Language (langcode)', 'target' => 'langcode'],
    ]);
    $this->seedConfig('word_entry_all_fields', [
      ['source' => 'Language code (langcode)', 'target' => 'langcode'],
    ]);

    mukurtu_import_update_40031();

    $multipage_mapping = $this->getMapping('multipage_item_all_fields');
    $this->assertSame('Language', $multipage_mapping[0]['source']);

    $word_entry_mapping = $this->getMapping('word_entry_all_fields');
    $this->assertSame('Language code', $word_entry_mapping[0]['source']);
  }

  /**
   * A site admin's customized langcode source is left untouched.
   */
  public function testLangcodeCorrectionLeavesCustomizationAlone(): void {
    $this->seedConfig('collection_all_fields', [
      ['source' => 'My Custom Locale Column', 'target' => 'langcode'],
    ]);

    mukurtu_import_update_40031();

    $mapping = $this->getMapping('collection_all_fields');
    $this->assertSame('My Custom Locale Column', $mapping[0]['source']);
  }

  /**
   * The "Default translation" ignore entry is appended when missing.
   */
  public function testDefaultTranslationAddedWhenMissing(): void {
    $this->seedConfig('collection_all_fields', [
      ['source' => 'Title', 'target' => 'title'],
    ]);

    mukurtu_import_update_40032();

    $mapping = $this->getMapping('collection_all_fields');
    $matches = array_filter($mapping, fn (array $m) => ($m['source'] ?? NULL) === 'Default translation');
    $this->assertCount(1, $matches);
    $this->assertSame('-1', reset($matches)['target']);
  }

  /**
   * An existing "Default translation" mapping (however it's targeted) is
   * not duplicated by a second run of the hook.
   */
  public function testDefaultTranslationNotDuplicatedWhenPresent(): void {
    $this->seedConfig('collection_all_fields', [
      ['source' => 'Title', 'target' => 'title'],
      ['source' => 'Default translation', 'target' => '-1'],
    ]);

    mukurtu_import_update_40032();

    $mapping = $this->getMapping('collection_all_fields');
    $matches = array_filter($mapping, fn (array $m) => ($m['source'] ?? NULL) === 'Default translation');
    $this->assertCount(1, $matches);
  }

  /**
   * A template whose bundle doesn't export default_langcode (not in the
   * hook's scoped id list) is never touched, even if it happens to exist.
   */
  public function testDefaultTranslationNotAddedForOutOfScopeTemplate(): void {
    $this->seedConfig('dictionary_word_all_fields', [
      ['source' => 'Title', 'target' => 'title'],
    ]);

    mukurtu_import_update_40032();

    $mapping = $this->getMapping('dictionary_word_all_fields');
    $this->assertCount(1, $mapping);
  }

  /**
   * The "Multipage parent" ignore entry is appended when missing, for a
   * node-bundle template whose exporter section carries that column.
   */
  public function testMultipageParentAddedWhenMissing(): void {
    $this->seedConfig('collection_all_fields', [
      ['source' => 'Title', 'target' => 'title'],
    ]);

    mukurtu_import_update_40033();

    $mapping = $this->getMapping('collection_all_fields');
    $matches = array_filter($mapping, fn (array $m) => ($m['source'] ?? NULL) === 'Multipage parent');
    $this->assertCount(1, $matches);
    $this->assertSame('-1', reset($matches)['target']);
  }

  /**
   * An existing "Multipage parent" mapping is not duplicated.
   */
  public function testMultipageParentNotDuplicatedWhenPresent(): void {
    $this->seedConfig('collection_all_fields', [
      ['source' => 'Title', 'target' => 'title'],
      ['source' => 'Multipage parent', 'target' => '-1'],
    ]);

    mukurtu_import_update_40033();

    $mapping = $this->getMapping('collection_all_fields');
    $matches = array_filter($mapping, fn (array $m) => ($m['source'] ?? NULL) === 'Multipage parent');
    $this->assertCount(1, $matches);
  }

  /**
   * A config that was never installed (isNew()) is skipped without error by
   * every hook.
   */
  public function testHooksSkipNonExistentConfig(): void {
    mukurtu_import_update_40031();
    mukurtu_import_update_40032();
    mukurtu_import_update_40033();
    $this->assertTrue(\Drupal::config('mukurtu_import.mukurtu_import_strategy.audio_all_fields')->isNew());
  }

}
