<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_protocol\Kernel\Access;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\og\OgMembershipInterface;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests MukurtuProtocolMediaAccessControlHandler's "has protocols, is
 * member" branch for the 'view' operation on published media.
 *
 * MediaEmptyProtocolAccessTest only covers the empty-protocol-set
 * fallback; this covers the separate branch reached once a media entity
 * has cultural protocols assigned and the requester passes
 * isProtocolSetMember() - previously always forbidden for anyone but an
 * 'administer media' bypass, because that branch's 'view' case delegated
 * straight to core's parent::checkAccess(), which requires the global
 * 'view media' permission that this codebase only ever grants inside OG
 * protocol role configs (an OG group-context grant, never surfaced
 * through the account's site-wide hasPermission()).
 */
#[Group('mukurtu_protocol')]
class MediaProtocolMemberViewAccessTest extends KernelTestBase {

  use MediaTypeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'field',
    'file',
    'geofield',
    'image',
    'leaflet',
    'media',
    'media_test_source',
    'mukurtu_core',
    'mukurtu_protocol',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'workflows',
  ];

  protected Community $community;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['field', 'system', 'image', 'file', 'og', 'media']);
    $this->installEntitySchema('community');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('protocol');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('user');
    $this->installEntitySchema('workflow');
    $this->installSchema('file', 'file_usage');
    $this->installSchema('system', 'sequences');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');

    // Burn uid 1 on a throwaway account before any test method's own
    // createUser() calls - the first user created in a fresh kernel test
    // otherwise becomes uid 1, which bypasses every permission check
    // (Drupal's superuser convention), silently defeating the "is
    // forbidden" assertions below. Mirrors MediaEmptyProtocolAccessTest.
    $uid1 = User::create(['name' => 'uid1-placeholder', 'status' => 1]);
    $uid1->save();

    // CulturalProtocolItem::preSave() silently strips any protocol the
    // *current user* (not the entity owner) lacks 'apply protocol'
    // permission for, unless the current user is uid 1 - a guard meant to
    // stop a user from applying protocols via API/import that they
    // couldn't apply through the UI. Fixture setup below assigns
    // protocols to media on behalf of test users who hold no such
    // permission, so run as uid 1 to bypass that guard; the actual view
    // access assertions all pass their own $account explicitly and don't
    // depend on the current_user service.
    $this->container->get('current_user')->setAccount($uid1);

    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    // No explicit bundle class is assigned to this media type, so
    // mukurtu_protocol_entity_bundle_info_alter() gives it MukurtuMedia -
    // the same as any real, unconfigured media bundle.
    $this->createMediaType('test', ['id' => 'test']);

    $this->community = Community::create(['name' => 'Test Community']);
    $this->community->save();
  }

  /**
   * Creates a saved protocol in the shared test community.
   */
  protected function createProtocol(string $accessMode): Protocol {
    $protocol = Protocol::create([
      'name' => $this->randomString(),
      'field_communities' => [$this->community->id()],
      'field_access_mode' => $accessMode,
    ]);
    $protocol->save();
    return $protocol;
  }

  /**
   * Creates an OG role on the protocol bundle with the given permissions.
   *
   * Mirrors AccessByProtocolTest's pattern: create with a bare name (not
   * a full 'protocol-protocol-{name}' id) and let OgRole::save() derive
   * the id, matching how Protocol::addMember()'s
   * OgRole::getRole('protocol', 'protocol', $name) lookup resolves it.
   */
  protected function createProtocolRole(string $name, string $label, array $permissions = []): OgRole {
    $role = OgRole::create([
      'name' => $name,
      'label' => $label,
      'permissions' => $permissions,
    ]);
    $role->setGroupType('protocol');
    $role->setGroupBundle('protocol');
    $role->save();
    return $role;
  }

  /**
   * Creates a saved media entity under the given protocol(s).
   */
  protected function createProtocolMedia(array $protocols, string $sharingSetting, bool $published, int $ownerUid): Media {
    $media = Media::create([
      'bundle' => 'test',
      'uid' => $ownerUid,
      'status' => $published,
    ]);
    $media->setSharingSetting($sharingSetting);
    $media->setProtocols($protocols);
    $media->save();
    return $media;
  }

  /**
   * The regression this fixes: anonymous visitors of an open/public
   * protocol must be able to view published media - e.g. an External
   * Embed widget on a public Digital Heritage Item page.
   */
  public function testAnonymousCanViewOpenProtocolPublishedMedia(): void {
    $owner = $this->createUser();
    $openProtocol = $this->createProtocol('open');
    $media = $this->createProtocolMedia([$openProtocol], 'any', TRUE, (int) $owner->id());

    $anonymous = new AnonymousUserSession();
    $this->assertTrue(
      $media->access('view', $anonymous, TRUE)->isAllowed(),
      'Anonymous should view published media under an open protocol.'
    );
  }

  /**
   * Documents intentional, pre-existing behavior this fix now makes
   * reachable for media (it was already the case for nodes): a user
   * blocked from a community can still view published content under
   * that community's OPEN protocol. getMemberProtocols()'s open-protocol
   * branch grants virtual membership to everyone with no block check
   * (the check only exists in the strict-protocol branch a few lines
   * below it), and CulturalProtocols::getAccountGrantIds() confirms this
   * is deliberate at the node-grants level too: its "user has access to
   * all open protocols" loop runs unconditionally, after the blocked-
   * community filtering has already been applied to everything else.
   * This matches the Protocol entity's own field description: "Open -
   * Content...is visible to all site members and visitors, with no
   * login required" - a block from the community doesn't make someone
   * less than a visitor. Asserting this explicitly so a future change to
   * getMemberProtocols() has to consciously decide to change node and
   * media behavior together, rather than silently drifting them apart.
   */
  public function testBlockedCommunityMemberCanStillViewOpenProtocolPublishedMedia(): void {
    $owner = $this->createUser();
    $openProtocol = $this->createProtocol('open');
    $media = $this->createProtocolMedia([$openProtocol], 'any', TRUE, (int) $owner->id());

    $blockedUser = $this->createUser();
    $this->community->addMember($blockedUser);
    $membership = Og::getMembership($this->community, $blockedUser);
    $membership->setState(OgMembershipInterface::STATE_BLOCKED)->save();

    $this->assertTrue(
      $media->access('view', $blockedUser, TRUE)->isAllowed(),
      'A user blocked from the community should still view published media under the community\'s open protocol, matching node behavior.'
    );
  }

  /**
   * Unaffected by this fix: a closed protocol still hides published
   * media from a non-member anonymous visitor.
   */
  public function testAnonymousCannotViewClosedProtocolPublishedMedia(): void {
    $owner = $this->createUser();
    $strictProtocol = $this->createProtocol('strict');
    $media = $this->createProtocolMedia([$strictProtocol], 'any', TRUE, (int) $owner->id());

    $anonymous = new AnonymousUserSession();
    $this->assertTrue(
      $media->access('view', $anonymous, TRUE)->isForbidden(),
      'Anonymous should not view published media under a closed protocol.'
    );
  }

  /**
   * Also currently broken, now fixed (see class docblock for why): a
   * genuine OG protocol member ("Contributor", whose shipped role
   * already grants 'view media' at the OG level) can now view published
   * media under their own protocol. A stranger with no membership in the
   * same closed protocol remains forbidden.
   */
  public function testOgContributorMemberCanViewPublishedMediaUnderOwnProtocol(): void {
    $this->createProtocolRole('contributor', 'Contributor', ['view media']);

    $owner = $this->createUser();
    $strictProtocol = $this->createProtocol('strict');
    $media = $this->createProtocolMedia([$strictProtocol], 'any', TRUE, (int) $owner->id());

    $contributor = $this->createUser();
    $strictProtocol->addMember($contributor, ['contributor']);
    $this->assertTrue(
      $media->access('view', $contributor, TRUE)->isAllowed(),
      'An OG protocol contributor should view published media under their own protocol.'
    );

    $stranger = $this->createUser();
    $this->assertTrue(
      $media->access('view', $stranger, TRUE)->isForbidden(),
      'A non-member should still not view media under a closed protocol.'
    );
  }

  /**
   * Regression guard: protocol membership - including "virtual"
   * membership via an open protocol, and real OG membership - must not
   * bypass unpublished visibility. Behavior here is completely unchanged
   * by this fix (falls through to parent::checkAccess(), which returns
   * neutral for a non-owner with no 'view own unpublished media').
   */
  public function testProtocolMemberCannotAutomaticallyViewUnpublishedMedia(): void {
    $owner = $this->createUser();
    $openProtocol = $this->createProtocol('open');
    $unpublished = $this->createProtocolMedia([$openProtocol], 'any', FALSE, (int) $owner->id());

    $anonymous = new AnonymousUserSession();
    $this->assertTrue(
      $unpublished->access('view', $anonymous, TRUE)->isNeutral(),
      'Open-protocol "membership" must not grant view of unpublished media.'
    );

    $strictProtocol = $this->createProtocol('strict');
    $unpublished2 = $this->createProtocolMedia([$strictProtocol], 'any', FALSE, (int) $owner->id());
    $member = $this->createUser();
    $strictProtocol->addMember($member);
    $this->assertTrue(
      $unpublished2->access('view', $member, TRUE)->isNeutral(),
      'Real OG protocol membership must not grant view of unpublished media.'
    );
  }

  /**
   * 'all' (intersection) sharing: isProtocolSetMember() requires
   * membership in EVERY assigned protocol. A partial member never
   * reaches the new view branch (still forbidden, unaffected by this
   * fix). A member of every protocol is allowed by the new branch, even
   * with no OG role/permission at all - bare membership is sufficient
   * for view, unlike update/delete.
   */
  public function testAllSharingViewRequiresMembershipInEveryProtocol(): void {
    $owner = $this->createUser();
    $protocolA = $this->createProtocol('strict');
    $protocolB = $this->createProtocol('strict');
    $media = $this->createProtocolMedia([$protocolA, $protocolB], 'all', TRUE, (int) $owner->id());

    $partialMember = $this->createUser();
    $protocolA->addMember($partialMember);
    $this->assertTrue(
      $media->access('view', $partialMember, TRUE)->isForbidden(),
      'A member of only one of two required protocols should not view "all"-shared media.'
    );

    $fullMember = $this->createUser();
    $protocolA->addMember($fullMember);
    $protocolB->addMember($fullMember);
    $this->assertTrue(
      $media->access('view', $fullMember, TRUE)->isAllowed(),
      'A member of every required protocol should view "all"-shared media, with no explicit OG role needed.'
    );
  }

  /**
   * Directly covers the reviewer's concern: a public node can still have
   * an individually protocol-gated media asset alongside a public one.
   * Per-media protocol gating (checked before this fix's changed branch
   * is ever reached) is independent of any node-level "public" status,
   * so mixing a public-protocol and a strict-protocol media item (as if
   * both were attached to the same public Digital Heritage Item, per
   * issue #1141's original scenario) keeps the strict one hidden from
   * anonymous while the open one is now correctly shown.
   */
  public function testMixedProtocolMediaOnSamePublicNodeGatedIndependently(): void {
    $owner = $this->createUser();
    $openProtocol = $this->createProtocol('open');
    $strictProtocol = $this->createProtocol('strict');

    $publicMedia = $this->createProtocolMedia([$openProtocol], 'any', TRUE, (int) $owner->id());
    $restrictedMedia = $this->createProtocolMedia([$strictProtocol], 'any', TRUE, (int) $owner->id());

    $anonymous = new AnonymousUserSession();
    $this->assertTrue(
      $publicMedia->access('view', $anonymous, TRUE)->isAllowed(),
      'The public-protocol media asset should be visible to anonymous.'
    );
    $this->assertTrue(
      $restrictedMedia->access('view', $anonymous, TRUE)->isForbidden(),
      'The strict-protocol media asset should stay hidden from anonymous even though a sibling asset is public.'
    );
  }

}
