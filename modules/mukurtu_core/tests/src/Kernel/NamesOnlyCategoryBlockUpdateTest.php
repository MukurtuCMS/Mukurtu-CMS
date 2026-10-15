<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40217(), which adds the names-only category block.
 *
 * @see mukurtu_core_update_40217()
 */
#[Group('mukurtu_core')]
class NamesOnlyCategoryBlockUpdateTest extends KernelTestBase {

  protected const DISPLAY_ID = 'browse_by_category_names_block';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'views', 'taxonomy', 'field', 'text'];

  /**
   * The shipped mukurtu_categories view config.
   */
  protected array $shippedView;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');
    Vocabulary::create(['vid' => 'category', 'name' => 'Category'])->save();

    // Required directly rather than via loadInclude(); see
    // CategoryAdminLinksCascadeUpdateTest::setUp() for why.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_core.install';

    $this->shippedView = Yaml::decode(file_get_contents($module_path . '/config/install/views.view.mukurtu_categories.yml'));
  }

  /**
   * Saves the shipped view as it was before this update.
   */
  protected function createPreUpdateView(): void {
    $values = $this->shippedView;
    unset($values['dependencies'], $values['_core'], $values['display'][self::DISPLAY_ID]);
    View::create($values)->save();
  }

  /**
   * An existing site gets the view mode, the display and the block.
   */
  public function testAddsViewModeDisplayAndBlock(): void {
    $this->createPreUpdateView();

    $this->assertNotNull(mukurtu_core_update_40217());

    $this->assertSame('Names only', EntityViewMode::load('taxonomy_term.names_only')?->label());
    $display = EntityViewDisplay::load('taxonomy_term.category.names_only');
    $this->assertNotNull($display);
    $this->assertSame([], $display->getComponents(), 'The names-only display must not show the thumbnail or any other field.');

    $view = View::load('mukurtu_categories');
    $this->assertSame(
      $this->shippedView['display'][self::DISPLAY_ID],
      $view->get('display')[self::DISPLAY_ID],
      'An updated site must get exactly the display a fresh install ships.'
    );
    $this->assertContains('core.entity_view_mode.taxonomy_term.names_only', $view->getDependencies()['config']);
  }

  /**
   * The new block renders terms in the names-only view mode.
   *
   * Views overrides row and style together, so a display that overrides only
   * its row silently falls back to the master display's teaser view mode.
   */
  public function testBlockResolvesToNamesOnlyViewMode(): void {
    $this->createPreUpdateView();
    mukurtu_core_update_40217();

    $view = Views::getView('mukurtu_categories');
    $view->setDisplay(self::DISPLAY_ID);
    $this->assertSame('names_only', $view->display_handler->getOption('row')['options']['view_mode']);
    $this->assertSame('Browse by Category', $view->display_handler->getOption('title'));

    // The existing block still shows thumbnails.
    $view->setDisplay('browse_by_category_block');
    $this->assertSame('teaser', $view->display_handler->getOption('row')['options']['view_mode']);
  }

  /**
   * Running twice is harmless and keeps a site's own changes.
   */
  public function testIsIdempotent(): void {
    $this->createPreUpdateView();
    mukurtu_core_update_40217();

    $view = View::load('mukurtu_categories');
    $displays = $view->get('display');
    $displays[self::DISPLAY_ID]['display_options']['title'] = 'Topics';
    $view->set('display', $displays)->save();

    $this->assertNull(mukurtu_core_update_40217());
    $this->assertSame('Topics', View::load('mukurtu_categories')->get('display')[self::DISPLAY_ID]['display_options']['title']);
  }

  /**
   * A site that deleted the view still gets the view mode, and no error.
   */
  public function testSkipsMissingView(): void {
    $this->assertNull(mukurtu_core_update_40217());
    $this->assertNotNull(EntityViewDisplay::load('taxonomy_term.category.names_only'));
  }

}
