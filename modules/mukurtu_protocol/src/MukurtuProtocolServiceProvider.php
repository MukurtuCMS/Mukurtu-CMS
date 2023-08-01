<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Modifies the OG Group Audience Helper service.
 */
class MukurtuProtocolServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('og.group_audience_helper')) {
      $definition = $container->getDefinition('og.group_audience_helper');
      $definition->setClass('Drupal\mukurtu_protocol\MukurtuOgGroupAudienceHelper')
        ->addArgument(new Reference('entity_type.manager'))
        ->addArgument(new Reference('entity_field.manager'));
    }

    if ($container->hasDefinition('og.group_type_manager')) {
      $definition = $container->getDefinition('og.group_type_manager');
      $definition->setClass('Drupal\mukurtu_protocol\MukurtuProtocolGroupTypeManager')
        ->addArgument(new Reference('config.factory'))
        ->addArgument(new Reference('entity_type.manager'))
        ->addArgument(new Reference('entity_type.bundle.info'))
        ->addArgument(new Reference('event_dispatcher'))
        ->addArgument(new Reference('cache.data'))
        ->addArgument(new Reference('og.permission_manager'))
        ->addArgument(new Reference('og.role_manager'))
        ->addArgument(new Reference('router.builder'))
        ->addArgument(new Reference('og.group_audience_helper'));
    }

    if ($container->hasDefinition('content_moderation.state_transition_validation')) {
      $definition = $container->getDefinition('content_moderation.state_transition_validation');
      $definition->setClass('Drupal\mukurtu_protocol\ProtocolAwareStateTransitionValidation');
    }
  }

}
