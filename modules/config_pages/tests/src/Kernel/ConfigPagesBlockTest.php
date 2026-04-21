<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Kernel tests for ConfigPagesBlock plugin.
 *
 * @group config_pages
 * @coversDefaultClass \Drupal\config_pages\Plugin\Block\ConfigPagesBlock
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesBlockTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'block',
    'config_pages',
  ];

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The config page type.
   *
   * @var \Drupal\config_pages\Entity\ConfigPagesType
   */
  protected ConfigPagesType $configPageType;

  /**
   * The config page entity.
   *
   * @var \Drupal\config_pages\Entity\ConfigPages
   */
  protected ConfigPages $configPage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('config_pages');
    $this->installEntitySchema('config_pages_type');
    $this->installConfig(['field', 'system', 'user']);
    $this->installSchema('user', ['users_data']);

    // Create user 1 (superuser) first.
    $superuser = User::create([
      'uid' => 1,
      'name' => 'superuser',
      'mail' => 'superuser@example.com',
      'status' => 1,
    ]);
    $superuser->save();

    $this->blockManager = $this->container->get('plugin.manager.block');

    // Create a config page type.
    $this->configPageType = ConfigPagesType::create([
      'id' => 'test_block_type',
      'label' => 'Test Block Type',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ]);
    $this->configPageType->save();

    // Create a config page entity.
    $this->configPage = ConfigPages::create([
      'type' => 'test_block_type',
      'label' => 'Test Config Page',
      'context' => serialize([]),
    ]);
    $this->configPage->save();
  }

  /**
   * Creates a block plugin instance.
   *
   * @param array $configuration
   *   Block configuration.
   *
   * @return \Drupal\config_pages\Plugin\Block\ConfigPagesBlock
   *   The block plugin instance.
   */
  protected function createBlockInstance(array $configuration = []) {
    $defaults = [
      'config_page_type' => 'test_block_type',
      'config_page_view_mode' => 'default',
    ];
    $configuration = array_merge($defaults, $configuration);

    return $this->blockManager->createInstance('config_pages_block', $configuration);
  }

  /**
   * Tests that the block plugin can be instantiated.
   */
  public function testBlockPluginExists(): void {
    $block = $this->createBlockInstance();

    $this->assertNotNull($block);
    $this->assertEquals('config_pages_block', $block->getPluginId());
  }

  /**
   * Tests the build method returns render array.
   *
   * @covers ::build
   */
  public function testBuildReturnsRenderArray(): void {
    $block = $this->createBlockInstance();

    $build = $block->build();

    $this->assertIsArray($build);
    $this->assertNotEmpty($build);
  }

  /**
   * Tests the build method with non-existing config page type.
   *
   * @covers ::build
   */
  public function testBuildWithNonExistingType(): void {
    $block = $this->createBlockInstance([
      'config_page_type' => 'non_existing_type',
    ]);

    $build = $block->build();

    $this->assertIsArray($build);
    $this->assertEmpty($build);
  }

  /**
   * Tests the build method with empty config page type.
   *
   * @covers ::build
   */
  public function testBuildWithEmptyType(): void {
    $block = $this->createBlockInstance([
      'config_page_type' => '',
    ]);

    $build = $block->build();

    $this->assertIsArray($build);
    $this->assertEmpty($build);
  }

  /**
   * Tests that build includes contextual links.
   *
   * @covers ::build
   */
  public function testBuildIncludesContextualLinks(): void {
    $block = $this->createBlockInstance();

    $build = $block->build();

    $this->assertArrayHasKey('#contextual_links', $build);
    $this->assertArrayHasKey('config_pages_type', $build['#contextual_links']);
    $this->assertEquals(
      'test_block_type',
      $build['#contextual_links']['config_pages_type']['route_parameters']['config_pages_type']
    );
  }

  /**
   * Tests getCacheTags returns config page tags.
   *
   * @covers ::getCacheTags
   */
  public function testGetCacheTagsWithValidConfigPage(): void {
    $block = $this->createBlockInstance();

    $cacheTags = $block->getCacheTags();

    $this->assertIsArray($cacheTags);
    $this->assertContains('config_pages:' . $this->configPage->id(), $cacheTags);
  }

  /**
   * Tests getCacheTags with non-existing config page returns list tag.
   *
   * When a config page type exists but no entity has been created yet,
   * the block should return a list cache tag so it gets invalidated
   * when the entity is created.
   *
   * @covers ::getCacheTags
   */
  public function testGetCacheTagsWithNonExistingConfigPage(): void {
    // Create a type without an entity.
    $typeWithoutEntity = ConfigPagesType::create([
      'id' => 'type_without_entity',
      'label' => 'Type Without Entity',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ]);
    $typeWithoutEntity->save();

    $block = $this->createBlockInstance([
      'config_page_type' => 'type_without_entity',
    ]);

    $cacheTags = $block->getCacheTags();

    $this->assertIsArray($cacheTags);
    $this->assertContains('config_pages_list:type_without_entity', $cacheTags);
  }

  /**
   * Tests getCacheTags with completely non-existing type returns list tag.
   *
   * @covers ::getCacheTags
   */
  public function testGetCacheTagsWithNonExistingType(): void {
    $block = $this->createBlockInstance([
      'config_page_type' => 'non_existing_type',
    ]);

    $cacheTags = $block->getCacheTags();

    $this->assertIsArray($cacheTags);
    $this->assertContains('config_pages_list:non_existing_type', $cacheTags);
  }

  /**
   * Tests getCacheTags with empty config page type.
   *
   * @covers ::getCacheTags
   */
  public function testGetCacheTagsWithEmptyType(): void {
    $block = $this->createBlockInstance([
      'config_page_type' => '',
    ]);

    $cacheTags = $block->getCacheTags();

    $this->assertIsArray($cacheTags);
    $this->assertEmpty($cacheTags);
  }

  /**
   * Tests getMachineNameSuggestion.
   *
   * @covers ::getMachineNameSuggestion
   */
  public function testGetMachineNameSuggestion(): void {
    $block = $this->createBlockInstance();

    $suggestion = $block->getMachineNameSuggestion();

    $this->assertEquals('config_pages', $suggestion);
  }

  /**
   * Tests defaultConfiguration.
   *
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfiguration(): void {
    $block = $this->createBlockInstance([]);

    $config = $block->defaultConfiguration();

    $this->assertIsArray($config);
  }

  /**
   * Tests access method with user who has view permission.
   *
   * @covers ::access
   */
  public function testAccessWithViewPermission(): void {
    $role = Role::create([
      'id' => 'test_viewer',
      'label' => 'Test Viewer',
    ]);
    $role->grantPermission('view config_pages entity');
    $role->save();

    $user = User::create([
      'name' => 'test_viewer_user',
      'mail' => 'viewer@example.com',
      'status' => 1,
    ]);
    $user->addRole('test_viewer');
    $user->save();

    $block = $this->createBlockInstance();
    $access = $block->access($user, TRUE);

    $this->assertTrue($access->isAllowed());
  }

  /**
   * Tests access method with user without permission.
   *
   * @covers ::access
   */
  public function testAccessWithoutPermission(): void {
    $user = User::create([
      'name' => 'test_no_perm_user',
      'mail' => 'noperm@example.com',
      'status' => 1,
    ]);
    $user->save();

    $block = $this->createBlockInstance();
    $access = $block->access($user, TRUE);

    $this->assertFalse($access->isAllowed());
  }

  /**
   * Tests access method with non-existing config page.
   *
   * @covers ::access
   */
  public function testAccessWithNonExistingConfigPage(): void {
    $user = User::create([
      'name' => 'test_user_no_access',
      'mail' => 'noaccess@example.com',
      'status' => 1,
    ]);
    $user->save();

    $block = $this->createBlockInstance([
      'config_page_type' => 'non_existing_type',
    ]);

    // Should fall back to parent access check.
    $access = $block->access($user, TRUE);

    // Parent returns allowed by default for blocks.
    $this->assertTrue($access->isAllowed());
  }

  /**
   * Tests getConfiguration returns set values.
   */
  public function testGetConfiguration(): void {
    $block = $this->createBlockInstance([
      'config_page_type' => 'test_block_type',
      'config_page_view_mode' => 'teaser',
    ]);

    $config = $block->getConfiguration();

    $this->assertEquals('test_block_type', $config['config_page_type']);
    $this->assertEquals('teaser', $config['config_page_view_mode']);
  }

  /**
   * Tests block with multiple config page types.
   */
  public function testBlockWithMultipleTypes(): void {
    // Create another type.
    $anotherType = ConfigPagesType::create([
      'id' => 'another_block_type',
      'label' => 'Another Block Type',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ]);
    $anotherType->save();

    $anotherPage = ConfigPages::create([
      'type' => 'another_block_type',
      'label' => 'Another Config Page',
      'context' => serialize([]),
    ]);
    $anotherPage->save();

    // Create blocks for each type.
    $block1 = $this->createBlockInstance(['config_page_type' => 'test_block_type']);
    $block2 = $this->createBlockInstance(['config_page_type' => 'another_block_type']);

    $tags1 = $block1->getCacheTags();
    $tags2 = $block2->getCacheTags();

    // Each block should have different cache tags.
    $this->assertContains('config_pages:' . $this->configPage->id(), $tags1);
    $this->assertContains('config_pages:' . $anotherPage->id(), $tags2);
    $this->assertNotEquals($tags1, $tags2);
  }

}
