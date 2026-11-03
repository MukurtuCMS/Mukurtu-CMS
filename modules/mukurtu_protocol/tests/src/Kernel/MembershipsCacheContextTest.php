<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_protocol\Cache\Context\MembershipsCacheContext;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\CommunityInterface;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\mukurtu_protocol\Entity\ProtocolInterface;
use Drupal\og\Og;
use Drupal\og\OgMembershipInterface;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the mukurtu_memberships cache context and where it's declared.
 *
 * @see \Drupal\mukurtu_protocol\Cache\Context\MembershipsCacheContext
 * @see mukurtu_protocol_post_update_listing_cache_varies_by_membership()
 */
#[Group('mukurtu_protocol')]
class MembershipsCacheContextTest extends ProtocolAwareEntityTestBase {

  /**
   * The community under test.
   */
  protected CommunityInterface $community;

  /**
   * A protocol in the community.
   */
  protected ProtocolInterface $protocol;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->community = Community::create([
      'name' => 'Community',
      'field_access_mode' => 'community-only',
    ]);
    $this->community->save();
    $this->protocol = Protocol::create([
      'name' => 'Protocol',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $this->protocol->save();
  }

  /**
   * Returns the context value for an account.
   */
  protected function contextFor(AccountInterface $account): string {
    // A new instance per call, since values are kept for the request.
    $context = new MembershipsCacheContext(
      $account,
      $this->container->get('database'),
      $this->container->get('private_key'),
    );
    return $context->getContext();
  }

  /**
   * Tests that the value follows memberships, states, and roles.
   */
  public function testContextVariesByMemberships(): void {
    $none_a = $this->createUser();
    $none_b = $this->createUser();
    $this->assertSame('user:none', $this->contextFor($none_a));
    $this->assertSame($this->contextFor($none_a), $this->contextFor($none_b));

    $member_a = $this->createUser();
    $member_b = $this->createUser();
    foreach ([$member_a, $member_b] as $account) {
      $this->community->addMember($account);
      $this->protocol->addMember($account);
    }
    $this->assertSame($this->contextFor($member_a), $this->contextFor($member_b));
    $this->assertNotSame($this->contextFor($none_a), $this->contextFor($member_a));

    // A blocked community membership changes which protocols apply.
    Og::getMembership($this->community, $member_b)
      ->setState(OgMembershipInterface::STATE_BLOCKED)
      ->save();
    $this->assertNotSame($this->contextFor($member_a), $this->contextFor($member_b));

    // So does a role.
    $steward = $this->createUser();
    $this->community->addMember($steward);
    $this->protocol->addMember($steward, ['protocol_steward']);
    $this->assertNotSame($this->contextFor($member_a), $this->contextFor($steward));
  }

  /**
   * Tests that user 1 never shares a value with another user.
   */
  public function testSuperUserHasOwnValue(): void {
    $super_user = $this->container->get('entity_type.manager')->getStorage('user')->load(1)
      ?? $this->createUser([], NULL, FALSE, ['uid' => 1]);
    $other = $this->createUser();
    $this->assertStringStartsWith('super-user:', $this->contextFor($super_user));
    $this->assertNotSame($this->contextFor($super_user), $this->contextFor($other));
  }

  /**
   * Tests that the filtered entity types declare the context for listings.
   */
  public function testListCacheContextsDeclared(): void {
    $entity_type_manager = $this->container->get('entity_type.manager');
    foreach (['community', 'protocol', 'media'] as $entity_type_id) {
      $this->assertContains('mukurtu_memberships', $entity_type_manager->getDefinition($entity_type_id)->getListCacheContexts(), $entity_type_id);
    }
  }

  /**
   * Tests that the post update adds the context to existing views.
   */
  public function testPostUpdateResavesViews(): void {
    $view = View::create([
      'id' => 'test_communities',
      'label' => 'Test communities',
      'base_table' => 'community_field_data',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_title' => 'Default',
          'position' => 0,
          'display_options' => [],
        ],
      ],
    ]);
    $view->save();

    // Simulate a view saved before the context was declared.
    $config = $this->container->get('config.factory')->getEditable('views.view.test_communities');
    $config->set('display.default.cache_metadata.contexts', ['user.permissions'])->save();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_protocol');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_protocol.post_update.php';
    mukurtu_protocol_post_update_listing_cache_varies_by_membership();

    $contexts = $this->container->get('config.factory')->get('views.view.test_communities')->get('display.default.cache_metadata.contexts');
    $this->assertContains('mukurtu_memberships', $contexts);
  }

}
