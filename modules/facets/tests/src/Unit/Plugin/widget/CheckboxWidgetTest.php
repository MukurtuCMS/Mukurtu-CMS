<?php

namespace Drupal\Tests\facets\Unit\Plugin\widget;

use Drupal\facets\Plugin\facets\widget\CheckboxWidget;

/**
 * Unit test for widget.
 *
 * @group facets
 */
class CheckboxWidgetTest extends WidgetTestBase {

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->widget = new CheckboxWidget(['show_numbers' => TRUE], 'checkbox_widget', []);
  }

  /**
   * Tests widget without filters.
   */
  public function testNoFilterResults() {
    $facet = $this->facet;
    $facet->setResults($this->originalResults);

    $output = $this->widget->build($facet);

    $this->assertSame('array', gettype($output));
    $this->assertCount(4, $output['#items']);

    $this->assertEquals(['facet-inactive', 'js-facets-checkbox-links'], $output['#attributes']['class']);

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

}
