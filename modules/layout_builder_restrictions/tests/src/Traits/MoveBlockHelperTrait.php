<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_restrictions\Traits;

use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;

/**
 * General-purpose methods for moving blocks in Layout Builder.
 */
trait MoveBlockHelperTrait {

  use ContextualLinkClickTrait;

  /**
   * Asserts the correct block labels appear in the draggable tables.
   *
   * @param string[] $expected_block_labels
   *   The expected block labels.
   */
  protected function assertBlockTable(array $expected_block_labels) {
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();
    $this->assertNotEmpty($assert->waitForText('Move the'));
    $block_tds = $page->findAll('css', '.layout-builder-components-table__block-label');
    $this->assertCount(count($block_tds), $expected_block_labels);
    /** @var \Behat\Mink\Element\NodeElement $block_td */
    foreach ($block_tds as $block_td) {
      $this->assertSame(array_shift($expected_block_labels), trim($this->normalizeThemeOutput($block_td->getText())));
    }
  }

  /**
   * Reconciles minor rendering differences in theme output.
   *
   * @param string $string
   *   A string of text.
   *
   * @return string
   *   The normalized string.
   */
  protected function normalizeThemeOutput($string) {
    // Drupal 9.5 and 10 appear to have different output for in-progress moved
    // blocks on Olivero theme. Normalize with a space before the asterisk.
    // Before: 'Body (current)*'
    // After: 'Body (current) *'.
    $string = str_replace(")*", ") *", $string);
    // @todo Make this more abstracted for other scenarios.
    // Before: 'branding*'
    // After: 'branding *'
    $string = str_replace("branding*", "branding *", $string);
    return $string;
  }

  /**
   * Waits for an element to be removed from the page.
   *
   * @param string $selector
   *   CSS selector.
   * @param int $timeout
   *   (optional) Timeout in milliseconds, defaults to 10000.
   *
   * @todo Remove in https://www.drupal.org/node/2892440.
   */
  protected function waitForNoElement($selector, $timeout = 10000) {
    $condition = "(typeof jQuery !== 'undefined' && jQuery('$selector').length === 0)";
    $this->assertJsCondition($condition, $timeout);
  }

  /**
   * Moves a block in the draggable table.
   *
   * @param string $direction
   *   The direction to move the block in the table.
   * @param string $block_label
   *   The block label.
   * @param array $updated_blocks
   *   The updated blocks order.
   */
  protected function moveBlockWithKeyboard($direction, $block_label, array $updated_blocks) {
    $keys = [
      'up' => 38,
      'down' => 40,
    ];
    $key = $keys[$direction];
    $handle = $this->findRowHandle($block_label);

    $handle->keyDown($key);
    $handle->keyUp($key);

    $handle->blur();
    $this->assertBlockTable($updated_blocks);
  }

  /**
   * Finds the row handle for a block in the draggable table.
   *
   * @param string $block_label
   *   The block label.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The row handle element.
   */
  protected function findRowHandle($block_label) {
    $assert_session = $this->assertSession();
    return $assert_session->elementExists('css', "[data-drupal-selector=\"edit-components\"] td:contains(\"$block_label\") a.tabledrag-handle");
  }

  /**
   * Asserts that blocks are in the correct order for a region.
   *
   * @param int $section_delta
   *   The section delta.
   * @param string $region
   *   The region.
   * @param array $expected_block_selectors
   *   The block selectors.
   */
  protected function assertRegionBlocksOrder($section_delta, $region, array $expected_block_selectors) {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->waitForNoElement('#drupal-off-canvas');

    $region_selector = "[data-layout-delta=\"$section_delta\"] [data-region=\"$region\"]";

    // Get all blocks currently in the region.
    $blocks = $page->findAll('css', "$region_selector [data-layout-block-uuid]");
    $this->assertCount(count($expected_block_selectors), $blocks);

    /** @var \Behat\Mink\Element\NodeElement $block */
    foreach ($blocks as $block) {
      $block_selector = array_shift($expected_block_selectors);
      $assert_session->elementsCount('css', "$region_selector $block_selector", 1);
      $expected_block = $page->find('css', "$region_selector $block_selector");
      $this->assertSame($expected_block->getAttribute('data-layout-block-uuid'), $block->getAttribute('data-layout-block-uuid'));
    }
  }

  /**
   * Open block for the body field.
   *
   * @param int $delta
   *   The section delta where the field should be.
   * @param string $region
   *   The region where the field should be.
   * @param string $field
   *   The field class that should be targeted.
   * @param array $initial_blocks
   *   The initial blocks that should be shown in the draggable table.
   */
  protected function openMoveForm($delta, $region, $field, array $initial_blocks) {
    $assert = $this->assertSession();
    $body_field_locator = "[data-layout-delta=\"$delta\"] [data-region=\"$region\"] ." . $field;
    $this->clickContextualLink($body_field_locator, 'Move');
    $this->assertNotEmpty($assert->waitForElementVisible('named', ['select', 'Region']));
    $assert->fieldValueEquals('Region', "$delta:$region");
    $this->assertBlockTable($initial_blocks);
  }

}
