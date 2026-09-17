<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use Drupal\mukurtu_core\MukurtuCoreServiceProvider;
use Drupal\mukurtu_core\Service\DbIpFallbackGeoIpService;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the DB-IP decorator is only ever added when it is safe to.
 *
 * mukurtu_core does not depend on visitors_geoip, so this decoration cannot
 * be a static "decorates:" entry in mukurtu_core.services.yml -- that would
 * fail to compile the container entirely on a site that has not installed
 * visitors_geoip. This is what proves the conditional registration in
 * MukurtuCoreServiceProvider actually holds up on both sides of that.
 *
 * @see \Drupal\mukurtu_core\MukurtuCoreServiceProvider
 */
#[Group('mukurtu_core')]
class MukurtuCoreServiceProviderTest extends UnitTestCase {

  /**
   * Without visitors_geoip.lookup defined, nothing is added.
   *
   * The real-world case this guards: a site that has not installed
   * visitors_geoip at all. Registering a decorator for a service that does
   * not exist would throw when the container compiles, breaking the whole
   * site, not just this feature.
   */
  public function testNoDecoratorIsAddedWithoutTheDecoratedService(): void {
    $container = new ContainerBuilder();

    (new MukurtuCoreServiceProvider())->register($container);

    $this->assertFalse($container->hasDefinition('mukurtu_core.dbip_geoip_fallback'));
  }

  /**
   * With visitors_geoip.lookup defined, the decorator is added correctly.
   */
  public function testDecoratorIsAddedWhenTheDecoratedServiceExists(): void {
    $container = new ContainerBuilder();
    $container->register('visitors_geoip.lookup', 'SomeMaxMindBackedClass');

    (new MukurtuCoreServiceProvider())->register($container);

    $this->assertTrue($container->hasDefinition('mukurtu_core.dbip_geoip_fallback'));
    $definition = $container->getDefinition('mukurtu_core.dbip_geoip_fallback');
    $this->assertSame(DbIpFallbackGeoIpService::class, $definition->getClass());
    $this->assertSame('visitors_geoip.lookup', $definition->getDecoratedService()[0]);
  }

}
