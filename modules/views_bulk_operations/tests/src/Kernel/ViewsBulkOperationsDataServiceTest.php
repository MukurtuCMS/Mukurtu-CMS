<?php

declare(strict_types=1);

namespace Drupal\Tests\views_bulk_operations\Kernel;

use Drupal\views\Views;

/**
 * @coversDefaultClass \Drupal\views_bulk_operations\Service\ViewsBulkOperationsViewData
 * @group views_bulk_operations
 */
final class ViewsBulkOperationsDataServiceTest extends ViewsBulkOperationsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->createTestNodes([
      'page' => [
        'languages' => ['pl', 'es', 'it', 'fr', 'de'],
        'count' => 20,
      ],
    ]);
  }

  /**
   * Tests the getEntityDefault() method.
   *
   * @covers ::getEntityDefault
   */
  public function testViewsBulkOperationsViewDataEntityGetter(): void {
    // Initialize and execute the test view with all items displayed.
    $view = Views::getView('views_bulk_operations_test');
    $view->setDisplay('page_1');
    $view->setItemsPerPage(0);
    $view->setCurrentPage(0);
    $view->execute();

    $test_data = $this->testNodesData;
    foreach ($view->result as $row) {
      $entity = $this->vboDataService->getEntityDefault($row, 'none', $view);

      $expected_label = $test_data[$entity->id()][$entity->language()->getId()];

      self::assertEquals($expected_label, $entity->label(), 'Title matches');
      if ($expected_label === $entity->label()) {
        unset($test_data[$entity->id()][$entity->language()->getId()]);
        if (\count($test_data[$entity->id()]) === 0) {
          unset($test_data[$entity->id()]);
        }
      }
    }

    self::assertEmpty($test_data, 'All created entities and their translations were returned.');
  }

}
