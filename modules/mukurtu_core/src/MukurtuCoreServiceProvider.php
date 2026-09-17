<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\mukurtu_core\Service\DbIpFallbackGeoIpService;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers services that depend on an optional module actually being present.
 */
class MukurtuCoreServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    // mukurtu_core does not depend on visitors_geoip (see VisitorsCountryMap's
    // own docblock), so this can't be a static decorates: entry in
    // mukurtu_core.services.yml -- that would fail to compile on any site
    // that has not installed visitors_geoip, breaking the whole container.
    // Registering it here, conditionally, is the standard way to decorate an
    // optional service (see e.g. pathauto\PathautoServiceProvider for the
    // same pattern the other way around, removing a definition instead of
    // adding one).
    if (!$container->hasDefinition('visitors_geoip.lookup')) {
      return;
    }

    $container->register('mukurtu_core.dbip_geoip_fallback', DbIpFallbackGeoIpService::class)
      ->setDecoratedService('visitors_geoip.lookup')
      ->addArgument(new Reference('mukurtu_core.dbip_geoip_fallback.inner'))
      ->addArgument(new Reference('mukurtu_core.dbip_locator'));
  }

}
