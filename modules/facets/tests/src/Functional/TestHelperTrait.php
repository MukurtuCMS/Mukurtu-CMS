<?php

namespace Drupal\Tests\facets\Functional;

use Drupal\Component\Render\FormattableMarkup;

/**
 * Adds helpers for test methods.
 */
trait TestHelperTrait {

  /**
   * Asserts that a facet with the specified label is found and is a link.
   */
  protected function assertFacetLabel(string $label, mixed $index = 0, ?string $message = ''): void {
    $links = $this->findFacetLink($label);
    $message = ($message ?: strtr('Link with label %label found.', ['%label' => $label]));
    $this->assertArrayHasKey($index, $links, $message);
  }

  /**
   * Asserts that a facet with the given label is active.
   */
  protected function checkFacetIsActive(string $label): void {
    $links = $this->findFacetLink($label);
    $this->assertArrayHasKey(0, $links);
  }

  /**
   * Asserts that a facet with the given label is not active.
   */
  protected function checkFacetIsNotActive(string $label): void {
    $label = strip_tags($label);
    $links = $this->xpath('//a/span[1][normalize-space(text())=:label]', [':label' => $label]);
    $this->assertArrayHasKey(0, $links);
  }

  /**
   * Asserts that a facet block does not appear.
   */
  protected function assertNoFacetBlocksAppear() {
    foreach ($this->blocks as $block) {
      $xpath = $this->xpath('//div[@id = :id]/div[@class="facet-empty"]', [':id' => 'block-' . $block->id()]);
      if (!$xpath) {
        $this->assertEmpty($xpath);
      }
      else {
        $this->assertTrue($this->xpath('//div[@id = :id]/div[@class="facet-empty"]', [':id' => 'block-' . $block->id()]));
      }
    }
  }

  /**
   * Asserts that a facet block appears.
   */
  protected function assertFacetBlocksAppear() {
    foreach ($this->blocks as $block) {
      $this->xpath('//div[@id = :id]', [':id' => 'block-' . $block->id()]);
      $this->assertSession()->pageTextContains($block->label());
    }
  }

  /**
   * Asserts that a string is found before another string in the source.
   *
   * This uses the simpletest's getRawContent method to search in the source of
   * the page for the position of 2 strings and that the first argument is
   * before the second argument's position.
   *
   * @param string $x
   *   A string.
   * @param string $y
   *   Another string.
   */
  protected function assertStringPosition($x, $y) {
    $this->assertSession()->pageTextContains($x);
    $this->assertSession()->pageTextContains($y);

    $x_position = strpos($this->getTextContent(), $x);
    $y_position = strpos($this->getTextContent(), $y);

    $message = new FormattableMarkup(
      'Assert that %x is before %y in the source',
      [
        '%x' => $x,
        '%y' => $y,
      ]
    );
    $this->assertTrue($x_position < $y_position, $message);
  }

  /**
   * Clicks the test facet.
   */
  protected function clickFacet() {
    $this->drupalGet('search-api-test-fulltext');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertFacetLabel('item');
    $this->assertFacetLabel('article');

    $this->clickLink('item');

    $this->assertSession()->statusCodeEquals(200);
    $this->checkFacetIsActive('item');
    $this->assertFacetLabel('article');
  }

  /**
   * Click a link by partial name.
   *
   * @param string $label
   *   The label of a link to click.
   */
  protected function clickPartialLink($label) {
    $label = (string) $label;

    $xpath = $this->assertSession()->buildXPathQuery('//a[starts-with(normalize-space(), :label)]', [':label' => $label]);
    $links = $this->getSession()->getPage()->findAll('xpath', $xpath);
    $links[0]->click();
  }

  /**
   * Use xpath to find a facet link.
   *
   * @param string $label
   *   Label of a link to find.
   *
   * @return array
   *   An array of links with the facets.
   */
  protected function findFacetLink($label) {
    $label = (string) $label;
    $label = strip_tags($label);
    $matches = [];

    if (preg_match('/(.*)\s\((\d+)\)/', $label, $matches)) {
      $links = $this->xpath(
        '//a//span[normalize-space(text())=:label]/following-sibling::span[normalize-space(text())=:count]',
        [
          ':label' => $matches[1],
          ':count' => '(' . $matches[2] . ')',
        ]
      );
    }
    else {
      $links = $this->xpath('//a//span[normalize-space(text())=:label]', [':label' => $label]);
    }

    return $links;
  }

  /**
   * Convert facet name to machine name.
   *
   * @param string $facet_name
   *   The name of the facet.
   *
   * @return string
   *   The facet name changed to a machine name.
   */
  protected function convertNameToMachineName($facet_name) {
    return preg_replace('@[^a-zA-Z0-9_]+@', '_', strtolower($facet_name));
  }

}
