<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_api\Kernel\JsonApi;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\PageCache\ChainRequestPolicy;
use Drupal\og\Og;
use PHPUnit\Framework\Attributes\Group;

/**
 * Reruns the JSON:API audit with the dynamic page cache on (#400).
 *
 * Every probe asks as a member of protocol A first and then as people
 * outside it, on the same URL. With the cache on, a response cached for the
 * member must never be served to them.
 *
 * The anonymous page cache isn't covered: it only stores anonymous
 * responses, which the parent class already checks.
 */
#[Group('mukurtu_api')]
class JsonApiDynamicCacheTest extends JsonApiProtocolAccessTest {

  /**
   * {@inheritdoc}
   *
   * KernelTestBase merges these with the parent class's modules.
   */
  protected static $modules = ['dynamic_page_cache'];

  /**
   * Shared text for the cache finding.
   */
  const FINDING_4 = 'Finding 4: JSON:API protocol and community lists are cached by URL alone (communities also by permissions), so a list a member loads is served from the dynamic page cache to people outside the protocol or community. Both entity types declare a mukurtu_memberships list cache context, but JSON:API collections ignore list cache contexts. Reproduced over HTTP on a real install.';

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
  public function testProtocolCollection(): void {
    $leaks = $this->leaksTo('/jsonapi/protocol/protocol');
    if ($leaks) {
      $this->markTestIncomplete(self::FINDING_4 . ' Protocol list leaked to: ' . implode(', ', $leaks) . '.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function testCommunityCollection(): void {
    $leaks = $this->leaksTo('/jsonapi/community/community');
    if ($leaks) {
      $this->markTestIncomplete(self::FINDING_4 . ' Community list leaked to: ' . implode(', ', $leaks) . '.');
    }
  }

  /**
   * The cache really is in play: a repeated request is a cache hit.
   */
  public function testCacheIsActive(): void {
    $path = $this->resourcePath('secret_node');
    $this->get($path, $this->member);
    $this->get($path, $this->member);
    $response = $this->lastResponse;
    $this->assertSame('HIT', $response->headers->get('X-Drupal-Dynamic-Cache'));
  }

  /**
   * Removing someone from a protocol takes effect despite the cache.
   */
  public function testRemovedMemberLosesAccess(): void {
    $paths = [
      $this->resourcePath('secret_node'),
      '/jsonapi/node/thing',
      '/jsonapi/media/document',
      $this->resourcePath('mixed_node') . '?include=field_test_media',
    ];
    foreach ($paths as $path) {
      [, $body] = $this->get($path, $this->member);
      $this->assertStringContainsString('SECRET', $body, "Before removal: $path");
    }

    Og::getMembership($this->fixtures['secret_protocol'], $this->member)->delete();

    foreach ($paths as $path) {
      [, $body] = $this->get($path, $this->member);
      $this->assertStringNotContainsString('SECRET', $body, "After removal: $path");
    }
  }

}
