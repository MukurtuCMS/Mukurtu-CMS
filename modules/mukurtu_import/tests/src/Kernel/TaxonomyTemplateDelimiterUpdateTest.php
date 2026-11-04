<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Serialization\Yaml;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_import_update_40405() (#2520).
 *
 * @see mukurtu_import_update_40405()
 */
#[Group('mukurtu_import')]
class TaxonomyTemplateDelimiterUpdateTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_import');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_import.install';

    // As in TaxonomyUuidMappingUpdateTest, create the templates from their
    // shipped YAML rather than installing the module's whole config set.
    foreach (['location', 'keywords', 'people'] as $vid) {
      $values = Yaml::decode(file_get_contents(\Drupal::root() . '/' . $module_path
        . "/config/install/mukurtu_import.mukurtu_import_strategy.taxonomy_{$vid}_all_fields.yml"));
      unset($values['langcode'], $values['status'], $values['dependencies']);
      \Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy')->create($values)->save();
    }
  }

  /**
   * Gets a template's multi-value delimiter.
   */
  protected function delimiterOf(string $vid): ?string {
    return \Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy')
      ->loadUnchanged("taxonomy_{$vid}_all_fields")
      ->getConfig('multivalue_delimiter');
  }

  /**
   * Sets a template's multi-value delimiter.
   */
  protected function setDelimiter(string $vid, string $delimiter): void {
    $strategy = \Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy')->load("taxonomy_{$vid}_all_fields");
    $strategy->setConfig('multivalue_delimiter', $delimiter);
    $strategy->save();
  }

  /**
   * Templates still on "||" switch to ";", and others are left alone.
   */
  public function testSwitchesOnlyUnchangedTemplates(): void {
    // As shipped before this update.
    $this->setDelimiter('location', '||');
    $this->setDelimiter('keywords', '||');
    // Changed by the site.
    $this->setDelimiter('people', '|');

    $message = mukurtu_import_update_40405();

    $this->assertSame(';', $this->delimiterOf('location'));
    $this->assertSame(';', $this->delimiterOf('keywords'));
    $this->assertSame('|', $this->delimiterOf('people'));
    $this->assertStringStartsWith('Set 2 default taxonomy import templates', (string) $message);

    // Running it again changes nothing.
    $this->assertNull(mukurtu_import_update_40405());
  }

}
