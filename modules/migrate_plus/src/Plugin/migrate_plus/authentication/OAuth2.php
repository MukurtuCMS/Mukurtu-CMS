<?php

declare(strict_types=1);

namespace Drupal\migrate_plus\Plugin\migrate_plus\authentication;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\migrate\MigrateException;
use Drupal\migrate_plus\Attribute\Authentication;
use Drupal\migrate_plus\AuthenticationPluginBase;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Sainsburys\Guzzle\Oauth2\GrantType\AuthorizationCode;
use Sainsburys\Guzzle\Oauth2\GrantType\ClientCredentials;
use Sainsburys\Guzzle\Oauth2\GrantType\JwtBearer;
use Sainsburys\Guzzle\Oauth2\GrantType\PasswordCredentials;
use Sainsburys\Guzzle\Oauth2\GrantType\RefreshToken;
use Sainsburys\Guzzle\Oauth2\Middleware\OAuthMiddleware;

/**
 * Provides OAuth2 authentication for the HTTP resource.
 *
 * @link https://packagist.org/packages/sainsburys/guzzle-oauth2-plugin
 */
#[Authentication(
  id: 'oauth2',
  title: new TranslatableMarkup('OAuth2')
)]
class OAuth2 extends AuthenticationPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationOptions($url): array {
    $handlerStack = HandlerStack::create();
    $client = new Client([
      'handler' => $handlerStack,
      'base_uri' => $this->configuration['base_uri'],
      'auth' => 'oauth2',
    ]);

    $grant_type = match ($this->configuration['grant_type']) {
      'authorization_code' => new AuthorizationCode($client, $this->configuration),
        'client_credentials' => new ClientCredentials($client, $this->configuration),
        'urn:ietf:params:oauth:grant-type:jwt-bearer' => new JwtBearer($client, $this->configuration),
        'password' => new PasswordCredentials($client, $this->configuration),
        'refresh_token' => new RefreshToken($client, $this->configuration),
        default => throw new MigrateException("Unrecognized grant_type {$this->configuration['grant_type']}."),
    };
    $middleware = new OAuthMiddleware($client, $grant_type);

    return [
      'headers' => [
        'Authorization' => 'Bearer ' . $middleware->getAccessToken()->getToken(),
      ],
    ];
  }

}
