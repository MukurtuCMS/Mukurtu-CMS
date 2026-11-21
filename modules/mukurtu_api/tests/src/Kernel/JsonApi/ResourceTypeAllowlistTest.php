<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_api\Kernel\JsonApi;

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that mukurtu_api limits JSON:API to content-facing entity types.
 *
 * Proof of concept for the #400 spike: the same fixtures as the audit, with
 * the allowlist turned on.
 */
#[Group('mukurtu_api')]
class ResourceTypeAllowlistTest extends JsonApiProtocolAccessTest {

  /**
   * {@inheritdoc}
   *
   * KernelTestBase merges these with the parent class's modules.
   */
  protected static $modules = ['mukurtu_api', 'mukurtu_design'];

  /**
   * Internal entity types are gone, content types remain.
   */
  public function testAllowlist(): void {
    [$status] = $this->get('/jsonapi/og_membership/default', $this->member);
    $this->assertSame(404, $status);
    [$status] = $this->get('/jsonapi/node_type/node_type', $this->member);
    $this->assertSame(404, $status);
    [$status] = $this->get('/jsonapi/node/thing', $this->member);
    $this->assertSame(200, $status);
  }

  /**
   * Memberships are no longer exposed, so the inherited probe doesn't apply.
   */
  public function testMembershipCollection(): void {
    $this->assertTrue(TRUE, 'Covered by testAllowlist().');
  }

}
