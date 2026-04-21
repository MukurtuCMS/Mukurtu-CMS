<?php

namespace Drupal\Tests\features\Unit;

use Drupal\features\Package;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\features\Package
 * @group features
 */
class PackageTest extends TestCase {

  /**
   * @covers ::setDependencies
   */
  public function testSetDependencies() {
    $package = new Package('test_feature', []);

    $this->assertEquals([], $package->getDependencies());
    $package->setDependencies([
      'some_module',
      'my_module',
      'my_module',
      'test_feature',
    ]);
    // Test that duplicates are removed, results sorted, and the package cannot
    /// require itself.
    $expected = [
      'my_module',
      'some_module',
    ];
    $this->assertEquals($expected, $package->getDependencies());
  }

  /**
   * @covers ::appendDependency
   */
  public function testAppendDependency() {
    $package = new Package('test_feature', []);

    $this->assertEquals([], $package->getDependencies());
    $dependencies = [
      'some_module',
      'my_module',
      'my_module',
      'test_feature',
    ];
    foreach ($dependencies as $dependency) {
      $package->appendDependency($dependency);
    }
    // Test that duplicates are removed, results sorted, and the package cannot
    /// require itself.
    $expected = [
      'my_module',
      'some_module',
    ];
    $this->assertEquals($expected, $package->getDependencies());
  }

  /**
   * @covers ::setFeaturesInfo
   */
  public function testSetFeaturesInfo() {
    $package = new Package('test_feature', []);

    $this->assertEquals([], $package->getFeaturesInfo());
    $package->setFeaturesInfo(['bundle' => 'test_bundle']);
    $this->assertEquals(['bundle' => 'test_bundle'], $package->getFeaturesInfo());
    $this->assertEquals('test_bundle', $package->getBundle());
  }

  /**
   * {@inheritDoc}
   */
  public function testGetConfig() {
    $package = new Package('test_feature', ['config' => ['test_config_a', 'test_config_b']]);
    $this->assertEquals(['test_config_a', 'test_config_b'], $package->getConfig());
    return $package;
  }

  /**
   * The test append config.
   *
   * @depends testGetConfig
   * @covers ::appendConfig
   */
  public function testAppendConfig(Package $package) {
    $package->appendConfig('test_config_a');
    $package->appendConfig('test_config_c');

    $this->assertEquals(['test_config_a', 'test_config_b', 'test_config_c'], array_values($package->getConfig()));
    return $package;
  }

  /**
   * The test remove config.
   *
   * @depends testAppendConfig
   * @covers ::removeConfig
   */
  public function testRemoveConfig(Package $package) {
    $package->removeConfig('test_config_a');

    $this->assertEquals(['test_config_b', 'test_config_c'], array_values($package->getConfig()));
  }

}
