<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_submissions_update_40201(), the submission coverage map fix.
 *
 * Builds the drifted display by hand rather than installing the shipped one,
 * since 4.0.1 already corrected the shipped config and the hook would have
 * nothing to do against it.
 *
 * @see mukurtu_submissions_update_40201()
 */
#[Group('mukurtu_submissions')]
class SubmissionCoverageMapUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'text',
    'geofield',
    'leaflet',
    'file',
    'image',
    'media',
    // Provides both the geofield_mukurtu widget this hook looks for and the
    // geofield_mukurtu_latlon one the negative case uses. Without it the
    // display cannot be saved at all: the plugin collection rejects an unknown
    // widget id on save.
    'mukurtu_core',
  ];

  /**
   * The display the hook targets.
   */
  private const DISPLAY_ID = 'node.digital_heritage.submission';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');

    NodeType::create(['type' => 'digital_heritage', 'name' => 'Digital Heritage'])->save();

    // The display's dependency calculation resolves its form mode, so the mode
    // has to exist or save() fails with getConfigDependencyName() on null.
    EntityFormMode::create([
      'id' => 'node.submission',
      'label' => 'Submission',
      'targetEntityType' => 'node',
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_coverage',
      'entity_type' => 'node',
      'type' => 'geofield',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_coverage',
      'entity_type' => 'node',
      'bundle' => 'digital_heritage',
      'label' => 'Coverage',
    ])->save();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_submissions');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_submissions.install';
  }

  /**
   * Creates the submission display with the given field_coverage settings.
   */
  private function createDisplay(array $settings, string $widget = 'geofield_mukurtu'): void {
    EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'digital_heritage',
      'mode' => 'submission',
      'status' => TRUE,
    ])->setComponent('field_coverage', [
      'type' => $widget,
      'weight' => 18,
      'region' => 'content',
      'settings' => $settings,
    ])->save();
  }

  /**
   * The settings as they drifted, before 4.0.1.
   */
  private function driftedSettings(): array {
    return [
      'map' => [
        'map_position' => [
          'center' => ['lat' => 0.0, 'lon' => 0.0],
          'zoom' => 2,
        ],
      ],
      'toolbar' => ['drawCircle' => FALSE],
    ];
  }

  /**
   * Reads the saved field_coverage settings back.
   */
  private function savedSettings(): array {
    $display = \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->loadUnchanged(self::DISPLAY_ID);
    $this->assertNotNull($display);

    return $display->getComponent('field_coverage')['settings'];
  }

  /**
   * A drifted site is brought in line with the other content types.
   */
  public function testAlignsADriftedDisplay(): void {
    $this->createDisplay($this->driftedSettings());

    $message = mukurtu_submissions_update_40201();

    $settings = $this->savedSettings();
    $position = $settings['map']['map_position'];
    $this->assertSame('20', (string) $position['center']['lat']);
    $this->assertSame('10', (string) $position['center']['lon']);
    $this->assertSame('12', (string) $position['singlePointZoom']);
    $this->assertTrue($settings['toolbar']['drawCircle'], 'The circle drawing tool is still off.');
    $this->assertNotNull($message);
  }

  /**
   * A map the site deliberately re-centred keeps its centre.
   *
   * This is the case the guard genuinely protects: a centre that differs from
   * the old default cannot have come from the drift.
   */
  public function testLeavesACustomisedCentreAlone(): void {
    $settings = $this->driftedSettings();
    $settings['map']['map_position']['center'] = ['lat' => '45', 'lon' => '-100'];
    $this->createDisplay($settings);

    mukurtu_submissions_update_40201();

    $position = $this->savedSettings()['map']['map_position'];
    $this->assertSame('45', (string) $position['center']['lat'], 'A deliberately chosen map centre was overwritten.');
    $this->assertSame('-100', (string) $position['center']['lon'], 'A deliberately chosen map centre was overwritten.');
    // The unambiguous gaps are still filled in.
    $this->assertSame('12', (string) $position['singlePointZoom']);
  }

  /**
   * A site that swapped the widget is not touched at all.
   */
  public function testIgnoresANonMapWidget(): void {
    $this->createDisplay($this->driftedSettings(), 'geofield_mukurtu_latlon');
    // Compare against what was actually stored, not what was passed in: the
    // widget plugin normalises settings to its own schema on save, and the
    // lat/long widget has no toolbar at all.
    $before = $this->savedSettings();

    $this->assertNull(mukurtu_submissions_update_40201());
    $this->assertSame($before, $this->savedSettings(), 'A display using a different widget was modified.');
  }

  /**
   * An already-correct display reports no change.
   */
  public function testIsIdempotent(): void {
    $this->createDisplay($this->driftedSettings());

    $first = mukurtu_submissions_update_40201();
    $second = mukurtu_submissions_update_40201();

    $this->assertNotNull($first);
    $this->assertNull($second, 'The second run reported changes it did not make.');
  }

  /**
   * A site with no submission display does not error.
   */
  public function testMissingDisplayDoesNotError(): void {
    $this->assertNull(mukurtu_submissions_update_40201());
  }

}
