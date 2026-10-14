<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Serialization\Yaml;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_import_update_40404(), which maps the Language code column.
 *
 * As in DefaultTranslationMappingUpdateTest, the template is put back into
 * its pre-fix state, or the hook would have nothing to do.
 *
 * @see mukurtu_import_update_40404()
 */
#[Group('mukurtu_import')]
class LanguageCodeMappingUpdateTest extends MukurtuImportTestBase {

  private const ID = 'taxonomy_language_all_fields';

  private const ROW = ['source' => 'Language code', 'target' => 'field_language_code'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_import');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_import.install';

    // Create the template from its shipped YAML, without the new row, for
    // the reason given in TaxonomyUuidMappingUpdateTest::setUp().
    $values = Yaml::decode(file_get_contents(\Drupal::root() . '/' . $module_path . '/config/install/mukurtu_import.mukurtu_import_strategy.' . self::ID . '.yml'));
    $this->assertContains(self::ROW, $values['mapping'], 'Precondition: the template does not ship the row.');
    $values['mapping'] = array_values(array_filter($values['mapping'], static fn (array $row): bool => $row !== self::ROW));
    unset($values['langcode'], $values['status'], $values['dependencies']);
    \Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy')->create($values)->save();
  }

  /**
   * Loads the template's mapping rows.
   */
  private function rows(): array {
    $strategy = \Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy')->loadUnchanged(self::ID);
    $this->assertNotNull($strategy, 'The template is not installed, so this test cannot mean anything.');
    return $strategy->getMapping() ?? [];
  }

  /**
   * The hook appends the row once and leaves the other rows alone.
   */
  public function testAddsRow(): void {
    $before = $this->rows();

    $this->assertSame('Mapped the Language code column in the default language import template.', mukurtu_import_update_40404());

    $after = $this->rows();
    $this->assertSame(self::ROW, array_pop($after));
    $this->assertSame($before, $after, 'The hook altered other rows.');

    $this->assertNull(mukurtu_import_update_40404(), 'A second run changed something.');
  }

  /**
   * A template that already maps the column keeps its own target.
   */
  public function testKeepsSiteMapping(): void {
    $strategy = \Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy')->load(self::ID);
    $strategy->setMapping(array_merge($strategy->getMapping(), [['source' => 'Language code', 'target' => '-1']]));
    $strategy->save();

    $this->assertNull(mukurtu_import_update_40404());
    $this->assertSame(['-1'], array_column(array_filter($this->rows(), static fn (array $row): bool => $row['source'] === 'Language code'), 'target'));
  }

  /**
   * A site that deleted the template is left alone.
   */
  public function testSkipsDeletedTemplate(): void {
    \Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy')->load(self::ID)->delete();
    $this->assertNull(mukurtu_import_update_40404());
    $this->assertNull(\Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy')->loadUnchanged(self::ID));
  }

}
