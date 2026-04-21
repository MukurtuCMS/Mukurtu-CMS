<?php

namespace Drupal\Tests\search_api\Functional;

use Behat\Mink\Element\NodeElement;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestStringId;
use Drupal\search_api\Entity\Index;
use Drupal\search_api_test_bulk_form\TypedData\FooDataDefinition;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Search API bulk form Views field plugin.
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\views\field\SearchApiBulkForm
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class SearchApiBulkFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api_test_bulk_form',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The test index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->index = Index::load('test_index');
    $this->createIndexedContent();
    $this->drupalLogin($this->createUser(['view test entity']));
  }

  /**
   * Tests the Views bulk form.
   */
  public function testBulkForm() {
    $this->drupalGet('/search-api-test-bulk-form');
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Check that only entity-based datasource rows have checkboxes. The view
    // is sorted by item ID, so the following assertions are safe.
    $this->assertCheckboxExistsInRow('entity:entity_test/1:en');
    $this->assertCheckboxExistsInRow('entity:entity_test/2:en');
    $this->assertCheckboxExistsInRow('entity:entity_test_string_id/1:und');
    $this->assertCheckboxExistsInRow('entity:entity_test_string_id/2:und');
    $this->assertCheckboxNotExistsInRow('search_api_test/1:en');
    $this->assertCheckboxNotExistsInRow('search_api_test/2:en');

    $assert->fieldExists('search_api_bulk_form[1]');
    $assert->fieldExists('search_api_bulk_form[2]');
    $assert->fieldExists('search_api_bulk_form[3]');
    $assert->fieldNotExists('search_api_bulk_form[4]');
    $assert->fieldNotExists('search_api_bulk_form[5]');

    // Check two entity_test rows with a compatible action.
    $this->checkCheckboxInRow('entity:entity_test/1:en');
    $this->checkCheckboxInRow('entity:entity_test/2:en');
    $page->selectFieldOption('Action', 'Search API test bulk form action: entity_test');
    $page->pressButton('Apply to selected items');
    $assert->pageTextContains('Search API test bulk form action: entity_test was applied to 2 items.');
    $this->assertActionsApplied([
      ['search_api_test_bulk_form_entity_test', 'entity_test', '1'],
      ['search_api_test_bulk_form_entity_test', 'entity_test', '2'],
    ]);

    // Check two entity_test rows with a compatible action and one that is not
    // compatible with the applied action.
    $this->checkCheckboxInRow('entity:entity_test/1:en');
    $this->checkCheckboxInRow('entity:entity_test/2:en');
    $this->checkCheckboxInRow('entity:entity_test_string_id/2:und');
    $page->selectFieldOption('Action', 'Search API test bulk form action: entity_test');
    $page->pressButton('Apply to selected items');
    $assert->pageTextContains('Search API test bulk form action: entity_test was applied to 2 items.');
    // Check that the incompatible row was not executed.
    $entity = EntityTestStringId::load(2);
    $assert->pageTextContains("Row {$entity->label()} removed from selection as it's not compatible with Search API test bulk form action: entity_test action.");
    $this->assertActionsApplied([
      ['search_api_test_bulk_form_entity_test', 'entity_test', '1'],
      ['search_api_test_bulk_form_entity_test', 'entity_test', '2'],
    ]);

    // Use the other action on an exclusive entity_test list.
    $this->checkCheckboxInRow('entity:entity_test/1:en');
    $this->checkCheckboxInRow('entity:entity_test/2:en');
    $page->selectFieldOption('Action', 'Search API test bulk form action: entity_test_string_id');
    $page->pressButton('Apply to selected items');
    $entity1 = EntityTest::load(1);
    $entity2 = EntityTest::load(2);
    $assert->pageTextContains("Rows {$entity1->label()}, {$entity2->label()} removed from selection as they are not compatible with Search API test bulk form action: entity_test_string_id action.");
    // The form didn't pass validation.
    $assert->pageTextContains("No items selected.");
    $this->assertActionsApplied([]);
  }

  /**
   * Creates and indexes test content.
   *
   * The index is composed of three datasources, including a non-entity one, in
   * order to test the bulk form on a view aggregating different entity types
   * and even non-entity rows:
   * - entity:entity_test: Datasource for 'entity_test' entity.
   * - entity:entity_test_string_id Datasource for 'entity_test_string_id'
   *   entity.
   * - search_api_test: Non-entity datasource.
   *
   * For each datasource we create and index two entries.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if the bundle does not exist or was needed but not specified.
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *   If the complex data structure is unset and no property can be created.
   */
  protected function createIndexedContent() {
    $foo_data_definition = FooDataDefinition::create()
      ->setMainPropertyName('foo')
      ->setLabel('Foo');

    // Create 2 items for each datasource.
    $search_api_test_values = [];
    for ($i = 1; $i <= 2; $i++) {
      // Entity: entity_type.
      $entity = EntityTest::create([
        'name' => $this->randomString(),
      ]);
      $entity->save();

      // Entity: entity_test_string_id.
      $entity = EntityTestStringId::create([
        'id' => "{$i}",
        'name' => $this->randomString(),
      ]);
      $entity->save();

      // Non-entity data.
      /** @var \Drupal\Core\TypedData\Plugin\DataType\Map $foo */
      $foo = \Drupal::getContainer()->get('typed_data_manager')
        ->createInstance('map', [
          'data_definition' => $foo_data_definition,
          'name' => NULL,
          'parent' => NULL,
        ]);
      $foo->set('foo', $this->randomMachineName());
      $search_api_test_values["{$i}:en"] = $foo;
    }

    $state = \Drupal::state();
    $state->set('search_api_test.datasource.return.loadMultiple', $search_api_test_values);
    $state->set('search_api_test.datasource.return.getItemLanguage', 'en');
    $state->set('search_api_test.datasource.return.getPropertyDefinitions', [
      'foo' => $foo_data_definition,
    ]);

    $this->index->trackItemsInserted('search_api_test', array_keys($search_api_test_values));
    $this->index->indexItems();

    $query_helper = \Drupal::getContainer()->get('search_api.query_helper');
    $query = $query_helper->createQuery($this->index);
    $results = $query->execute()->getResultItems();

    // Check that content has been indexed.
    $this->assertCount(6, $results);
    $this->assertArrayHasKey('entity:entity_test/1:en', $results);
    $this->assertArrayHasKey('entity:entity_test/2:en', $results);
    $this->assertArrayHasKey('entity:entity_test_string_id/1:und', $results);
    $this->assertArrayHasKey('entity:entity_test_string_id/2:und', $results);
    $this->assertArrayHasKey('search_api_test/1:en', $results);
    $this->assertArrayHasKey('search_api_test/2:en', $results);
  }

  /**
   * Asserts that the given actions were applied via the bulk form.
   *
   * @param array $expected_actions
   *   A list of expected actions. Each item is an indexed array with the
   *   following structure:
   *   - 0: The action plugin ID.
   *   - 1: The entity type ID.
   *   - 2: The entity ID.
   *
   * @see \Drupal\search_api_test_bulk_form\Plugin\Action\TestActionTrait::execute()
   */
  protected function assertActionsApplied(array $expected_actions) {
    $key_value = \Drupal::keyValue('search_api_test');
    $actual_actions = $key_value->get('search_api_test_bulk_form', []);
    $this->assertSame($expected_actions, $actual_actions);
    // Reset the state variable to be used by future assertions.
    $key_value->delete('search_api_test_bulk_form');
  }

  /**
   * Checks the checkbox in the Views row containing the given text.
   *
   * @param string $text
   *   Text contained in the row to be selected.
   */
  protected function checkCheckboxInRow(string $text) {
    $row = $this->getRowContainingText($text);
    $checkbox = $row->find('css', 'input[type="checkbox"]');
    $this->assertNotNull($checkbox);
    $checkbox->check();
  }

  /**
   * Asserts that a checkbox exists in the Views row containing the given text.
   *
   * @param string $text
   *   Text contained in the row.
   */
  protected function assertCheckboxExistsInRow(string $text) {
    $row = $this->getRowContainingText($text);
    $this->assertNotNull($row->find('css', 'input[type="checkbox"]'));
  }

  /**
   * Asserts that no checkbox exists in the Views row containing the given text.
   *
   * The existence of a row with the given text is also still asserted.
   *
   * @param string $text
   *   Text contained in the row.
   */
  protected function assertCheckboxNotExistsInRow(string $text) {
    $row = $this->getRowContainingText($text);
    $this->assertNull($row->find('css', 'input[type="checkbox"]'));
  }

  /**
   * Returns a table row containing the given text.
   *
   * @param string $text
   *   Text contained in the row.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   A table row containing the given text.
   */
  protected function getRowContainingText(string $text): NodeElement {
    $rows = $this->getSession()->getPage()->findAll('css', 'tr');
    $this->assertNotEmpty($rows, 'No rows found on the page.');

    $found = FALSE;
    /** @var \Behat\Mink\Element\NodeElement $row */
    foreach ($rows as $row) {
      if (str_contains($row->getText(), $text)) {
        $found = TRUE;
        break;
      }
    }
    $this->assertTrue($found, "No row with text \"$text\" found on the page.");
    return $row;
  }

}
