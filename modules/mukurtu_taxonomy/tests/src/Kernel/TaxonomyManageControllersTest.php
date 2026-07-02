<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_taxonomy\Controller\MukurtuCategoryManageController;
use Drupal\mukurtu_taxonomy\Controller\MukurtuManageTaxonomyController;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests that the vocabulary overview form renders without a fatal error.
 *
 * @group mukurtu_taxonomy
 */
class TaxonomyManageControllersTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['filter', 'taxonomy']);

    Vocabulary::create(['vid' => 'category', 'name' => 'Category'])->save();
  }

  /**
   * MukurtuCategoryManageController::content() must not fatally error.
   *
   * Regression test: the controller previously called
   * formBuilder()->getForm('Drupal\taxonomy\Form\OverviewTerms', $vocabulary),
   * the old FormBase calling convention, against a class that is now an
   * EntityForm requiring setEntity() to be called first. That mismatch threw
   * "Call to a member function getEntityTypeId() on null".
   */
  public function testCategoryManageControllerRendersOverviewForm(): void {
    $controller = MukurtuCategoryManageController::create($this->container);
    $build = $controller->content();

    $this->assertNotEmpty($build);
    $this->assertSame('taxonomy_overview_terms', $build[0]['#form_id'] ?? NULL);
  }

  /**
   * MukurtuManageTaxonomyController::content() must not fatally error.
   */
  public function testManageTaxonomyControllerRendersOverviewForm(): void {
    $controller = MukurtuManageTaxonomyController::create($this->container);
    $build = $controller->content('category');

    $this->assertNotEmpty($build);
    $this->assertSame('taxonomy_overview_terms', $build[0]['#form_id'] ?? NULL);
  }

}
