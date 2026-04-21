<?php

namespace Drupal\Tests\tagify\FunctionalJavascript\FieldWidget;

use Drupal\Tests\TestFileCreationTrait;
use Drupal\Tests\tagify\FunctionalJavascript\TagifyJavascriptTestBase;
use Drupal\entity_test\Entity\EntityTestMulRevPub;

/**
 * Tests tagify entity reference widget.
 *
 * @group tagify
 */
class TagifyEntityReferenceAutocompleteWidgetTest extends TagifyJavascriptTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'tagify',
    // Prevent tests from failing due to 'RuntimeException' with AJAX request.
    'js_testing_ajax_request_test',
  ];

  /**
   * Test a single value widget.
   *
   * @dataProvider providerTestSingleValueWidget
   */
  public function testSingleValueWidget($match_operator, $autocreate) {
    // Create a new entity reference field with tagify widget.
    $this->createField('tagify', 'node', 'test', 'entity_reference', [
      'target_type' => 'entity_test_mulrevpub',
    ], [
      'handler' => 'default:entity_test_mulrevpub',
      'handler_settings' => [
        'auto_create' => $autocreate,
      ],
    ], 'tagify_entity_reference_autocomplete_widget', [
      'match_operator' => $match_operator,
      'match_limit' => 10,
      'suggestions_dropdown' => 1,
      'show_entity_id' => 0,
    ]);

    // Add references to the new field.
    EntityTestMulRevPub::create(['name' => 'foo'])->save();
    EntityTestMulRevPub::create(['name' => 'bar'])->save();
    EntityTestMulRevPub::create(['name' => 'baz'])->save();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('/node/add/test');
    $page->fillField('title[0][value]', 'Test node');
    $this->click('.tagify__input');

    // Write value to get suggestion.
    $page->find('css', '.tagify__input')->setValue('foo');
    $assert_session->waitForElement('css', '.tagify__dropdown__item');
    $assert_session->waitForElementVisible('css', '.tagify__dropdown__item--active');

    $page->find('css', '.tagify__dropdown__item--active')->click();
    $page->pressButton('Save');

    $node = $this->getNodeByTitle('Test node', TRUE);
    if (!$node) {
      return;
    }

    // Get Tag id from entity reference field.
    $tag_id = $node->get('tagify')->getString();
    // Check if the node is an object and there is Tag ID.
    if (is_object($node) && $tag_id) {
      $this->assertSame("1", $tag_id);
    }
  }

  /**
   * Data provider for testSingleValueWidget().
   *
   * @return array
   *   The data.
   */
  public static function providerTestSingleValueWidget() {
    return [
      ['CONTAINS', TRUE],
      ['STARTS_WITH', TRUE],
    ];
  }

  /**
   * Test multiple value widget.
   *
   * @dataProvider providerTestMultipleValueWidget
   */
  public function testMultipleValueWidget($match_operator, $autocreate, $cardinality) {
    // Create a new entity reference field with tagify widget.
    $this->createField('tagify', 'node', 'test', 'entity_reference', [
      'target_type' => 'entity_test_mulrevpub',
      'cardinality' => $cardinality,
    ], [
      'handler' => 'default:entity_test_mulrevpub',
      'handler_settings' => [
        'auto_create' => $autocreate,
      ],
    ], 'tagify_entity_reference_autocomplete_widget', [
      'match_operator' => $match_operator,
      'match_limit' => 10,
      'suggestions_dropdown' => 0,
      'show_entity_id' => 0,
    ]);

    // Add references to the new field.
    EntityTestMulRevPub::create(['name' => 'foo'])->save();
    EntityTestMulRevPub::create(['name' => 'bar'])->save();
    EntityTestMulRevPub::create(['name' => 'baz'])->save();
    EntityTestMulRevPub::create(['name' => 'waldo'])->save();
    EntityTestMulRevPub::create(['name' => 'fred'])->save();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('/node/add/test');
    $page->fillField('title[0][value]', 'Test node');

    // Add first value.
    $this->click('.tagify__input');
    $page->find('css', '.tagify__input')->setValue('foo');
    $this->getSession()->wait(500);
    $assert_session->waitForElement('css', '.tagify__dropdown__item');
    $assert_session->waitForElementVisible('css', '.tagify__dropdown__item--active');
    $page->find('css', '.tagify__dropdown__item--active')->click();

    // Add second value.
    $this->click('.tagify__input');
    $page->find('css', '.tagify__input')->setValue('bar');
    $this->getSession()->wait(500);
    $assert_session->waitForElement('css', '.tagify__dropdown__item');
    $assert_session->waitForElementVisible('css', '.tagify__dropdown__item--active');
    $page->find('css', '.tagify__dropdown__item--active')->click();

    // Add third value.
    $this->click('.tagify__input');
    $page->find('css', '.tagify__input')->setValue('baz');
    $this->getSession()->wait(500);
    $assert_session->waitForElement('css', '.tagify__dropdown__item');
    $assert_session->waitForElementVisible('css', '.tagify__dropdown__item--active');
    $page->find('css', '.tagify__dropdown__item--active')->click();

    // Add fourth value.
    $this->click('.tagify__input');
    $page->find('css', '.tagify__input')->setValue('waldo');
    $this->getSession()->wait(500);
    $assert_session->waitForElement('css', '.tagify__dropdown__item');
    $assert_session->waitForElementVisible('css', '.tagify__dropdown__item--active');
    $page->find('css', '.tagify__dropdown__item--active')->click();
    $assert_session->waitForElementVisible('css', 'tagify__tag');

    $page->pressButton('Save');

    $node = $this->getNodeByTitle('Test node', TRUE);
    if (!$node) {
      return;
    }
    $this->assertEquals([
      ['target_id' => 1],
      ['target_id' => 2],
      ['target_id' => 3],
      ['target_id' => 4],
    ], $node->get('tagify')->getValue());
  }

  /**
   * Data provider for testMultipleValueWidget().
   *
   * @return array
   *   The data.
   */
  public static function providerTestMultipleValueWidget() {
    return [
      ['CONTAINS', TRUE, -1],
      ['CONTAINS', FALSE, -1],
    ];
  }

  /**
   * Test limited cardinality information.
   *
   * @dataProvider providerTestLimitedCardinality
   */
  public function testLimitedCardinality($match_operator, $autocreate, $cardinality) {
    // Create a new entity reference field with tagify widget.
    $this->createField('tagify', 'node', 'test', 'entity_reference', [
      'target_type' => 'entity_test_mulrevpub',
      'cardinality' => $cardinality,
    ], [
      'handler' => 'default:entity_test_mulrevpub',
      'handler_settings' => [
        'auto_create' => $autocreate,
      ],
    ], 'tagify_entity_reference_autocomplete_widget', [
      'match_operator' => $match_operator,
      'match_limit' => 10,
      'suggestions_dropdown' => 0,
      'show_entity_id' => 0,
    ]);

    // Add references to the new field.
    EntityTestMulRevPub::create(['name' => 'foo'])->save();
    EntityTestMulRevPub::create(['name' => 'bar'])->save();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('/node/add/test');
    $page->fillField('title[0][value]', 'Test node');

    // Add first value.
    $this->click('.tagify__input');
    $page->find('css', '.tagify__input')->setValue('foo');
    $assert_session->waitForElement('css', '.tagify__dropdown__item');
    $assert_session->waitForElementVisible('css', '.tagify__dropdown__item--active');
    $page->find('css', '.tagify__dropdown__item--active')->click();

    // Add second value.
    $this->click('.tagify__input');
    $page->find('css', '.tagify__input')->setValue('bar');
    $assert_session->waitForElement('css', '.tagify__dropdown__item');
    $assert_session->waitForElementVisible('css', '.tagify__dropdown__footer');
    // Assert that the footer element contains the correct text.
    $this->assertSession()->elementTextContains('css', '.tagify__dropdown__footer', 'Tags are limited to: 1');
  }

  /**
   * Data provider for testLimitedCardinality().
   *
   * @return array
   *   The data.
   */
  public static function providerTestLimitedCardinality() {
    return [
      ['CONTAINS', TRUE, 1],
      ['CONTAINS', FALSE, 1],
    ];
  }

  /**
   * Test non matching tag information.
   *
   * @dataProvider providerTestNonMatchingTag
   */
  public function testNonMatchingTag($match_operator, $autocreate) {
    // Create a new entity reference field with tagify widget.
    $this->createField('tagify', 'node', 'test', 'entity_reference', [
      'target_type' => 'entity_test_mulrevpub',
      'cardinality' => -1,
    ], [
      'handler' => 'default:entity_test_mulrevpub',
      'handler_settings' => [
        'auto_create' => $autocreate,
      ],
    ], 'tagify_entity_reference_autocomplete_widget', [
      'match_operator' => $match_operator,
      'match_limit' => 10,
      'suggestions_dropdown' => 0,
      'show_entity_id' => 0,
    ]);

    // Add references to the new field.
    EntityTestMulRevPub::create(['name' => 'foo'])->save();
    EntityTestMulRevPub::create(['name' => 'bar'])->save();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('/node/add/test');
    $page->fillField('title[0][value]', 'Test node');

    // Add value non existing value.
    $this->click('.tagify__input');
    $page->find('css', '.tagify__input')->setValue('baz');
    $assert_session->waitForElement('css', '.tagify__dropdown__item');
    $assert_session->waitForElementVisible('css', '.tagify--dropdown-item-no-match');
    // Assert that the footer element contains the correct text.
    $this->assertSession()->elementTextContains('css', '.tagify--dropdown-item-no-match', 'No matching suggestions found for: baz');
  }

  /**
   * Data provider for testNonMatchingTag().
   *
   * @return array
   *   The data.
   */
  public static function providerTestNonMatchingTag() {
    return [
      ['CONTAINS', FALSE],
    ];
  }

}
