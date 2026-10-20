<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
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
 * @see \Drupal\mukurtu_core\Hook\LayoutBuilderHooks::restrictBlocksForRole()
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
   * Runs block definitions through the hook for a basic page.
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

    (new LayoutBuilderHooks())->restrictBlocksForRole($definitions, ['section_storage' => $section_storage]);
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
   * Reusable and site-wide blocks are hidden from every role.
   *
   * Content blocks come from "Add a block", so reusable ones are not offered.
   * The "Custom" plugin blocks belong in theme regions. The footer and
   * single-media inline types are not offered on landing pages, so basic
   * pages match.
   */
  #[DataProvider('roleProvider')]
  public function testSiteWideBlocksAreHidden(bool $administrator): void {
    $this->logIn($administrator);

    $definitions = [
      'consent_popup' => ['category' => new TranslatableMarkup('Custom')],
      'mukurtu_footer' => ['category' => new TranslatableMarkup('Custom')],
      'block_content:5c3a1f2e-0000-4000-8000-000000000001' => ['category' => new TranslatableMarkup('Content block')],
      'inline_block:mukurtu_footer' => ['category' => new TranslatableMarkup('Inline blocks')],
      'inline_block:media_block' => ['category' => new TranslatableMarkup('Inline blocks')],
      'inline_block:basic' => ['category' => new TranslatableMarkup('Inline blocks')],
    ];

    $allowed = $this->filterDefinitions($definitions);

    $this->assertSame(['inline_block:basic'], array_values(array_intersect(array_keys($definitions), $allowed)));
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
   * Administrators keep everything else.
   */
  public function testAdministratorKeepsOtherBlocks(): void {
    $this->logIn(TRUE);

    $definitions = [
      'views_block:mukurtu_browse_by_map-map_block' => ['category' => new TranslatableMarkup('Lists (Views)')],
      'inline_block:image_with_description' => ['category' => new TranslatableMarkup('Inline blocks')],
    ];

    $this->assertSame(array_keys($definitions), $this->filterDefinitions($definitions));
  }

  /**
   * Category matching must not depend on the interface language.
   *
   * Casting a category to a string translates it. The hook compares English
   * names, so it has to read the untranslated string.
   */
  public function testCategoryMatchingIgnoresTranslation(): void {
    $this->logIn(FALSE);
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturn('Translated');

    $definitions = [
      'consent_popup' => ['category' => new TranslatableMarkup('Custom', [], [], $translation)],
      'inline_block:basic' => ['category' => new TranslatableMarkup('Inline blocks', [], [], $translation)],
    ];

    $this->assertSame(['inline_block:basic'], $this->filterDefinitions($definitions));
  }

  /**
   * Other node bundles are left alone.
   */
  public function testOtherBundlesAreNotAltered(): void {
    $this->logIn(FALSE);
    $definitions = [
      'consent_popup' => ['category' => new TranslatableMarkup('Custom')],
    ];

    $this->assertSame(['consent_popup'], $this->filterDefinitions($definitions, 'landing_page'));
  }

}
