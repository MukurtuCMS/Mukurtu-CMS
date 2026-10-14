<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Serialization\Yaml;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Other Names display switch to the combined formatter (#2290).
 *
 * Covers both halves of the change: the shipped config/install displays
 * that fresh installs get, and mukurtu_person_update_40201() and
 * mukurtu_place_update_40201(), which bring existing sites to the same
 * state. The person and place modules are not enabled because their
 * dependency chain is far heavier than these hooks need; their .install
 * files are loaded directly instead.
 */
#[Group('mukurtu_taxonomy')]
class OtherNamesDisplayUpdateTest extends EntityKernelTestBase {

  use EntityReferenceFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'taxonomy', 'mukurtu_taxonomy'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    EntityViewMode::create(['id' => 'node.full', 'targetEntityType' => 'node', 'label' => 'Full'])->save();

    $profile = dirname(__DIR__, 5);
    require_once $profile . '/modules/mukurtu_person/mukurtu_person.install';
    require_once $profile . '/modules/mukurtu_place/mukurtu_place.install';
  }

  /**
   * Cases: node bundle, field name, module.
   */
  public static function bundleProvider(): array {
    return [
      'person' => ['person', 'field_other_names', 'mukurtu_person'],
      'place' => ['place', 'field_other_place_names', 'mukurtu_place'],
    ];
  }

  /**
   * Creates the bundle, field and full display with the given formatter.
   */
  private function createDisplay(string $bundle, string $field, ?string $formatter): EntityViewDisplay {
    NodeType::create(['type' => $bundle, 'name' => $bundle])->save();
    $this->createEntityReferenceField('node', $bundle, $field, $field, 'taxonomy_term', 'default', [], -1);
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => $bundle,
      'mode' => 'full',
      'status' => TRUE,
    ]);
    if ($formatter) {
      $display->setComponent($field, [
        'type' => $formatter,
        'label' => 'above',
        'weight' => 13,
        'settings' => ['link' => FALSE],
      ]);
    }
    else {
      $display->removeComponent($field);
    }
    $display->save();
    return $display;
  }

  /**
   * Reloads the full display for a bundle.
   */
  private function reload(string $bundle): ?EntityViewDisplay {
    \Drupal::entityTypeManager()->getStorage('entity_view_display')->resetCache();
    return EntityViewDisplay::load("node.$bundle.full");
  }

  /**
   * The shipped full display uses the combined formatter.
   */
  #[DataProvider('bundleProvider')]
  public function testShippedConfig(string $bundle, string $field, string $module): void {
    $path = dirname(__DIR__, 5) . "/modules/$module/config/install/core.entity_view_display.node.$bundle.full.yml";
    $config = Yaml::decode(file_get_contents($path));

    $this->assertSame('mukurtu_combined_term_label', $config['content'][$field]['type']);
    $this->assertContains('mukurtu_taxonomy', $config['dependencies']['module']);
  }

  /**
   * The hook swaps core's label formatter and keeps its other settings.
   */
  #[DataProvider('bundleProvider')]
  public function testHookSwitchesDefaultFormatter(string $bundle, string $field, string $module): void {
    $this->createDisplay($bundle, $field, 'entity_reference_label');

    $message = (string) ("{$module}_update_40201")();

    $component = $this->reload($bundle)->getComponent($field);
    $this->assertSame('mukurtu_combined_term_label', $component['type']);
    $this->assertSame(['link' => FALSE], $component['settings']);
    $this->assertSame(13, $component['weight']);
    $this->assertSame('above', $component['label']);
    $this->assertStringContainsString('now show identical', $message);
    $this->assertContains('mukurtu_taxonomy', $this->reload($bundle)->getDependencies()['module']);
  }

  /**
   * A site that chose another formatter keeps it.
   */
  #[DataProvider('bundleProvider')]
  public function testHookLeavesCustomFormatter(string $bundle, string $field, string $module): void {
    $this->createDisplay($bundle, $field, 'entity_reference_entity_id');

    $message = (string) ("{$module}_update_40201")();

    $this->assertSame('entity_reference_entity_id', $this->reload($bundle)->getComponent($field)['type']);
    $this->assertStringContainsString('left unchanged', $message);
  }

  /**
   * A hidden field stays hidden.
   */
  #[DataProvider('bundleProvider')]
  public function testHookLeavesHiddenField(string $bundle, string $field, string $module): void {
    $this->createDisplay($bundle, $field, NULL);

    $message = (string) ("{$module}_update_40201")();

    $this->assertNull($this->reload($bundle)->getComponent($field));
    $this->assertStringContainsString('nothing was changed', $message);
  }

  /**
   * A site with no full display at all is not an error.
   */
  #[DataProvider('bundleProvider')]
  public function testHookHandlesMissingDisplay(string $bundle, string $field, string $module): void {
    $message = (string) ("{$module}_update_40201")();

    $this->assertNull($this->reload($bundle));
    $this->assertStringContainsString('nothing was changed', $message);
  }

}
