<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Serialization\Yaml;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_import_update_40402(), which ignores Default translation.
 *
 * The templates are deliberately put back into their pre-fix state here.
 * mukurtu_import's config/install now ships the row, so a freshly installed
 * test site already has it and the hook would have nothing to do: every
 * assertion would pass without the hook being exercised at all.
 *
 * @see mukurtu_import_update_40402()
 */
#[Group('mukurtu_import')]
class DefaultTranslationMappingUpdateTest extends MukurtuImportTestBase {

  /**
   * The shipped templates the hook updates.
   */
  private const STRATEGY_IDS = [
    'dictionary_word_all_fields',
    'image_all_fields',
    'soundcloud_all_fields',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_import');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_import.install';

    // Create the templates from their shipped YAML rather than calling
    // installConfig('mukurtu_import'), for the reason given in
    // TaxonomyUuidMappingUpdateTest::setUp().
    $storage = \Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy');
    foreach (self::STRATEGY_IDS as $id) {
      $file = \Drupal::root() . '/' . $module_path
        . '/config/install/mukurtu_import.mukurtu_import_strategy.' . $id . '.yml';
      $this->assertFileExists($file);

      $values = Yaml::decode(file_get_contents($file));
      unset($values['langcode'], $values['status'], $values['dependencies']);
      $storage->create($values)->save();
    }
  }

  /**
   * Loads a template's mapping rows.
   */
  private function rowsOf(string $id): array {
    $strategy = \Drupal::entityTypeManager()
      ->getStorage('mukurtu_import_strategy')
      ->loadUnchanged($id);
    $this->assertNotNull($strategy, "The $id template is not installed, so this test cannot mean anything.");
    return $strategy->getMapping() ?? [];
  }

  /**
   * Returns the targets a template maps the Default translation column to.
   */
  private function defaultTranslationTargets(string $id): array {
    return array_column(array_filter(
      $this->rowsOf($id),
      static fn (array $row): bool => ($row['source'] ?? NULL) === 'Default translation'
    ), 'target');
  }

  /**
   * Puts a template back into the state an existing site is in.
   */
  private function removeDefaultTranslationRow(string $id): void {
    $strategy = \Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy')->load($id);
    $strategy->setMapping(array_values(array_filter(
      $strategy->getMapping() ?? [],
      static fn (array $row): bool => ($row['source'] ?? NULL) !== 'Default translation'
    )));
    $strategy->save();

    $this->assertSame([], $this->defaultTranslationTargets($id), 'Precondition: the Default translation row was not actually removed.');
  }

  /**
   * The hook adds the ignore row a pre-fix site is missing, once.
   */
  public function testAddsIgnoreRowToAnExistingSite(): void {
    $before = [];
    foreach (self::STRATEGY_IDS as $id) {
      $this->removeDefaultTranslationRow($id);
      $before[$id] = $this->rowsOf($id);
    }

    $message = mukurtu_import_update_40402();
    $this->assertSame('Set 3 default import template(s) to ignore the Default translation column.', $message);

    foreach (self::STRATEGY_IDS as $id) {
      $after = $this->rowsOf($id);
      $this->assertSame(['source' => 'Default translation', 'target' => '-1'], array_pop($after), "$id did not get the ignore row appended.");
      $this->assertSame($before[$id], $after, "The hook altered other rows in $id.");
    }

    $this->assertNull(mukurtu_import_update_40402(), 'A second run changed something.');
  }

  /**
   * A template that already has the column keeps whatever it maps it to.
   */
  public function testLeavesExistingRowsAlone(): void {
    // Shipped config already carries the row, so this is the fresh-install
    // and the already-updated case at once.
    $this->assertNull(mukurtu_import_update_40402());
    foreach (self::STRATEGY_IDS as $id) {
      $this->assertSame(['-1'], $this->defaultTranslationTargets($id), "$id should have exactly one ignore row.");
    }

    // A site that deleted one template and left another alone.
    \Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy')->load('image_all_fields')->delete();
    $this->removeDefaultTranslationRow('soundcloud_all_fields');

    $this->assertSame('Set 1 default import template(s) to ignore the Default translation column.', mukurtu_import_update_40402());
    $this->assertNull(\Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy')->loadUnchanged('image_all_fields'), 'The deleted template came back.');
    $this->assertSame(['-1'], $this->defaultTranslationTargets('soundcloud_all_fields'));
  }

}
