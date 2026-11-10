<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\CommunityInterface;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\mukurtu_protocol\ProtocolListBuilder;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests which communities show on /admin/communities-protocols.
 *
 * Every community the user can view gets a row, whether or not they can see
 * any of its protocols.
 */
#[Group('mukurtu_protocol')]
class ProtocolListBuilderVisibilityTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    Role::load('authenticated')
      ->grantPermission('view published community entities')
      ->save();
  }

  /**
   * Creates and saves a community.
   */
  protected function createCommunity(string $name, string $sharing): CommunityInterface {
    $community = Community::create([
      'name' => $name,
      'status' => TRUE,
      'field_access_mode' => $sharing,
    ]);
    $community->save();
    return $community;
  }

  /**
   * Creates and saves a protocol in a community.
   */
  protected function createProtocol(string $name, CommunityInterface $community, string $sharing): Protocol {
    $protocol = Protocol::create([
      'name' => $name,
      'status' => TRUE,
      'field_communities' => [['target_id' => $community->id()]],
      'field_access_mode' => $sharing,
    ]);
    $protocol->save();
    return $protocol;
  }

  /**
   * Saves the community organization: child ID => parent ID (0 = top level).
   */
  protected function setOrganization(array $parents): void {
    $organization = [];
    $weight = 0;
    foreach ($parents as $id => $parent) {
      $organization[$id] = ['parent' => $parent, 'weight' => $weight++];
    }
    $this->config('mukurtu_protocol.community_organization')
      ->set('organization', $organization)
      ->save();
    // The parent/child fields are computed from this config, so drop any
    // community entities already loaded with the old values.
    \Drupal::entityTypeManager()->getStorage('community')->resetCache();
  }

  /**
   * Builds the list, keyed by community label, with each row's depth.
   *
   * @return array<string, int>
   *   The depth of each community row, keyed by the rendered label.
   */
  protected function communityRows(): array {
    $definition = \Drupal::entityTypeManager()->getDefinition('protocol');
    $build = ProtocolListBuilder::createInstance(\Drupal::getContainer(), $definition)->render();

    $rows = [];
    foreach ($build['#rows'] as $row) {
      $cell = $row['data'][0]['data'];
      // Orphan-protocol rows use plain markup, not a name/operations wrapper.
      if (!isset($cell['name'])) {
        continue;
      }
      $name = $cell['name']['label'] ?? $cell['name'];
      $label = isset($name['#markup']) ? (string) $name['#markup'] : (string) $name['#title'];
      preg_match('/depth-(\d+)/', implode(' ', $row['class']), $matches);
      $rows[$label] = (int) $matches[1];
    }
    return $rows;
  }

  /**
   * A non-member sees a public community even when its protocols are strict.
   */
  public function testNonMemberSeesPublicCommunityWithStrictProtocols(): void {
    $public = $this->createCommunity('Public Community', 'public');
    $this->createProtocol('Strict Protocol', $public, 'strict');
    $private = $this->createCommunity('Private Community', 'community-only');
    $this->setOrganization([$public->id() => 0, $private->id() => 0]);

    $rows = $this->communityRows();
    $this->assertArrayHasKey('Public Community', $rows);
    $this->assertArrayNotHasKey('Private Community', $rows);
  }

  /**
   * A member who isn't a community manager sees their community.
   */
  public function testMemberSeesCommunityWithNoVisibleProtocols(): void {
    $community = $this->createCommunity('Member Community', 'community-only');
    $this->createProtocol('Strict Protocol', $community, 'strict');
    $this->setOrganization([$community->id() => 0]);
    $community->addMember($this->currentUser);

    $this->assertArrayHasKey('Member Community', $this->communityRows());
  }

  /**
   * A viewable child of a hidden parent shows as a top-level row.
   */
  public function testChildOfHiddenParentShowsAtTopLevel(): void {
    $parent = $this->createCommunity('Hidden Parent', 'community-only');
    $child = $this->createCommunity('Public Child', 'public');
    $this->setOrganization([$parent->id() => 0, $child->id() => $parent->id()]);

    $rows = $this->communityRows();
    $this->assertSame(['Public Child' => 0], $rows);
  }

  /**
   * A child of a viewable parent stays nested under it.
   */
  public function testChildOfVisibleParentStaysNested(): void {
    $parent = $this->createCommunity('Public Parent', 'public');
    $child = $this->createCommunity('Public Child', 'public');
    $this->setOrganization([$parent->id() => 0, $child->id() => $parent->id()]);

    $this->assertSame(['Public Parent' => 0, 'Public Child' => 1], $this->communityRows());
  }

}
