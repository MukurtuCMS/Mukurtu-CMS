<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Hook\LocalTaskVisibilityHooks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests node Visitors/Devel/Revisions local task visibility.
 *
 * Community/protocol get the equivalent treatment from
 * mukurtu_protocol's HideCommunityProtocolLocalTasksOutsideEditView, which
 * is covered separately; this class must not touch their tabs.
 *
 * @see \Drupal\mukurtu_core\Hook\LocalTaskVisibilityHooks
 * @see \Drupal\mukurtu_protocol\Hook\HideCommunityProtocolLocalTasksOutsideEditView
 */
#[Group('mukurtu_core')]
class LocalTaskVisibilityHooksTest extends KernelTestBase {

  /**
   * A data array carrying every tab this hook cares about, still present.
   *
   * @return array
   */
  protected function tabsWithEverythingPresent(): array {
    return [
      'tabs' => [
        0 => [
          'visitors.node_tab' => ['#link' => ['title' => 'Visitors']],
          'devel.entities:node.devel_tab' => ['#link' => ['title' => 'Devel']],
          'entity.node.version_history' => ['#link' => ['title' => 'Revisions']],
          'entity.version_history:node.version_history' => ['#link' => ['title' => 'Revisions']],
        ],
      ],
    ];
  }

  /**
   * The Visitors tab is hidden on both the node view and edit pages.
   */
  #[DataProvider('visitorsHiddenRouteProvider')]
  public function testVisitorsTabHidden(string $routeName): void {
    $data = $this->tabsWithEverythingPresent();
    (new LocalTaskVisibilityHooks())->menuLocalTasksAlter($data, $routeName);
    $this->assertArrayNotHasKey('visitors.node_tab', $data['tabs'][0]);
  }

  public static function visitorsHiddenRouteProvider(): array {
    return [
      'node view page' => ['entity.node.canonical'],
      'node edit page' => ['entity.node.edit_form'],
    ];
  }

  /**
   * Devel and Revisions are hidden on the node view page.
   */
  public function testDevelAndRevisionsHiddenOnViewPage(): void {
    $data = $this->tabsWithEverythingPresent();
    (new LocalTaskVisibilityHooks())->menuLocalTasksAlter($data, 'entity.node.canonical');

    $this->assertArrayNotHasKey('devel.entities:node.devel_tab', $data['tabs'][0]);
    $this->assertArrayNotHasKey('entity.node.version_history', $data['tabs'][0]);
    $this->assertArrayNotHasKey('entity.version_history:node.version_history', $data['tabs'][0]);
  }

  /**
   * Devel and Revisions are left alone on the node edit page.
   *
   * Whether they actually show there is still gated by Drupal's own
   * permissions (e.g. 'access devel information') when the tab is used;
   * this hook is only responsible for the view/edit split.
   */
  public function testDevelAndRevisionsPreservedOnEditPage(): void {
    $data = $this->tabsWithEverythingPresent();
    (new LocalTaskVisibilityHooks())->menuLocalTasksAlter($data, 'entity.node.edit_form');

    $this->assertArrayHasKey('devel.entities:node.devel_tab', $data['tabs'][0]);
    $this->assertArrayHasKey('entity.node.version_history', $data['tabs'][0]);
    $this->assertArrayHasKey('entity.version_history:node.version_history', $data['tabs'][0]);
  }

  /**
   * Community/protocol tabs are untouched, even on their own view pages.
   *
   * Guards against scope creep: that job now belongs to
   * HideCommunityProtocolLocalTasksOutsideEditView, which (unlike this
   * class) preserves the uid-1-only restriction on their Revisions tab.
   */
  #[DataProvider('communityProtocolRouteProvider')]
  public function testCommunityAndProtocolTabsUntouched(string $routeName, string $entityTypeId): void {
    $data = [
      'tabs' => [
        0 => [
          "devel.entities:$entityTypeId.devel_tab" => ['#link' => ['title' => 'Devel']],
          "entity.$entityTypeId.version_history" => ['#link' => ['title' => 'Revisions']],
          "entity.version_history:$entityTypeId.version_history" => ['#link' => ['title' => 'Revisions']],
        ],
      ],
    ];
    (new LocalTaskVisibilityHooks())->menuLocalTasksAlter($data, $routeName);

    $this->assertArrayHasKey("devel.entities:$entityTypeId.devel_tab", $data['tabs'][0]);
    $this->assertArrayHasKey("entity.$entityTypeId.version_history", $data['tabs'][0]);
    $this->assertArrayHasKey("entity.version_history:$entityTypeId.version_history", $data['tabs'][0]);
  }

  public static function communityProtocolRouteProvider(): array {
    return [
      'community view page' => ['entity.community.canonical', 'community'],
      'protocol view page' => ['entity.protocol.canonical', 'protocol'],
    ];
  }

}
