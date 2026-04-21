<?php

declare(strict_types=1);

namespace Drupal\migrate_plus\Plugin\migrate_plus\authentication;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\migrate_plus\Attribute\Authentication;
use Drupal\migrate_plus\AuthenticationPluginBase;

/**
 * Provides basic authentication for the HTTP resource.
 */
#[Authentication(
  id: 'basic',
  title: new TranslatableMarkup('Basic')
)]
class Basic extends AuthenticationPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationOptions($url): array {
    return [
      'auth' => [
        $this->configuration['username'],
        $this->configuration['password'],
      ],
    ];
  }

}
