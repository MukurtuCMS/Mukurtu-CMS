<?php

declare(strict_types=1);

namespace Drupal\migrate_plus\Plugin\migrate_plus\authentication;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\migrate_plus\Attribute\Authentication;
use Drupal\migrate_plus\AuthenticationPluginBase;

/**
 * Provides NTLM authentication for the HTTP resource.
 */
#[Authentication(
  id: 'ntlm',
  title: new TranslatableMarkup('NTLM')
)]
class Ntlm extends AuthenticationPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationOptions($url): array {
    return [
      'auth' => [
        $this->configuration['username'],
        $this->configuration['password'],
        'ntlm',
      ],
    ];
  }

}
