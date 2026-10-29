<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_digital_heritage\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Serialization\Yaml;

/**
 * Tests removing the title from the digital heritage sidebar (#598).
 *
 * @group mukurtu_digital_heritage
 */
#[\PHPUnit\Framework\Attributes\Group('mukurtu_digital_heritage')]
class SidebarTitleUpdateTest extends DigitalHeritageTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'comment',
    'content_moderation',
    'entity_reference_revisions',
    'field',
    'field_group',
    'file',
    'filter',
    'geofield',
    'image',
    'leaflet',
    'link',
    'media',
    'node',
    'node_access_test',
    'og',
    'options',
    'original_date',
    'paragraphs',
    'path_alias',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
    'mukurtu_core',
    'mukurtu_digital_heritage',
    'mukurtu_drafts',
    'mukurtu_local_contexts',
    'mukurtu_protocol',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::moduleHandler()->loadInclude('mukurtu_digital_heritage', 'install');
    if (!EntityViewMode::load('node.full')) {
      EntityViewMode::create(['id' => 'node.full', 'targetEntityType' => 'node', 'label' => 'Full content'])->save();
    }
  }

  /**
   * Saves a full display with the sidebar group holding the given children.
   *
   * @param string[] $sidebar_children
   *   Children of group_sidebar_mukurtu_fields.
   * @param string[] $other_children
   *   Children of a second group, group_other.
   */
  protected function createFullDisplay(array $sidebar_children, array $other_children = []): void {
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'digital_heritage',
      'mode' => 'full',
      'status' => TRUE,
    ]);
    $display->setComponent('field_title', ['weight' => 23, 'region' => 'content']);
    foreach (['group_sidebar_mukurtu_fields' => $sidebar_children, 'group_other' => $other_children] as $name => $children) {
      $display->setThirdPartySetting('field_group', $name, [
        'children' => $children,
        'label' => $name,
        'parent_name' => '',
        'region' => 'content',
        'weight' => 0,
        'format_type' => 'html_element',
        'format_settings' => [],
      ]);
    }
    $display->save();
  }

  /**
   * Loads the full display fresh from storage.
   */
  protected function loadFullDisplay(): ?EntityViewDisplay {
    \Drupal::entityTypeManager()->getStorage('entity_view_display')->resetCache();
    return EntityViewDisplay::load('node.digital_heritage.full');
  }

  /**
   * The shipped display hides the title and leaves it out of the sidebar.
   */
  public function testShippedDisplayHidesTitle(): void {
    $path = \Drupal::service('extension.list.module')->getPath('mukurtu_digital_heritage') . '/config/install/core.entity_view_display.node.digital_heritage.full.yml';
    $config = Yaml::decode(file_get_contents($path));

    $this->assertArrayNotHasKey('field_title', $config['content']);
    $this->assertTrue($config['hidden']['field_title']);
    $this->assertNotContains('field_title', $config['third_party_settings']['field_group']['group_sidebar_mukurtu_fields']['children']);
  }

  /**
   * The update removes the title from the shipped sidebar placement.
   */
  public function testUpdateRemovesTitleFromSidebar(): void {
    $this->createFullDisplay(['field_title', 'field_category', 'field_keywords']);

    mukurtu_digital_heritage_update_40010();

    $display = $this->loadFullDisplay();
    $this->assertNull($display->getComponent('field_title'));
    $group = $display->getThirdPartySetting('field_group', 'group_sidebar_mukurtu_fields');
    $this->assertSame(['field_category', 'field_keywords'], $group['children']);
  }

  /**
   * A site that moved the title to another group keeps it there.
   */
  public function testUpdateLeavesMovedTitleAlone(): void {
    $this->createFullDisplay(['field_category'], ['field_title']);

    mukurtu_digital_heritage_update_40010();

    $display = $this->loadFullDisplay();
    $this->assertNotNull($display->getComponent('field_title'));
    $this->assertSame(['field_title'], $display->getThirdPartySetting('field_group', 'group_other')['children']);
  }

  /**
   * The update does nothing on a site without the full display.
   */
  public function testUpdateWithoutDisplay(): void {
    mukurtu_digital_heritage_update_40010();
    $this->assertNull($this->loadFullDisplay());
  }

}
