<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\mukurtu_taxonomy\Controller\MukurtuManageTaxonomyController;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the generic Manage Taxonomy page renders without a fatal error.
 *
 * Same bug as `MukurtuCategoryManageControllerTest`, in the sibling
 * controller used for non-category vocabularies: `OverviewTerms` is now the
 * vocabulary entity type's `'overview'` form handler (an `EntityForm`), so
 * calling it via the older `formBuilder()->getForm('...OverviewTerms',
 * $vocabulary)` convention left its entity null and fatally errored
 * `getBaseFormId()`.
 *
 * @see \Drupal\mukurtu_taxonomy\Controller\MukurtuManageTaxonomyController::content()
 */
#[Group('mukurtu_taxonomy')]
class MukurtuManageTaxonomyControllerTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['taxonomy', 'field', 'text', 'filter'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['taxonomy']);

    Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords'])->save();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_taxonomy');
    require_once $module_path . '/src/Controller/MukurtuManageTaxonomyController.php';
  }

  /**
   * Builds and renders the page, asserting it does not fatally error.
   */
  private function assertPageRendersWithoutError(): array {
    $controller = MukurtuManageTaxonomyController::create($this->container);
    $build = $controller->content('keywords');

    $this->assertArrayHasKey(0, $build, 'Vocabulary overview form was built.');
    $this->assertArrayHasKey('add_term', $build, '"Add a new term" form was built.');

    $rendered = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertNotEmpty($rendered);

    return $build;
  }

  /**
   * The page renders when the vocabulary has no terms yet.
   */
  public function testRendersWithEmptyVocabulary(): void {
    $this->assertPageRendersWithoutError();
  }

  /**
   * The page renders the same way once terms already exist.
   */
  public function testRendersWithExistingTerms(): void {
    Term::create(['vid' => 'keywords', 'name' => 'Existing term'])->save();
    $this->assertPageRendersWithoutError();
  }

}
