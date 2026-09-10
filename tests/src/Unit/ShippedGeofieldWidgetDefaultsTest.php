<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Asserts the shipped map widget defaults every geofield form display uses.
 *
 * Each of these was set by its own update hook during 4.0.x, with a matching
 * kernel test per content type. The hooks are gone in 4.0.1, so what matters
 * now is that the shipped form displays actually carry the values, and that
 * they carry the *same* values: a map that opens at a different centre or zoom
 * on one content type than another is a bug users notice immediately.
 *
 * The displays are discovered by scanning for the widget rather than listed
 * here, so a new bundle with a coverage map is covered the moment it ships.
 * That is deliberate. The submission form display had quietly drifted - no
 * circle tool, centred on the Gulf of Guinea at 0,0, and no singlePointZoom -
 * because the update hooks that set these values only ever touched the six
 * bundles that existed when each hook was written, and no test looked wider.
 *
 * A pure filesystem and YAML check, so no Drupal bootstrap is needed.
 */
#[Group('mukurtu')]
class ShippedGeofieldWidgetDefaultsTest extends UnitTestCase {

  /**
   * The widget whose defaults this test governs.
   */
  private const WIDGET = 'geofield_mukurtu';

  /**
   * Expected map_position values, as shipped.
   *
   * A world view rather than a point, so an author with no coordinates yet
   * sees the whole map, and a close zoom once a single point is chosen.
   */
  private const MAP_POSITION = [
    'zoom' => '2',
    'minZoom' => '1',
    'maxZoom' => '18',
    'singlePointZoom' => '12',
  ];

  /**
   * Expected centre of the initial view.
   */
  private const CENTRE = ['lat' => '20', 'lon' => '10'];

  /**
   * Resolves the profile root from this file's location.
   */
  private static function profileRoot(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Every shipped form display using the Mukurtu geofield widget.
   */
  public static function widgetProvider(): \Generator {
    $root = self::profileRoot();
    $patterns = [
      "$root/config/install/core.entity_form_display.*.yml",
      "$root/modules/*/config/install/core.entity_form_display.*.yml",
    ];

    foreach ($patterns as $pattern) {
      foreach (glob($pattern) ?: [] as $path) {
        $display = Yaml::parseFile($path);
        foreach ($display['content'] ?? [] as $field => $component) {
          if (($component['type'] ?? NULL) !== self::WIDGET) {
            continue;
          }
          $label = str_replace("$root/", '', $path) . " ($field)";
          yield $label => [$label, $component];
        }
      }
    }
  }

  /**
   * Config YAML types booleans and numbers loosely, so compare as strings.
   *
   * The shipped files are inconsistent about quoting - '1' in some, true in
   * others - and the config system casts both to the same stored value, so
   * comparing raw would flag differences that do not exist.
   */
  private function normalise(mixed $value): string {
    if (is_bool($value)) {
      return $value ? '1' : '0';
    }
    return (string) $value;
  }

  #[DataProvider('widgetProvider')]
  public function testMapOpensOnTheSharedWorldView(string $label, array $component): void {
    $position = $component['settings']['map']['map_position'] ?? NULL;
    $this->assertIsArray($position, "$label has no map.map_position settings.");

    foreach (self::MAP_POSITION as $key => $expected) {
      $this->assertArrayHasKey($key, $position, "$label is missing map_position.$key.");
      $this->assertSame(
        $expected,
        $this->normalise($position[$key]),
        "$label has a different map_position.$key from every other coverage map."
      );
    }

    foreach (self::CENTRE as $key => $expected) {
      $this->assertSame(
        $expected,
        $this->normalise($position['center'][$key] ?? NULL),
        "$label centres its map somewhere different from every other coverage map."
      );
    }
  }

  /**
   * The circle tool stays available on every coverage map.
   *
   * Circles express an approximate or radial extent, which is how a lot of
   * cultural heritage coverage is actually described, so a map without the
   * tool silently forces authors into drawing polygons instead.
   */
  #[DataProvider('widgetProvider')]
  public function testCircleToolIsEnabled(string $label, array $component): void {
    $toolbar = $component['settings']['toolbar'] ?? NULL;
    $this->assertIsArray($toolbar, "$label has no toolbar settings.");
    $this->assertSame(
      '1',
      $this->normalise($toolbar['drawCircle'] ?? NULL),
      "$label has the circle drawing tool turned off."
    );
  }

  /**
   * Sanity check on the provider itself.
   *
   * A glob that matched nothing would make every test above pass without
   * asserting anything, which is the failure mode this test exists to avoid.
   */
  public function testProviderFindsTheShippedDisplays(): void {
    $found = iterator_to_array(self::widgetProvider());
    $this->assertGreaterThanOrEqual(
      7,
      count($found),
      'Expected at least the seven known coverage map form displays; the glob has stopped matching.'
    );
  }

}
