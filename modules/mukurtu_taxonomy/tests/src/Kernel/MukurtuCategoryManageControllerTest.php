<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\mukurtu_taxonomy\Controller\MukurtuCategoryManageController;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the Manage Categories page renders without a fatal error.
 *
 * `OverviewTerms` (the "category" vocabulary's `'overview'` form handler) was
 * refactored in Drupal core to extend `EntityForm`, tied to the vocabulary
 * entity. `MukurtuCategoryManageController::content()` still called it with
 * the older `formBuilder()->getForm('...OverviewTerms', $vocabulary)`
 * convention, which never sets `$this->entity` on the form object. That left
 * `EntityForm::getBaseFormId()`/`getFormId()` calling `getEntityTypeId()` on a
 * null entity, fatally erroring the page before the "Add a new category" form
 * could even render - unconditionally, not just on an empty vocabulary.
 *
 * `mukurtu_taxonomy`'s full dependency chain (media, entity_browser, facets,
 * mukurtu_protocol, mukurtu_search, ...) isn't needed to exercise the
 * controller directly, so it's required here rather than enabled, mirroring
 * `StaticTaxonomyNameFieldRestoreTest`.
 *
 * @see \Drupal\mukurtu_taxonomy\Controller\MukurtuCategoryManageController::content()
 */
#[Group('mukurtu_taxonomy')]
class MukurtuCategoryManageControllerTest extends EntityKernelTestBase {

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

    Vocabulary::create(['vid' => 'category', 'name' => 'Category'])->save();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_taxonomy');
    require_once $module_path . '/src/Controller/MukurtuCategoryManageController.php';
  }

  /**
   * Builds and renders the page, asserting it does not fatally error.
   */
  private function assertPageRendersWithoutError(): array {
    $controller = MukurtuCategoryManageController::create($this->container);
    $build = $controller->content();

    $this->assertArrayHasKey(0, $build, 'Vocabulary overview form was built.');
    $this->assertArrayHasKey('add_category', $build, '"Add a new category" form was built.');

    $rendered = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertNotEmpty($rendered);

    return $build;
  }

  /**
   * The page renders when the "category" vocabulary has no terms yet.
   */
  public function testRendersWithEmptyVocabulary(): void {
    $this->assertPageRendersWithoutError();
  }

  /**
   * The page renders the same way once categories already exist.
   */
  public function testRendersWithExistingTerms(): void {
    Term::create(['vid' => 'category', 'name' => 'General'])->save();
    $this->assertPageRendersWithoutError();
  }

}
