<?php

namespace Drupal\Tests\facets\Unit\Plugin\widget;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Tests\facets\Unit\Drupal10CompatibilityUnitTestCase;
use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetInterface;
use Drupal\facets\Result\Result;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Drupal\facets\FacetSource\FacetSourcePluginManager;
use Drupal\facets\UrlProcessor\UrlProcessorInterface;

/**
 * Base class for widget unit tests.
 */
abstract class WidgetTestBase extends Drupal10CompatibilityUnitTestCase {

  /**
   * The widget to be tested.
   *
   * @var \Drupal\facets\Widget\WidgetPluginInterface
   */
  protected $widget;

  /**
   * The facet used for the widget test.
   *
   * @var \Drupal\facets\FacetInterface
   */
  protected $facet;

  /**
   * An array containing the results for the widget.
   *
   * @var \Drupal\facets\Result\Result[]
   */
  protected $originalResults;

  /**
   * Sets up the container and other variables used in all the tests.
   */
  protected function setUp(): void {
    parent::setUp();

    $facet = new Facet([], 'facets_facet');
    $this->facet = $facet;
    /** @var \Drupal\facets\Result\Result[] $original_results */
    $original_results = [
      new Result($facet, 'llama', 'Llama', 10),
      new Result($facet, 'badger', 'Badger', 20),
      new Result($facet, 'duck', 'Duck', 15),
      new Result($facet, 'alpaca', 'Alpaca', 9),
    ];

    foreach ($original_results as $original_result) {
      $original_result->setUrl(new Url('test'));
    }
    $this->originalResults = $original_results;

    // Create a container, so we can access string translation.
    $string_translation = $this->prophesize(TranslationInterface::class);
    $url_generator = $this->prophesize(UrlGeneratorInterface::class);
    $widget_manager = $this->prophesize(WidgetPluginManager::class);

    $url_processor = $this->createMock(UrlProcessorInterface::class);
    $manager = $this->createMock(FacetSourcePluginManager::class);
    $manager->method('createInstance')->willReturn($url_processor);

    $em = $this->prophesize(EntityTypeManager::class);

    $container = new ContainerBuilder();
    $container->set('plugin.manager.facets.widget', $widget_manager->reveal());
    $container->set('plugin.manager.facets.url_processor', $manager);
    $container->set('string_translation', $string_translation->reveal());
    $container->set('url_generator', $url_generator->reveal());
    $container->set('entity_type.manager', $em->reveal());
    \Drupal::setContainer($container);
  }

  /**
   * Tests default configuration.
   */
  public function testDefaultConfiguration() {
    $default_config = $this->widget->defaultConfiguration();
    $this->assertEquals(['show_numbers' => FALSE, 'soft_limit' => 0], $default_config);
  }

  /**
   * Tests get query type.
   */
  public function testGetQueryType() {
    $result = $this->widget->getQueryType();
    $this->assertEquals(NULL, $result);
  }

  /**
   * Tests default for required properties.
   */
  public function testIsPropertyRequired() {
    $this->assertFalse($this->widget->isPropertyRequired('llama', 'owl'));
  }

  /**
   * Build a formattable markup object to use as assertion.
   *
   * @param string $text
   *   Text to display.
   * @param string $raw_value
   *   Raw value of the result.
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   * @param int $count
   *   Number of results.
   * @param bool $active
   *   Link is active.
   * @param bool $show_numbers
   *   Numbers are displayed.
   *
   * @return array
   *   A render array.
   */
  protected function buildLinkAssertion($text, $raw_value, FacetInterface $facet, $count = 0, $active = FALSE, $show_numbers = TRUE) {
    return [
      '#theme' => 'facets_result_item',
      '#raw_value' => $raw_value,
      '#facet' => $facet,
      '#value' => $text,
      '#show_count' => $show_numbers && ($count !== NULL),
      '#count' => $count,
      '#is_active' => $active,
    ];
  }

}
