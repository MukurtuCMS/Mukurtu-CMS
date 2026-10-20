<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\mukurtu_core\Hook\LayoutBuilderHooks;
use Drupal\node\NodeInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests which blocks basic pages offer in Layout Builder.
 *
 * @see \Drupal\mukurtu_core\Hook\LayoutBuilderHooks::restrictBasicPageBlocks()
 */
#[Group('mukurtu_core')]
class LayoutBuilderHooksTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'geofield',
    'leaflet',
    'node',
    'block',
    'layout_discovery',
    'layout_builder',
    'mukurtu_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    Role::create(['id' => 'administrator', 'label' => 'Administrator'])->save();
  }

  /**
   * Runs block definitions through the hook.
   *
   * @param array $definitions
   *   Block definitions keyed by plugin ID.
   * @param string $bundle
   *   The node bundle whose layout is being edited.
   *
   * @return string[]
   *   The plugin IDs that survived filtering.
   */
  protected function filterDefinitions(array $definitions, string $bundle = 'page'): array {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn($bundle);
    $section_storage = $this->createMock(OverridesSectionStorageInterface::class);
    $section_storage->method('getContextValue')->with('entity')->willReturn($node);

    (new LayoutBuilderHooks())->restrictBasicPageBlocks($definitions, ['section_storage' => $section_storage]);
    return array_keys($definitions);
  }

  /**
   * Logs in a user with or without the administrator role.
   */
  protected function logIn(bool $administrator): void {
    // Create uid 1 first so the test user does not bypass access checks.
    $this->createUser();
    $account = $this->createUser();
    if ($administrator) {
      $account->addRole('administrator');
      $account->save();
    }
    $this->setCurrentUser($account);
  }

  /**
   * Every role gets the allowed blocks and nothing else.
   *
   * The extras cover what used to leak through: reusable content blocks, the
   * "Custom" plugin blocks, the footer and single-media inline types, and the
   * wider set administrators used to see.
   */
  #[DataProvider('roleProvider')]
  public function testOnlyAllowedBlocksAreOffered(bool $administrator): void {
    $this->logIn($administrator);

    $definitions = array_fill_keys(LayoutBuilderHooks::ALLOWED_BLOCKS, []);
    $definitions += array_fill_keys([
      'block_content:5c3a1f2e-0000-4000-8000-000000000001',
      'consent_popup',
      'mukurtu_footer',
      'inline_block:mukurtu_footer',
      'inline_block:media_block',
      'system_menu_block:main',
      'views_block:content_recent-block_1',
      'mukurtu_community_protocol_list',
    ], []);

    $this->assertSame(LayoutBuilderHooks::ALLOWED_BLOCKS, $this->filterDefinitions($definitions));
  }

  /**
   * Data provider: administrator or not.
   */
  public static function roleProvider(): array {
    return [
      'administrator' => [TRUE],
      'non-administrator' => [FALSE],
    ];
  }

  /**
   * The basic page set must match what landing pages offer.
   *
   * The two lists live in different modules, so they can drift. Compare the
   * hook's list with the allowlists in the shipped landing page display.
   */
  public function testAllowedBlocksMatchLandingPages(): void {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_landing_page');
    $display = (new FileStorage($module_path . '/config/install'))->read('core.entity_view_display.node.landing_page.default');
    $allowlists = $display['third_party_settings']['layout_builder_restrictions']['entity_view_mode_restriction']['allowlisted_blocks'];

    $landing_page_blocks = array_merge(...array_values($allowlists));
    $basic_page_blocks = LayoutBuilderHooks::ALLOWED_BLOCKS;
    sort($landing_page_blocks);
    sort($basic_page_blocks);

    $this->assertSame($landing_page_blocks, $basic_page_blocks);
  }

  /**
   * Other node bundles are left alone.
   */
  public function testOtherBundlesAreNotAltered(): void {
    $this->logIn(FALSE);

    $this->assertSame(['consent_popup'], $this->filterDefinitions(['consent_popup' => []], 'landing_page'));
  }

}
