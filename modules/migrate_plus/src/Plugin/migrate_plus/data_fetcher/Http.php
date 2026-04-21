<?php

declare(strict_types=1);

namespace Drupal\migrate_plus\Plugin\migrate_plus\data_fetcher;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\migrate\MigrateException;
use Drupal\migrate_plus\Attribute\DataFetcher;
use Drupal\migrate_plus\AuthenticationPluginInterface;
use Drupal\migrate_plus\AuthenticationPluginManager;
use Drupal\migrate_plus\DataFetcherPluginBase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Retrieve data over an HTTP connection for migration.
 *
 * Example:
 *
 * @code
 * source:
 *   plugin: url
 *   data_fetcher_plugin: http
 *   headers:
 *     Accept: application/json
 *     User-Agent: Internet Explorer 6
 *     Authorization-Key: secret
 *     Arbitrary-Header: fooBarBaz
 *   # Guzzle request options can be added.
 *   # See https://docs.guzzlephp.org/en/stable/request-options.html
 *   request_options:
 *     timeout: 300
 *     allow_redirects: false
 * @endcode
 */
#[DataFetcher(
  id: 'http',
  title: new TranslatableMarkup('HTTP')
)]
class Http extends DataFetcherPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The request headers.
   */
  protected array $headers = [];

  /**
   * The data retrieval client.
   */
  protected AuthenticationPluginInterface $authenticationPlugin;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected Client $httpClient,
    protected AuthenticationPluginManager $authenticationPluginManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    // Ensure there is a 'headers' key in the configuration.
    $configuration += ['headers' => []];
    $this->setRequestHeaders($configuration['headers']);
    // Set GET request-method by default.
    $configuration += ['method' => 'GET'];
    $this->configuration['method'] = $configuration['method'];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('plugin.manager.migrate_plus.authentication'),
    );
  }

  /**
   * Returns the initialized authentication plugin.
   *
   *   The authentication plugin.
   */
  public function getAuthenticationPlugin(): AuthenticationPluginInterface {
    if (!isset($this->authenticationPlugin)) {
      $this->authenticationPlugin = $this->authenticationPluginManager->createInstance($this->configuration['authentication']['plugin'], $this->configuration['authentication']);
    }
    return $this->authenticationPlugin;
  }

  /**
   * {@inheritdoc}
   */
  public function setRequestHeaders(array $headers): void {
    $this->headers = $headers;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestHeaders(): array {
    return !empty($this->headers) ? $this->headers : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse($url): ResponseInterface {
    try {
      $options = ['headers' => $this->getRequestHeaders()];
      if (!empty($this->configuration['authentication'])) {
        $options = NestedArray::mergeDeep($options, $this->getAuthenticationPlugin()->getAuthenticationOptions($url));
      }
      if (!empty($this->configuration['request_options'])) {
        $options = NestedArray::mergeDeep($options, $this->configuration['request_options']);
      }
      $method = $this->configuration['method'] ?? 'GET';
      $response = $this->httpClient->request($method, $url, $options);
      if (empty($response)) {
        throw new MigrateException('No response at ' . $url . '.');
      }
    }
    catch (RequestException $e) {
      throw new MigrateException('Error message: ' . $e->getMessage() . ' at ' . $url . '.');
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponseContent(string $url): string {
    return (string) $this->getResponse($url)->getBody();
  }

  /**
   * {@inheritdoc}
   */
  public function getNextUrls(string $url): array {
    $next_urls = [];

    $headers = $this->getResponse($url)->getHeader('Link');
    if (!empty($headers)) {
      $headers = explode(',', $headers[0]);
      foreach ($headers as $header) {
        $matches = [];
        preg_match('/^<(.*)>; rel="next"$/', trim($header), $matches);
        if (!empty($matches) && !empty($matches[1])) {
          $next_urls[] = $matches[1];
        }
      }
    }

    return array_merge(parent::getNextUrls($url), $next_urls);
  }

}
