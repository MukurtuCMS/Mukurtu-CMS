<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\CommunityInterface;
use Drupal\og\Og;
use Drupal\og\OgMembershipInterface;
use Drupal\user\Entity\Role;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the legacy Browse by Community view hides what the viewer cannot see.
 *
 * The view itself filters only on status and langcode. Sharing is enforced by
 * mukurtu_protocol_query_community_access_alter(), which Views applies through
 * the community_access query tag unless SQL rewriting is disabled. Issue #2185
 * was filed from the filter list alone; this pins the real behaviour so the
 * view cannot start leaking community-only communities unnoticed. The block
 * plugin and /communities page are covered by CommunityListingAccessTest.
 */
#[Group('mukurtu_protocol')]
class BrowseByCommunityViewAccessTest extends ProtocolAwareEntityTestBase {

  /**
   * A published, public community.
   */
  protected CommunityInterface $publicCommunity;

  /**
   * A published, community-only community.
   */
  protected CommunityInterface $privateCommunity;

  /**
   * A plain, non-privileged user.
   */
  protected AccountInterface $viewer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    Role::load('authenticated')
      ->grantPermission('view published community entities')
      ->save();

    $this->publicCommunity = $this->createCommunity('Public Community', TRUE, 'public');
    $this->createCommunity('Unpublished Community', FALSE, 'public');
    $this->privateCommunity = $this->createCommunity('Private Community', TRUE, 'community-only');

    View::create($this->shippedView())->save();

    $this->viewer = $this->createUser();
    $this->setCurrentUser($this->viewer);
  }

  /**
   * Returns the view as shipped in config/install.
   */
  protected function shippedView(): array {
    $path = \Drupal::service('extension.list.module')->getPath('mukurtu_protocol');
    return Yaml::decode(file_get_contents("$path/config/install/views.view.browse_by_community.yml"));
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
   * Returns the community IDs the block display lists for the current user.
   */
  protected function listedIds(): array {
    $view = Views::getView('browse_by_community');
    $view->setDisplay('community_browse_block');
    $view->execute();
    $ids = array_map(fn($row) => (int) $row->_entity->id(), $view->result);
    sort($ids);
    return $ids;
  }

  /**
   * The shipped view leaves SQL rewriting on, which the access alter needs.
   */
  public function testSqlRewriteIsEnabled(): void {
    foreach ($this->shippedView()['display'] as $id => $display) {
      $this->assertEmpty(
        $display['display_options']['query']['options']['disable_sql_rewrite'] ?? FALSE,
        "Display $id must not disable SQL rewriting."
      );
    }
  }

  /**
   * A non-member sees only the public community.
   */
  public function testNonMemberSeesOnlyPublic(): void {
    $this->assertSame([(int) $this->publicCommunity->id()], $this->listedIds());
  }

  /**
   * Anonymous users see only the public community.
   */
  public function testAnonymousSeesOnlyPublic(): void {
    $this->setCurrentUser(new AnonymousUserSession());

    $this->assertSame([(int) $this->publicCommunity->id()], $this->listedIds());
  }

  /**
   * A member also sees the community-only community they belong to.
   */
  public function testMemberSeesCommunityOnlyCommunity(): void {
    $this->privateCommunity->addMember($this->viewer);

    $expected = [(int) $this->publicCommunity->id(), (int) $this->privateCommunity->id()];
    sort($expected);
    $this->assertSame($expected, $this->listedIds());
  }

  /**
   * Creates a community with an invalid access mode as another user.
   *
   * Older Mukurtu 3 migrations wrote "open", and the access handler treats
   * any value other than "public" as community-only. The creator becomes a
   * member, so it's created as someone other than the viewer.
   */
  protected function createInvalidCommunity(): CommunityInterface {
    $this->setCurrentUser($this->createUser());
    $community = $this->createCommunity('Invalid Community', TRUE, 'open');
    $this->setCurrentUser($this->viewer);
    return $community;
  }

  /**
   * A non-member doesn't see a community with an invalid access mode.
   */
  public function testNonMemberDoesNotSeeInvalidAccessMode(): void {
    $this->createInvalidCommunity();

    $this->assertSame([(int) $this->publicCommunity->id()], $this->listedIds());
  }

  /**
   * A member sees a community with an invalid access mode.
   */
  public function testMemberSeesInvalidAccessMode(): void {
    $invalid = $this->createInvalidCommunity();
    $invalid->addMember($this->viewer);

    $expected = [(int) $this->publicCommunity->id(), (int) $invalid->id()];
    sort($expected);
    $this->assertSame($expected, $this->listedIds());
  }

  /**
   * A blocked membership does not grant visibility.
   */
  public function testBlockedMemberSeesOnlyPublic(): void {
    $this->privateCommunity->addMember($this->viewer);
    Og::getMembership($this->privateCommunity, $this->viewer)
      ->setState(OgMembershipInterface::STATE_BLOCKED)
      ->save();

    $this->assertSame([(int) $this->publicCommunity->id()], $this->listedIds());
  }

  /**
   * Disabling SQL rewriting leaks the community-only community.
   *
   * The negative control: proves the assertions above depend on the access
   * alter, and are not passing for some other reason.
   */
  public function testDisablingSqlRewriteLeaks(): void {
    $view = View::load('browse_by_community');
    $display = $view->get('display');
    $display['default']['display_options']['query']['options']['disable_sql_rewrite'] = TRUE;
    $view->set('display', $display)->save();

    $this->assertContains((int) $this->privateCommunity->id(), $this->listedIds());
  }

}
