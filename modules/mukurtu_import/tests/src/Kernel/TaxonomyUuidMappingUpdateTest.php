<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Serialization\Yaml;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_import_update_40201(), which adds UUID to taxonomy imports.
 *
 * Note that the strategies are deliberately put back into their pre-fix state
 * here. mukurtu_import's config/install now ships the UUID row, so a freshly
 * installed test site already has it and the hook would have nothing to do:
 * every assertion would pass without the hook being exercised at all.
 *
 * @see mukurtu_import_update_40201()
 */
#[Group('mukurtu_import')]
class TaxonomyUuidMappingUpdateTest extends MukurtuImportTestBase {

  /**
   * A shipped taxonomy strategy used as the representative case.
   */
  private const STRATEGY_ID = 'taxonomy_category_all_fields';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_import');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_import.install';

    // Create the strategy from its shipped YAML rather than calling
    // installConfig('mukurtu_import'). Installing the module's whole config set
    // fails here on an unrelated pre-existing schema gap in
    // views.view.mukurtu_import_results_content, and turning off
    // strictConfigSchema to get past it would hide that rather than fix it.
    // Reading the one file keeps the test on real shipped data either way.
    $file = \Drupal::root() . '/' . $module_path
      . '/config/install/mukurtu_import.mukurtu_import_strategy.' . self::STRATEGY_ID . '.yml';
    $this->assertFileExists($file);

    $values = Yaml::decode(file_get_contents($file));
    unset($values['langcode'], $values['status'], $values['dependencies']);
    \Drupal::entityTypeManager()
      ->getStorage('mukurtu_import_strategy')
      ->create($values)
      ->save();
  }

  /**
   * Loads a strategy's mapping as a source => target array.
   */
  private function mappingOf(string $id): array {
    $strategy = \Drupal::entityTypeManager()
      ->getStorage('mukurtu_import_strategy')
      ->loadUnchanged($id);
    $this->assertNotNull($strategy, "The $id strategy is not installed, so this test cannot mean anything.");

    $out = [];
    foreach ($strategy->getMapping() ?? [] as $row) {
      $out[$row['source']] = $row['target'];
    }

    return $out;
  }

  /**
   * Puts a strategy back into the state an existing site is in.
   */
  private function removeUuidMapping(string $id): void {
    $storage = \Drupal::entityTypeManager()->getStorage('mukurtu_import_strategy');
    $strategy = $storage->load($id);
    $this->assertNotNull($strategy);

    $mapping = array_values(array_filter(
      $strategy->getMapping() ?? [],
      static fn (array $row): bool => ($row['source'] ?? NULL) !== 'UUID'
    ));
    $strategy->setMapping($mapping);
    $strategy->save();

    $this->assertArrayNotHasKey('UUID', $this->mappingOf($id), 'Precondition: the UUID row was not actually removed.');
  }

  /**
   * The hook adds the mapping a pre-fix site is missing.
   */
  public function testAddsUuidMappingToAnExistingSite(): void {
    $this->removeUuidMapping(self::STRATEGY_ID);
    $before = $this->mappingOf(self::STRATEGY_ID);

    $message = mukurtu_import_update_40201();

    $after = $this->mappingOf(self::STRATEGY_ID);
    $this->assertSame('uuid', $after['UUID'] ?? NULL, 'A taxonomy export\'s UUID column still cannot be mapped on import.');
    $this->assertNotNull($message, 'The operator was told nothing about the templates changing.');

    // Everything else is left exactly as it was. The hook appends, it does not
    // rewrite a template a site may have customised.
    unset($after['UUID']);
    $this->assertSame($before, $after, 'The hook altered mappings other than the one it adds.');
  }

  /**
   * A strategy that already maps UUID is left alone, with no duplicate row.
   */
  public function testDoesNotDuplicateAnExistingMapping(): void {
    // Shipped config already carries the row, so this is the fresh-install and
    // the already-updated case at once.
    $this->assertArrayHasKey('UUID', $this->mappingOf(self::STRATEGY_ID), 'Precondition: shipped config should already map UUID.');

    mukurtu_import_update_40201();

    $strategy = \Drupal::entityTypeManager()
      ->getStorage('mukurtu_import_strategy')
      ->loadUnchanged(self::STRATEGY_ID);
    $uuidRows = array_filter(
      $strategy->getMapping() ?? [],
      static fn (array $row): bool => ($row['source'] ?? NULL) === 'UUID'
    );

    $this->assertCount(1, $uuidRows, 'The hook added a second UUID row.');
  }

  /**
   * Running it twice changes nothing the second time.
   */
  public function testIsIdempotent(): void {
    $this->removeUuidMapping(self::STRATEGY_ID);

    $first = mukurtu_import_update_40201();
    $afterFirst = $this->mappingOf(self::STRATEGY_ID);
    $second = mukurtu_import_update_40201();

    $this->assertNotNull($first);
    $this->assertNull($second, 'The second run reported changes it did not make.');
    $this->assertSame($afterFirst, $this->mappingOf(self::STRATEGY_ID));
  }

}
