<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\PageCache\ChainRequestPolicy;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests that cached JSON:API protocol and community lists vary by membership.
 *
 * The protocol and community access query alters filter these lists by the
 * current user's memberships. A list that is cached without the
 * mukurtu_memberships cache context is served to everyone who requests the
 * same URL, so a list cached for a member shows restricted names to people
 * outside the protocol or community, and a list cached for anyone else hides
 * them from the member.
 *
 * JSON:API ignores the entity types' list cache contexts. It only picks up
 * cacheability that the query bubbles while it runs.
 *
 * @see mukurtu_protocol_query_protocol_access_alter()
 * @see mukurtu_protocol_query_community_access_alter()
 */
#[Group('mukurtu_protocol')]
class JsonApiListCacheTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dynamic_page_cache',
    'jsonapi',
    'serialization',
  ];

  /**
   * A member of the restricted community and protocol.
   */
  protected AccountInterface $member;

  /**
   * Someone signed in, but in no community or protocol.
   */
  protected AccountInterface $outsider;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    // The default policy never caches command-line requests, which is how
    // Kernel tests run. A chain with no policies allows every GET.
    $container->getDefinition('dynamic_page_cache_request_policy')
      ->setClass(ChainRequestPolicy::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The view permission mukurtu_install() grants on a new site.
    Role::load(RoleInterface::AUTHENTICATED_ID)
      ->grantPermission('view published community entities')
      ->save();
    Role::create(['id' => RoleInterface::ANONYMOUS_ID, 'label' => 'Anonymous'])
      ->grantPermission('view published community entities')
      ->save();

    $public = Community::create([
      'name' => 'Public community',
      'field_access_mode' => 'public',
      'status' => 1,
    ]);
    $public->save();
    $restricted = Community::create([
      'name' => 'SECRET community',
      'field_access_mode' => 'community-only',
      'status' => 1,
    ]);
    $restricted->save();

    Protocol::create([
      'name' => 'Open protocol',
      'field_communities' => [$public->id()],
      'field_access_mode' => 'open',
    ])->save();
    $strict = Protocol::create([
      'name' => 'SECRET protocol',
      'field_communities' => [$restricted->id()],
      'field_access_mode' => 'strict',
    ]);
    $strict->save();

    $this->member = $this->createUser();
    $restricted->addMember($this->member);
    $strict->addMember($this->member);
    $this->outsider = $this->createUser();

    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Tests that a member's cached list never reaches anyone else.
   */
  public function testMemberListIsNotShared(): void {
    foreach (['/jsonapi/protocol/protocol', '/jsonapi/community/community'] as $path) {
      $this->assertStringContainsString('SECRET', $this->getBody($path, $this->member), "The member sees the restricted item at $path.");

      foreach ($this->nonMembers() as $who => $account) {
        $this->assertStringNotContainsString('SECRET', $this->getBody($path, $account), "$who must not get the member's cached list at $path.");
      }
    }
  }

  /**
   * Tests that a list cached for someone else doesn't hide a member's items.
   */
  public function testOtherListIsNotServedToMember(): void {
    foreach (['/jsonapi/protocol/protocol', '/jsonapi/community/community'] as $path) {
      foreach ($this->nonMembers() as $who => $account) {
        $this->assertStringNotContainsString('SECRET', $this->getBody($path, $account), "$who doesn't see the restricted item at $path.");
      }
      $this->assertStringContainsString('SECRET', $this->getBody($path, $this->member), "The member still sees the restricted item at $path.");
    }
  }

  /**
   * Tests that each access-checked list query bubbles the context.
   *
   * Media isn't in the JSON:API tests above, but its access query alter
   * filters by the same memberships.
   */
  public function testQueriesBubbleMembershipsContext(): void {
    $this->installEntitySchema('media');
    $this->setCurrentUser($this->member);
    $renderer = $this->container->get('renderer');
    $entity_type_manager = $this->container->get('entity_type.manager');

    foreach (['protocol', 'community', 'media'] as $entity_type_id) {
      $context = new RenderContext();
      $renderer->executeInRenderContext($context, function () use ($entity_type_manager, $entity_type_id) {
        return $entity_type_manager->getStorage($entity_type_id)->getQuery()->accessCheck(TRUE)->execute();
      });
      $this->assertFalse($context->isEmpty(), "The $entity_type_id query bubbles cacheability.");
      $this->assertContains('mukurtu_memberships', $context->pop()->getCacheContexts(), $entity_type_id);
    }
  }

  /**
   * Tests that the cache is really in play.
   */
  public function testCacheIsActive(): void {
    $this->getBody('/jsonapi/protocol/protocol', $this->member);
    $this->getBody('/jsonapi/protocol/protocol', $this->member);
    $this->assertSame('HIT', $this->lastCacheStatus);
  }

  /**
   * The X-Drupal-Dynamic-Cache header of the last response.
   */
  protected ?string $lastCacheStatus = NULL;

  /**
   * People outside the restricted community and protocol.
   *
   * @return \Drupal\Core\Session\AccountInterface[]
   *   Keyed by a short description.
   */
  protected function nonMembers(): array {
    return ['anonymous' => new AnonymousUserSession(), 'outsider' => $this->outsider];
  }

  /**
   * Sends a JSON:API GET request as an account and returns the body.
   */
  protected function getBody(string $path, AccountInterface $account): string {
    $this->resetRequestState();
    $this->setCurrentUser($account);
    $request = Request::create($path);
    $request->headers->set('Accept', 'application/vnd.api+json');
    $response = $this->container->get('http_kernel')->handle($request);
    $body = (string) $response->getContent();
    $this->assertSame(200, $response->getStatusCode(), "$path\n$body");
    $this->lastCacheStatus = $response->headers->get('X-Drupal-Dynamic-Cache');
    return $body;
  }

  /**
   * Clears what a real site wouldn't carry from one request to the next.
   *
   * A Kernel test runs every request in one PHP process, so in-memory caches
   * and the page cache kill switch otherwise leak between requests. The
   * dynamic page cache, which is what this test is about, is kept.
   */
  protected function resetRequestState(): void {
    $this->container->get('messenger')->deleteAll();
    $this->container->set('page_cache_kill_switch', new KillSwitch());
    $this->container->get('entity.memory_cache')->deleteAll();
    $this->container->get('cache.static')->deleteAll();
    $entity_type_manager = $this->container->get('entity_type.manager');
    foreach (array_keys($entity_type_manager->getDefinitions()) as $entity_type_id) {
      if ($entity_type_manager->hasHandler($entity_type_id, 'access')) {
        $entity_type_manager->getAccessControlHandler($entity_type_id)->resetCache();
      }
    }
    drupal_static_reset();
  }

}
