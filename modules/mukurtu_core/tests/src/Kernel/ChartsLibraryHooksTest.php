<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Asset\LibrariesDirectoryFileFinder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Hook\ChartsLibraryHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that charts_chartjs is pointed at the Chart.js copy the profile ships.
 *
 * Runs the hook against charts_chartjs's real library definitions and a real
 * libraries file finder, rather than through library.discovery, because
 * enabling mukurtu_core in a Kernel test pulls in most of the profile.
 */
#[CoversClass(ChartsLibraryHooks::class)]
#[Group('mukurtu_core')]
class ChartsLibraryHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The charts_chartjs library definitions, as charts ships them.
   */
  private array $libraries;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $path = \Drupal::service('extension.list.module')->getPath('charts_chartjs');
    $this->libraries = Yaml::decode(file_get_contents(\Drupal::root() . '/' . $path . '/charts_chartjs.libraries.yml'));
  }

  /**
   * Builds the hook class with a file finder for the given install profile.
   */
  private function hooks(?string $install_profile): ChartsLibraryHooks {
    return new ChartsLibraryHooks(new LibrariesDirectoryFileFinder(
      \Drupal::root(),
      $this->container->getParameter('site.path'),
      \Drupal::service('extension.list.profile'),
      $install_profile,
    ));
  }

  /**
   * Every Chart.js file is served from the profile's libraries/ directory.
   */
  public function testPointsChartJsAtProfileCopy(): void {
    $libraries = $this->libraries;
    $this->hooks('mukurtu')->libraryInfoAlter($libraries, 'charts_chartjs');

    $profile_libraries = '/' . \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/libraries/';
    $js = $libraries['charts_chartjs']['js'];
    $this->assertNotEmpty($js);
    foreach (array_keys($js) as $file) {
      $this->assertStringStartsWith($profile_libraries, $file, "$file is not served from the profile's copy.");
      $this->assertFileExists(\Drupal::root() . $file);
    }

    // Per-file options survive the rewrite.
    $annotation = $profile_libraries . 'chartjs-plugin-annotation/dist/chartjs-plugin-annotation.min.js';
    $this->assertSame(['minified' => TRUE], $js[$annotation]);
  }

  /**
   * The charts_chartjs requirements check finds the profile's copy.
   *
   * This is what clears the "using the Chart.js library via a content
   * delivery network" status report warning.
   */
  public function testRequirementsCheckFindsProfileCopy(): void {
    $profile_path = \Drupal::service('extension.list.profile')->getPath('mukurtu');
    $this->assertFileExists(\Drupal::root() . '/' . $profile_path . '/libraries/chart.js/dist/chart.umd.js');
    $this->assertDirectoryDoesNotExist(
      \Drupal::root() . '/' . $profile_path . '/libraries/chart.js/auto',
      'charts_chartjs warns when the library directory contains auto/.'
    );
  }

  /**
   * Paths to files the finder can't locate are left alone.
   *
   * The negative control for testPointsChartJsAtProfileCopy(): without the
   * profile's libraries/ directory to search, nothing is rewritten.
   */
  public function testLeavesUnfoundFilesAlone(): void {
    $libraries = $this->libraries;
    $this->hooks(NULL)->libraryInfoAlter($libraries, 'charts_chartjs');

    $this->assertSame($this->libraries, $libraries);
  }

  /**
   * Other extensions' libraries are not touched.
   */
  public function testIgnoresOtherExtensions(): void {
    $libraries = $this->libraries;
    $this->hooks('mukurtu')->libraryInfoAlter($libraries, 'charts');

    $this->assertSame($this->libraries, $libraries);
  }

}
