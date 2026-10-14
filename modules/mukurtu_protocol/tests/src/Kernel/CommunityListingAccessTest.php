<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_protocol\Controller\CommunitiesPageController;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\CommunityInterface;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that community listings only show communities the user can view.
 *
 * Covers the "Browse by Community" block and the /communities page, which
 * share the same filtering logic.
 */
#[Group('mukurtu_protocol')]
class CommunityListingAccessTest extends ProtocolAwareEntityTestBase {

  /**
   * A published, public community.
   */
  protected CommunityInterface $publicCommunity;

  /**
   * An unpublished, public community.
   */
  protected CommunityInterface $unpublishedCommunity;

  /**
   * A published, community-only community.
   */
  protected CommunityInterface $privateCommunity;

  /**
   * A plain, non-privileged user.
   *
   * The base class's current user is UID 1, which bypasses all access
   * checks via core's superuser access policy.
   */
  protected AccountInterface $viewer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Required by the block plugin manager's block_content deriver, which
    // queries this table when discovering block plugin definitions.
    $this->installEntitySchema('block_content');

    Role::load('authenticated')
      ->grantPermission('view published community entities')
      ->save();

    $this->publicCommunity = $this->createCommunity('Public Community', TRUE, 'public');
    $this->unpublishedCommunity = $this->createCommunity('Unpublished Community', FALSE, 'public');
    $this->privateCommunity = $this->createCommunity('Private Community', TRUE, 'community-only');

    $organization = [];
    foreach ([$this->publicCommunity, $this->unpublishedCommunity, $this->privateCommunity] as $weight => $community) {
      $organization[$community->id()] = ['parent' => 0, 'weight' => $weight];
    }
    $this->config('mukurtu_protocol.community_organization')
      ->set('organization', $organization)
      ->save();

    $viewer = $this->createUser();
    $viewer->save();
    $this->viewer = $viewer;
    $this->setCurrentUser($this->viewer);
  }

  /**
   * Creates and saves a community.
   */
  protected function createCommunity(string $name, bool $status, string $sharing): CommunityInterface {
    $community = Community::create([
      'name' => $name,
      'status' => $status,
      'field_access_mode' => $sharing,
    ]);
    $community->save();
    return $community;
  }

  /**
   * Builds the Browse by Community block.
   */
  protected function buildBlock(): array {
    return $this->container->get('plugin.manager.block')
      ->createInstance('mukurtu_browse_by_community', [])
      ->build();
  }

  /**
   * Builds the /communities page.
   */
  protected function buildPage(): array {
    return $this->container->get('class_resolver')
      ->getInstanceFromDefinition(CommunitiesPageController::class)
      ->page();
  }

  /**
   * Returns the IDs of the communities in a listing build.
   */
  protected function listedIds(array $communities): array {
    return array_map(fn(array $build) => $build['#community']->id(), $communities);
  }

  /**
   * Returns the listed community IDs from both the block and the page.
   */
  protected function listedIdsForBoth(): array {
    return [
      'block' => $this->listedIds($this->buildBlock()['#communities']),
      'page' => $this->listedIds($this->buildPage()['template']['#communities']),
    ];
  }

  /**
   * Unpublished and non-member community-only communities are hidden.
   */
  public function testNonMemberSeesOnlyViewableCommunities(): void {
    foreach ($this->listedIdsForBoth() as $listing => $ids) {
      $this->assertSame([$this->publicCommunity->id()], $ids, "$listing listing");
    }
  }

  /**
   * Members see the community-only communities they belong to.
   */
  public function testMemberSeesCommunityOnlyCommunity(): void {
    $this->privateCommunity->addMember($this->viewer);

    foreach ($this->listedIdsForBoth() as $listing => $ids) {
      $this->assertSame([$this->publicCommunity->id(), $this->privateCommunity->id()], $ids, "$listing listing");
    }
  }

  /**
   * Users who can view unpublished communities still see them.
   */
  public function testUnpublishedVisibleWithPermission(): void {
    Role::load('authenticated')
      ->grantPermission('view unpublished community entities')
      ->save();

    foreach ($this->listedIdsForBoth() as $listing => $ids) {
      $this->assertSame([$this->publicCommunity->id(), $this->unpublishedCommunity->id()], $ids, "$listing listing");
    }
  }

  /**
   * Users without the view permission see no communities.
   */
  public function testNoCommunitiesWithoutViewPermission(): void {
    Role::load('authenticated')
      ->revokePermission('view published community entities')
      ->save();

    foreach ($this->listedIdsForBoth() as $listing => $ids) {
      $this->assertSame([], $ids, "$listing listing");
    }
  }

  /**
   * The listings vary by user, so one user's list is never served to another.
   */
  public function testListingsCarryAccessCacheability(): void {
    $builds = [
      'block' => $this->buildBlock(),
      'page' => $this->buildPage(),
    ];
    foreach ($builds as $listing => $build) {
      $contexts = $build['#cache']['contexts'] ?? [];
      $tags = $build['#cache']['tags'] ?? [];
      $this->assertContains('user', $contexts, "$listing listing");
      $this->assertContains('user.permissions', $contexts, "$listing listing");
      $this->assertContains('config:mukurtu_protocol.community_organization', $tags, "$listing listing");
      $this->assertContains('community:' . $this->unpublishedCommunity->id(), $tags, "$listing listing");
    }
  }

}
