<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Serialization\Yaml;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_import_update_40406(), which maps the membership columns.
 *
 * As in LanguageCodeMappingUpdateTest, the templates are put back into their
 * pre-fix state, or the hook would have nothing to do.
 *
 * @see mukurtu_import_update_40406()
 */
#[Group('mukurtu_import')]
class ContainerMembershipMappingUpdateTest extends MukurtuImportTestBase {

  /**
   * The rows the hook adds, by template.
   */
  private const ROWS = [
    'digital_heritage_item_all_fields' => [['source' => 'Collections', 'target' => 'field_in_collection']],
    'dictionary_word_all_fields' => [
      ['source' => 'Collections', 'target' => 'field_in_collection'],
      ['source' => 'Word Lists', 'target' => 'field_in_word_list'],
    ],
    'word_list_all_fields' => [['source' => 'Collections', 'target' => 'field_in_collection']],
    'person_record_all_fields' => [['source' => 'Collections', 'target' => 'field_in_collection']],
    'place_record_all_fields' => [['source' => 'Collections', 'target' => 'field_in_collection']],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_import');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_import.install';

    // Create each template from its shipped YAML, without the new rows.
    foreach (self::ROWS as $id => $rows) {
      $values = Yaml::decode(file_get_contents(\Drupal::root() . '/' . $module_path . '/config/install/mukurtu_import.mukurtu_import_strategy.' . $id . '.yml'));
      foreach ($rows as $row) {
        $this->assertContains($row, $values['mapping'], "Precondition: $id does not ship the {$row['source']} row.");
      }
      $values['mapping'] = array_values(array_filter($values['mapping'], static fn (array $row): bool => !in_array($row, $rows, TRUE)));
      unset($values['langcode'], $values['status'], $values['dependencies']);
      \Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy')->create($values)->save();
    }
  }

  /**
   * Loads a template's mapping rows.
   */
  private function rows(string $id): array {
    $strategy = \Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy')->loadUnchanged($id);
    $this->assertNotNull($strategy, "The $id template is not installed, so this test cannot mean anything.");
    return $strategy->getMapping() ?? [];
  }

  /**
   * The hook appends the rows once and leaves the other rows alone.
   */
  public function testAddsRows(): void {
    $before = array_map(fn (string $id) => $this->rows($id), array_combine(array_keys(self::ROWS), array_keys(self::ROWS)));

    $this->assertSame('Mapped the Collections and Word Lists columns in 5 default import template(s).', mukurtu_import_update_40406());

    foreach (self::ROWS as $id => $rows) {
      $this->assertSame(array_merge($before[$id], $rows), $this->rows($id), "$id did not get exactly the new rows at the end.");
    }

    $this->assertNull(mukurtu_import_update_40406(), 'A second run changed something.');
  }

  /**
   * A site's own mapping is kept, and a deleted template stays deleted.
   */
  public function testKeepsSiteChoices(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy');
    $storage->load('place_record_all_fields')->delete();

    $strategy = $storage->load('person_record_all_fields');
    $strategy->setMapping(array_merge($strategy->getMapping(), [
      ['source' => 'Collections', 'target' => '-1'],
    ]));
    $strategy->save();

    $strategy = $storage->load('word_list_all_fields');
    $strategy->setMapping(array_merge($strategy->getMapping(), [
      ['source' => 'In collections', 'target' => 'field_in_collection'],
    ]));
    $strategy->save();

    $this->assertSame('Mapped the Collections and Word Lists columns in 2 default import template(s).', mukurtu_import_update_40406());

    $this->assertNull($storage->loadUnchanged('place_record_all_fields'), 'The deleted template came back.');
    $sources = static fn (array $rows, string $target): array => array_column(array_filter($rows, static fn (array $row): bool => $row['target'] === $target), 'source');
    $this->assertSame([], $sources($this->rows('person_record_all_fields'), 'field_in_collection'));
    $this->assertSame(['In collections'], $sources($this->rows('word_list_all_fields'), 'field_in_collection'));
  }

}
