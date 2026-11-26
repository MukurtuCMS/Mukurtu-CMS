<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\NodeType;
use Drupal\og\Entity\OgRole;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40233(), which adds the 3D Model media type.
 *
 * Also mukurtu_core_update_40234(), which lets text formats embed it (#2810).
 *
 * @see mukurtu_core_update_40233()
 * @see mukurtu_core_update_40234()
 */
#[Group('mukurtu_core')]
class ThreeDModelUpdateTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'content_moderation',
    'workflows',
    'field',
    'file',
    'filter',
    'geofield',
    'image',
    'key',
    'layout_builder',
    'layout_discovery',
    'leaflet',
    'node',
    'node_access_test',
    'media',
    'media_library',
    'og',
    'options',
    'path',
    'path_alias',
    'system',
    'tagify',
    'text',
    'taxonomy',
    'user',
    'views',
    'mukurtu_core',
    'mukurtu_protocol',
    'mukurtu_media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('layout_builder', ['inline_block_usage']);

    // Roll the site back to before the update: the type's two new field
    // storages aren't installed yet, and there is no 3D Model type.
    $manager = \Drupal::entityDefinitionUpdateManager();
    foreach (['field_media_3d_model', 'field_media_3d_model_usdz'] as $field_name) {
      $manager->uninstallFieldStorageDefinition($manager->getFieldStorageDefinition($field_name, 'media'));
    }

    // The media types the update looks for in existing fields and widgets.
    foreach (['audio', 'document'] as $type) {
      MediaType::create([
        'id' => $type,
        'label' => $type,
        'source' => 'file',
        'source_configuration' => ['source_field' => 'field_media_' . ($type === 'audio' ? 'audio_file' : 'document')],
      ])->save();
    }

    // One media view mode the site has. The others the type ships displays
    // for are missing, as a site that deleted them would be.
    EntityViewMode::create(['id' => 'media.media_assets', 'targetEntityType' => 'media', 'label' => 'Media assets'])->save();
    EntityFormMode::create(['id' => 'media.bulk_edit', 'targetEntityType' => 'media', 'label' => 'Bulk edit'])->save();

    // A page with a media field and widget that take every type, and an
    // article whose field and widget the site narrowed to audio.
    FieldStorageConfig::create([
      'field_name' => 'field_page_media_assets',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'media'],
      'cardinality' => -1,
    ])->save();
    foreach (['page' => ['audio', 'document'], 'article' => ['audio']] as $bundle => $types) {
      NodeType::create(['type' => $bundle, 'name' => $bundle])->save();
      FieldConfig::create([
        'field_name' => 'field_page_media_assets',
        'entity_type' => 'node',
        'bundle' => $bundle,
        'settings' => [
          'handler' => 'default:media',
          'handler_settings' => ['target_bundles' => array_combine($types, $types)],
        ],
      ])->save();
      EntityFormDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ])->setComponent('field_page_media_assets', [
        'type' => 'media_library_widget',
        'settings' => ['media_types' => $types],
      ])->save();
    }

    // A protocol role that can work with documents, and one that can't.
    $contributor = OgRole::create([
      'name' => 'contributor',
      'label' => 'Contributor',
      'permissions' => [
        'create document media',
        'update own document media',
        'delete own document media',
        'create audio media',
      ],
    ]);
    $contributor->setGroupType('protocol')->setGroupBundle('protocol')->save();

    require_once \Drupal::root() . '/' . \Drupal::service('extension.list.module')->getPath('mukurtu_core') . '/mukurtu_core.install';
  }

  /**
   * The update adds the type, its storage, displays, tabs and permissions.
   */
  public function testUpdate(): void {
    $this->assertSame('Added the 3D Model media type.', mukurtu_core_update_40233());

    $manager = \Drupal::entityDefinitionUpdateManager();
    $this->assertNotNull($manager->getFieldStorageDefinition('field_media_3d_model', 'media'));
    $this->assertNotNull($manager->getFieldStorageDefinition('field_media_3d_model_usdz', 'media'));
    // Other entity types show up in a kernel test only because it never
    // installs them; media is the one this update touches.
    $this->assertArrayNotHasKey('media', $manager->getChangeList(), 'No media schema changes are left over.');

    $this->assertNotNull(MediaType::load('3d_model'));
    $this->assertNotNull(EntityFormDisplay::load('media.3d_model.default'));
    $this->assertNotNull(EntityFormDisplay::load('media.3d_model.bulk_edit'));
    $this->assertNotNull(EntityViewDisplay::load('media.3d_model.default'));
    $this->assertNotNull(EntityViewDisplay::load('media.3d_model.media_assets'));
    $this->assertNull(EntityViewDisplay::load('media.3d_model.browse'), 'No display for a view mode the site lacks.');

    // The page's field and widget take every type, so they take this one,
    // and its tab is the last one.
    $bundles = FieldConfig::load('node.page.field_page_media_assets')->getSetting('handler_settings')['target_bundles'];
    $this->assertArrayHasKey('3d_model', $bundles);
    $types = EntityFormDisplay::load('node.page.default')->getComponent('field_page_media_assets')['settings']['media_types'];
    $this->assertSame(['audio', 'document', '3d_model'], $types);

    // The article's were narrowed, so they stay as they were.
    $bundles = FieldConfig::load('node.article.field_page_media_assets')->getSetting('handler_settings')['target_bundles'];
    $this->assertArrayNotHasKey('3d_model', $bundles);
    $types = EntityFormDisplay::load('node.article.default')->getComponent('field_page_media_assets')['settings']['media_types'];
    $this->assertSame(['audio'], $types);

    // A role gets the 3D model versions of the document permissions it has.
    $contributor = OgRole::load('protocol-protocol-contributor');
    foreach (['create 3d_model media', 'update own 3d_model media', 'delete own 3d_model media'] as $permission) {
      $this->assertTrue($contributor->hasPermission($permission), $permission);
    }
    $this->assertFalse($contributor->hasPermission('update any 3d_model media'));
    $steward = OgRole::load('protocol-protocol-protocol_steward');
    $this->assertEmpty(array_filter($steward->getPermissions(), fn($p) => str_contains($p, '3d_model')));

    // Running it again changes nothing.
    $this->assertNull(mukurtu_core_update_40233());
  }

  /**
   * A display for a mode the site deleted is skipped, not an error.
   */
  public function testMissingFormModeIsSkipped(): void {
    EntityFormMode::load('media.bulk_edit')->delete();

    $this->assertSame('Added the 3D Model media type.', mukurtu_core_update_40233());

    $this->assertNotNull(EntityFormDisplay::load('media.3d_model.default'));
    $this->assertNull(EntityFormDisplay::load('media.3d_model.bulk_edit'));
  }

  /**
   * A form display component the site removed stays removed.
   */
  public function testRemovedWidgetIsLeftAlone(): void {
    EntityFormDisplay::load('node.page.default')->removeComponent('field_page_media_assets')->save();

    mukurtu_core_update_40233();

    $this->assertNull(EntityFormDisplay::load('node.page.default')->getComponent('field_page_media_assets'));
  }

  /**
   * Formats that embed every type, documents included, get 3D models.
   *
   * A format a site narrowed, or left empty (every type), is left alone.
   */
  public function testEmbedFormats(): void {
    // Before 40233 there is no media type to allow.
    $this->assertNull(mukurtu_core_update_40234());
    mukurtu_core_update_40233();

    $formats = [
      'basic_html' => ['audio' => 'audio', 'document' => 'document', 'image' => 'image'],
      'full_html' => ['image' => 'image'],
      'restricted_html' => [],
    ];
    foreach ($formats as $id => $types) {
      FilterFormat::create([
        'format' => $id,
        'name' => $id,
        'filters' => [
          'media_embed' => [
            'status' => TRUE,
            'settings' => [
              'default_view_mode' => 'default',
              'allowed_view_modes' => [],
              'allowed_media_types' => $types,
            ],
          ],
        ],
      ])->save();
    }

    $this->assertSame('Allowed 3D models in editor media embeds.', mukurtu_core_update_40234());

    $allowed = fn(string $id) => FilterFormat::load($id)->filters('media_embed')->getConfiguration()['settings']['allowed_media_types'];
    $this->assertSame(['3d_model' => '3d_model', 'audio' => 'audio', 'document' => 'document', 'image' => 'image'], $allowed('basic_html'));
    $this->assertSame(['image' => 'image'], $allowed('full_html'));
    $this->assertSame([], $allowed('restricted_html'));

    $this->assertNull(mukurtu_core_update_40234(), 'A second run changed something.');
  }

}
