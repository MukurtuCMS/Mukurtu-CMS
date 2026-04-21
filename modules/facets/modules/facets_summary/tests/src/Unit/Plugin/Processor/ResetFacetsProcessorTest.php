<?php

namespace Drupal\Tests\facets_summary\Unit\Plugin\Processor;

use Drupal\Tests\facets\Unit\Drupal10CompatibilityUnitTestCase;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\facets_summary\Entity\FacetsSummary;
use Drupal\facets_summary\Plugin\facets_summary\processor\ResetFacetsProcessor;

/**
 * Provides the ResetFacetsProcessorTest class.
 *
 * @group facets
 * @coversDefaultClass \Drupal\facets_summary\Plugin\facets_summary\processor\ResetFacetsProcessor
 */
class ResetFacetsProcessorTest extends Drupal10CompatibilityUnitTestCase {

  /**
   * The processor we're testing.
   *
   * @var \Drupal\facets_summary\Processor\ProcessorInterface|\Drupal\facets_summary\Processor\BuildProcessorInterface
   */
  protected $processor;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $string_translation = $this->prophesize(TranslationInterface::class);

    $container = new ContainerBuilder();
    $container->set('string_translation', $string_translation->reveal());
    \Drupal::setContainer($container);

    $this->processor = new ResetFacetsProcessor([
      'settings' => [
        'link_text' => 'Text',
        'position' => ResetFacetsProcessor::POSITION_BEFORE,
      ],
    ], 'reset_facets', []);
  }

  /**
   * Tests the is hidden method.
   *
   * @covers ::isHidden
   */
  public function testIsHidden() {
    $this->assertFalse($this->processor->isHidden());
  }

  /**
   * Tests the is locked method.
   *
   * @covers ::isLocked
   */
  public function testIsLocked() {
    $this->assertFalse($this->processor->isLocked());
  }

  /**
   * Tests the build method.
   *
   * @covers ::build
   */
  public function testBuildWithEmptyItems() {
    $summary = new FacetsSummary([], 'facets_summary');
    $summary->setFacetSourceId('foo');
    $config = [
      'processor_id' => 'reset_facets',
      'weights' => [],
      'settings' => [
        'link_text' => 'Text',
        'position' => ResetFacetsProcessor::POSITION_BEFORE,
      ],
    ];
    $summary->addProcessor($config);

    $result = $this->processor->build($summary, ['foo'], []);
    $this->assertSame('array', gettype($result));
  }

}
