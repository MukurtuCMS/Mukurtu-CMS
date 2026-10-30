<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\MediaType;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_update_40039().
 *
 * Existing sites get the media bulk_edit form mode, a bulk_edit form for
 * each media type they have, and the bulk edit media page's view.
 */
#[Group('mukurtu')]
class BulkEditMediaPageUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * The view names filter plugins from many Mukurtu modules. The hook only
   * imports the shipped config, so this checks the import, not the plugins.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'flag',
    'geofield',
    'image',
    'key',
    'leaflet',
    'media',
    'media_entity_soundcloud',
    'media_library',
    'mukurtu_core',
    'mukurtu_media',
    'mukurtu_protocol',
    'node',
    'og',
    'options',
    'path',
    'system',
    'tagify',
    'taxonomy',
    'text',
    'user',
    'views',
    'views_bulk_operations',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('media');
    require_once $this->root . '/' . \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/mukurtu.install';
  }

  /**
   * Everything is added once, and a second run keeps site changes.
   */
  public function testInstallsOnce(): void {
    // Only a type the site has gets a form.
    // Mukurtu defines the source field in code, so the type is created bare.
    MediaType::create([
      'id' => 'image',
      'label' => 'Image',
      'source' => 'image',
      'source_configuration' => ['source_field' => 'field_media_image'],
    ])->save();
    $this->assertNull(View::load('mukurtu_bulk_edit_media'));

    $this->assertSame('Added the bulk edit media page.', mukurtu_update_40039());

    $this->assertNotNull(EntityFormMode::load('media.bulk_edit'));
    $this->assertNotNull(EntityFormDisplay::load('media.image.bulk_edit'));
    $this->assertNull(EntityFormDisplay::load('media.audio.bulk_edit'));
    $view = View::load('mukurtu_bulk_edit_media');
    $this->assertNotNull($view);
    $this->assertSame('admin/content/bulk-media/%', $view->getDisplay('page')['display_options']['path']);
    $this->assertSame('use mukurtu bulk edit', $view->getDisplay('default')['display_options']['access']['options']['perm']);

    $view->set('label', 'Site label')->save();
    $this->assertSame('The bulk edit media page is already installed; nothing to change.', mukurtu_update_40039());
    $this->assertSame('Site label', View::load('mukurtu_bulk_edit_media')->label());
  }

}
