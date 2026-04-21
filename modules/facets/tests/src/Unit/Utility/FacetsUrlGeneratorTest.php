<?php

namespace Drupal\Tests\facets\Unit\Utility;

use Drupal\Tests\facets\Unit\Drupal10CompatibilityUnitTestCase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\facets\UrlProcessor\UrlProcessorPluginManager;
use Drupal\facets\Utility\FacetsUrlGenerator;
use Prophecy\Argument;

/**
 * Unit test for URL Generator Service.
 *
 * @group facets
 * @coversDefaultClass \Drupal\facets\Utility\FacetsUrlGenerator
 */
class FacetsUrlGeneratorTest extends Drupal10CompatibilityUnitTestCase {

  /**
   * Tests that passing an invalid facet ID throws an InvalidArgumentException.
   *
   * @covers ::getUrl
   */
  public function testEmptyArray() {
    $url_processor_plugin_manager = $this->prophesize(UrlProcessorPluginManager::class)->reveal();

    $storage = $this->prophesize(EntityStorageInterface::class);
    $etm = $this->prophesize(EntityTypeManagerInterface::class);
    $etm->getStorage('facets_facet')->willReturn($storage->reveal());

    $url_generator = new FacetsUrlGenerator($url_processor_plugin_manager, $etm->reveal());

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("The active filters passed in are invalid. They should look like: ['facet_id' => ['value1', 'value2']]");
    $url_generator->getUrl([]);
  }

  /**
   * Tests that passing an invalid facet ID throws an InvalidArgumentException.
   *
   * @covers ::getUrl
   */
  public function testInvalidArray() {
    $url_processor_plugin_manager = $this->prophesize(UrlProcessorPluginManager::class)->reveal();

    $storage = $this->prophesize(EntityStorageInterface::class);
    $etm = $this->prophesize(EntityTypeManagerInterface::class);
    $etm->getStorage('facets_facet')->willReturn($storage->reveal());

    $url_generator = new FacetsUrlGenerator($url_processor_plugin_manager, $etm->reveal());

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("The active filters passed in are invalid. They should look like: [imaginary => ['value1', 'value2']]");
    $url_generator->getUrl(['imaginary' => 'unicorn']);
  }

  /**
   * Tests that passing an invalid facet ID throws an InvalidArgumentException.
   *
   * @covers ::getUrl
   */
  public function testInvalidFacet() {
    $url_processor_plugin_manager = $this->prophesize(UrlProcessorPluginManager::class)->reveal();

    $storage = $this->prophesize(EntityStorageInterface::class);
    $storage->load(Argument::type('string'))->willReturn(NULL);
    $etm = $this->prophesize(EntityTypeManagerInterface::class);
    $etm->getStorage('facets_facet')->willReturn($storage->reveal());

    $url_generator = new FacetsUrlGenerator($url_processor_plugin_manager, $etm->reveal());

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The Facet imaginary could not be loaded.');
    $url_generator->getUrl(['imaginary' => ['unicorn']]);
  }

}
