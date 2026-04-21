<?php

namespace Drupal\Tests\facets_searchbox_widget\Unit\Plugin\widget;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\facets\Entity\Facet;
use Drupal\facets\Plugin\facets\widget\LinksWidget;
use Drupal\facets\Result\Result;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\facets\FacetSource\FacetSourcePluginManager;
use Drupal\facets\UrlProcessor\UrlProcessorInterface;
use Drupal\facets\Utility\FacetsUrlGenerator;
use Drupal\Tests\facets\Unit\Plugin\widget\LinksWidgetTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\ParameterBag;
use Drupal\Tests\Core\Routing\TestRouterInterface;

/**
 * Unit test for widget.
 *
 * @group facets
 */
class SearchboxLinksWidgetTest extends LinksWidgetTest {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->widget = new LinksWidget([], 'links_widget', []);
  }

  /**
   * Tests widget without filters.
   */
  public function testNoFilterResults() {
    $facet = $this->facet;
    $facet->setResults($this->originalResults);

    $this->widget->setConfiguration(['show_numbers' => TRUE]);
    $output = $this->widget->build($facet);

    $this->assertSame('array', gettype($output));
    $this->assertCount(4, $output['#items']);

    $expected_links = [
      $this->buildLinkAssertion('Llama', 'llama', $facet, 10),
      $this->buildLinkAssertion('Badger', 'badger', $facet, 20),
      $this->buildLinkAssertion('Duck', 'duck', $facet, 15),
      $this->buildLinkAssertion('Alpaca', 'alpaca', $facet, 9),
    ];
    foreach ($expected_links as $index => $value) {
      $this->assertSame('array', gettype($output['#items'][$index]));
      $this->assertEquals($value, $output['#items'][$index]['#title']);
      $this->assertSame('array', gettype($output['#items'][$index]['#title']));
      $this->assertEquals('link', $output['#items'][$index]['#type']);
      $this->assertEquals(['facet-item'], $output['#items'][$index]['#wrapper_attributes']['class']);
    }
  }

  /**
   * Test widget with 2 active items.
   */
  public function testActiveItems() {
    $original_results = $this->originalResults;
    $original_results[0]->setActiveState(TRUE);
    $original_results[3]->setActiveState(TRUE);

    $facet = $this->facet;
    $facet->setResults($original_results);

    $this->widget->setConfiguration(['show_numbers' => TRUE]);
    $output = $this->widget->build($facet);

    $this->assertSame('array', gettype($output));
    $this->assertCount(4, $output['#items']);

    $expected_links = [
      $this->buildLinkAssertion('Llama', 'llama', $facet, 10, TRUE),
      $this->buildLinkAssertion('Badger', 'badger', $facet, 20),
      $this->buildLinkAssertion('Duck', 'duck', $facet, 15),
      $this->buildLinkAssertion('Alpaca', 'alpaca', $facet, 9, TRUE),
    ];
    foreach ($expected_links as $index => $value) {
      $this->assertSame('array', gettype($output['#items'][$index]));
      $this->assertEquals($value, $output['#items'][$index]['#title']);
      $this->assertEquals('link', $output['#items'][$index]['#type']);
      if ($index === 0 || $index === 3) {
        $this->assertEquals(['is-active'], $output['#items'][$index]['#attributes']['class']);
      }
      $this->assertEquals(['facet-item'], $output['#items'][$index]['#wrapper_attributes']['class']);
    }
  }

  /**
   * Tests widget, make sure hiding and showing numbers works.
   */
  public function testHideNumbers() {
    $original_results = $this->originalResults;
    $original_results[1]->setActiveState(TRUE);

    $facet = $this->facet;
    $facet->setResults($original_results);

    $this->widget->setConfiguration(['show_numbers' => FALSE]);
    $output = $this->widget->build($facet);

    $this->assertSame('array', gettype($output));
    $this->assertCount(4, $output['#items']);

    $expected_links = [
      $this->buildLinkAssertion('Llama', 'llama', $facet, 10, FALSE, FALSE),
      $this->buildLinkAssertion('Badger', 'badger', $facet, 20, TRUE, FALSE),
      $this->buildLinkAssertion('Duck', 'duck', $facet, 15, FALSE, FALSE),
      $this->buildLinkAssertion('Alpaca', 'alpaca', $facet, 9, FALSE, FALSE),
    ];
    foreach ($expected_links as $index => $value) {
      $this->assertSame('array', gettype($output['#items'][$index]));
      $this->assertEquals($value, $output['#items'][$index]['#title']);
      $this->assertEquals('link', $output['#items'][$index]['#type']);
      if ($index === 1) {
        $this->assertEquals(['is-active'], $output['#items'][$index]['#attributes']['class']);
      }
      $this->assertEquals(['facet-item'], $output['#items'][$index]['#wrapper_attributes']['class']);
    }

    // Enable the 'show_numbers' setting again to make sure that the switch
    // between those settings works.
    $this->widget->setConfiguration(['show_numbers' => TRUE]);

    $output = $this->widget->build($facet);

    $this->assertSame('array', gettype($output));
    $this->assertCount(4, $output['#items']);

    $expected_links = [
      $this->buildLinkAssertion('Llama', 'llama', $facet, 10),
      $this->buildLinkAssertion('Badger', 'badger', $facet, 20, TRUE),
      $this->buildLinkAssertion('Duck', 'duck', $facet, 15),
      $this->buildLinkAssertion('Alpaca', 'alpaca', $facet, 9),
    ];
    foreach ($expected_links as $index => $value) {
      $this->assertSame('array', gettype($output['#items'][$index]));
      $this->assertEquals($value, $output['#items'][$index]['#title']);
      $this->assertEquals('link', $output['#items'][$index]['#type']);
      if ($index === 1) {
        $this->assertEquals(['is-active'], $output['#items'][$index]['#attributes']['class']);
      }
      $this->assertEquals(['facet-item'], $output['#items'][$index]['#wrapper_attributes']['class']);
    }
  }

  /**
   * Tests for links widget with children.
   */
  public function testChildren() {
    $original_results = $this->originalResults;

    $facet = $this->facet;
    $child = new Result($facet, 'snake', 'Snake', 5);
    $original_results[1]->setActiveState(TRUE);
    $original_results[1]->setChildren([$child]);

    $facet->setResults($original_results);

    $this->widget->setConfiguration(['show_numbers' => TRUE]);
    $output = $this->widget->build($facet);

    $this->assertSame('array', gettype($output));
    $this->assertCount(4, $output['#items']);

    $expected_links = [
      $this->buildLinkAssertion('Llama', 'llama', $facet, 10),
      $this->buildLinkAssertion('Badger', 'badger', $facet, 20, TRUE),
      $this->buildLinkAssertion('Duck', 'duck', $facet, 15),
      $this->buildLinkAssertion('Alpaca', 'alpaca', $facet, 9),
    ];
    foreach ($expected_links as $index => $value) {
      $this->assertSame('array', gettype($output['#items'][$index]));
      $this->assertEquals($value, $output['#items'][$index]['#title']);
      $this->assertEquals('link', $output['#items'][$index]['#type']);
      if ($index === 1) {
        $this->assertEquals(['is-active'], $output['#items'][$index]['#attributes']['class']);
        $this->assertEquals(['facet-item', 'facet-item--expanded'], $output['#items'][$index]['#wrapper_attributes']['class']);
      }
      else {
        $this->assertEquals(['facet-item'], $output['#items'][$index]['#wrapper_attributes']['class']);
      }
    }
  }

  /**
   * Tests the rest link.
   */
  public function testResetLink() {
    $facet = new Facet([], 'facets_facet');
    $facet->setResults($this->originalResults);

    $output = $this->widget->build($facet);

    $this->assertSame('array', gettype($output));
    $this->assertCount(4, $output['#items']);

    $request = new Request();
    $request->query->set('f', []);

    $request_stack = new RequestStack();
    $request_stack->push($request);

    $this->createContainer();
    $container = \Drupal::getContainer();
    $container->set('request_stack', $request_stack);
    \Drupal::setContainer($container);

    // Enable the show reset link.
    $this->widget->setConfiguration(['show_reset_link' => TRUE]);
    $output = $this->widget->build($facet);

    // Check that we now have more results.
    $this->assertSame('array', gettype($output));
    $this->assertCount(5, $output['#items']);
  }

  /**
   * {@inheritdoc}
   */
  public function testDefaultConfiguration() {
    $default_config = $this->widget->defaultConfiguration();
    $this->assertArrayHasKey('show_numbers', $default_config);
    $this->assertArrayHasKey('soft_limit', $default_config);
    $this->assertArrayHasKey('show_reset_link', $default_config);
    $this->assertArrayHasKey('reset_text', $default_config);
    $this->assertArrayHasKey('soft_limit_settings', $default_config);
    $this->assertArrayHasKey('show_less_label', $default_config['soft_limit_settings']);
    $this->assertArrayHasKey('show_more_label', $default_config['soft_limit_settings']);

    $this->assertEquals(FALSE, $default_config['show_numbers']);
    $this->assertEquals(0, $default_config['soft_limit']);
    $this->assertEquals(FALSE, $default_config['show_reset_link']);
  }

  /**
   * Sets up a container.
   */
  protected function createContainer() {
    $router = $this->createMock(TestRouterInterface::class);
    $router->expects($this->any())
      ->method('matchRequest')
      ->willReturn([
        '_raw_variables' => new ParameterBag([]),
        '_route' => 'test',
      ]);

    $url_processor = $this->createMock(UrlProcessorInterface::class);

    $manager = $this->createMock(FacetSourcePluginManager::class);
    $manager->expects($this->atLeastOnce())
      ->method('createInstance')
      ->willReturn($url_processor);

    $facets_url_generator = $this->createMock(FacetsUrlGenerator::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $em = $this->createMock(EntityTypeManagerInterface::class);
    $em->expects($this->atLeastOnce())
      ->method('getStorage')
      ->willReturn($storage);

    $container = new ContainerBuilder();
    $container->set('router.no_access_checks', $router);
    $container->set('entity_type.manager', $em);
    $container->set('plugin.manager.facets.url_processor', $manager);
    $container->set('facets.utility.url_generator', $facets_url_generator);
    \Drupal::setContainer($container);
  }

}
