<?php

namespace Drupal\search_api_solr\SolrConnector;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Url;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\Plugin\ConfigurablePluginBase;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\Solarium\Autocomplete\Query as AutocompleteQuery;
use Drupal\search_api_solr\SolrConnectorInterface;
use Solarium\Client;
use Solarium\Core\Client\Adapter\Curl;
use Solarium\Core\Client\Adapter\Http;
use Solarium\Core\Client\Adapter\TimeoutAwareInterface;
use Solarium\Core\Client\Endpoint;
use Solarium\Core\Client\Request;
use Solarium\Core\Client\Response;
use Solarium\Core\Query\QueryInterface;
use Solarium\Exception\HttpException;
use Solarium\QueryType\Analysis\Query\AbstractQuery;
use Solarium\QueryType\Analysis\Query\Field;
use Solarium\QueryType\Extract\Result as ExtractResult;
use Solarium\QueryType\Select\Query\Query;
use Solarium\QueryType\Update\Query\Query as UpdateQuery;
use ZipStream\ZipStream;

/**
 * Defines a base class for Solr connector plugins.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition arrays may be altered through
 * hook_search_api_solr_connector_info_alter(). The definition includes the
 * following keys:
 * - id: The unique, system-wide identifier of the backend class.
 * - label: The human-readable name of the backend class, translated.
 * - description: A human-readable description for the backend class,
 *   translated.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @SolrConnector(
 *   id = "my_connector",
 *   label = @Translation("My connector"),
 *   description = @Translation("Authenticates with SuperAuth™.")
 * )
 * @endcode
 *
 * @see \Drupal\search_api_solr\Annotation\SolrConnector
 * @see \Drupal\search_api_solr\SolrConnector\SolrConnectorPluginManager
 * @see \Drupal\search_api_solr\SolrConnectorInterface
 * @see plugin_api
 */
abstract class SolrConnectorPluginBase extends ConfigurablePluginBase implements SolrConnectorInterface, PluginFormInterface {

  use PluginFormTrait {
    submitConfigurationForm as traitSubmitConfigurationForm;
  }

  use LoggerTrait;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * A connection to the Solr server.
   *
   * @var \Solarium\Client
   */
  protected $solr;

  /**
   * {@inheritdoc}
   */
  public function setEventDispatcher(EventDispatcherInterface $eventDispatcher) : SolrConnectorInterface {
    $this->eventDispatcher = $eventDispatcher;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'scheme' => 'http',
      'host' => 'localhost',
      'port' => 8983,
      'path' => '/',
      'core' => '',
      'timeout' => 5,
      self::INDEX_TIMEOUT => 5,
      self::OPTIMIZE_TIMEOUT => 10,
      self::FINALIZE_TIMEOUT => 30,
      'solr_version' => '',
      'http_method' => 'AUTO',
      'commit_within' => 1000,
      'jmx' => FALSE,
      'jts' => FALSE,
      'solr_install_dir' => '',
      'skip_schema_check' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $configuration['port'] = (int) $configuration['port'];
    $configuration['timeout'] = (int) $configuration['timeout'];
    $configuration[self::INDEX_TIMEOUT] = (int) $configuration[self::INDEX_TIMEOUT];
    $configuration[self::OPTIMIZE_TIMEOUT] = (int) $configuration[self::OPTIMIZE_TIMEOUT];
    $configuration[self::FINALIZE_TIMEOUT] = (int) $configuration[self::FINALIZE_TIMEOUT];
    $configuration['commit_within'] = (int) $configuration['commit_within'];
    $configuration['jmx'] = (bool) $configuration['jmx'];
    $configuration['jts'] = (bool) $configuration['jts'];
    $configuration['skip_schema_check'] = (bool) $configuration['skip_schema_check'];

    parent::setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['scheme'] = [
      '#type' => 'select',
      '#title' => $this->t('HTTP protocol'),
      '#description' => $this->t('The HTTP protocol to use for sending queries.'),
      '#default_value' => $this->configuration['scheme'] ?? 'http',
      '#options' => [
        'http' => $this->t('http'),
        'https' => $this->t('https'),
      ],
    ];

    $form['host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Solr host'),
      '#description' => $this->t('The host name or IP of your Solr server, e.g. <code>localhost</code> or <code>www.example.com</code>.'),
      '#default_value' => $this->configuration['host'] ?? '',
      '#required' => TRUE,
    ];

    $form['port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Solr port'),
      '#description' => $this->t('The Jetty example server is at port 8983, while Tomcat uses 8080 by default.'),
      '#default_value' => $this->configuration['port'] ?? '',
      '#required' => TRUE,
    ];

    $form['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Solr path'),
      '#description' => $this->t('The path that identifies the Solr instance to use on the server.'),
      '#default_value' => $this->configuration['path'] ?? '/',
    ];

    $form['core'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Solr core'),
      '#description' => $this->t('The name that identifies the Solr core to use on the server.'),
      '#default_value' => $this->configuration['core'] ?? '',
      '#required' => TRUE,
    ];

    $form['timeout'] = [
      '#type' => 'number',
      '#min' => 1,
      '#max' => 180,
      '#title' => $this->t('Query timeout'),
      '#description' => $this->t('The timeout in seconds for search queries sent to the Solr server.'),
      '#default_value' => $this->configuration['timeout'] ?? 5,
      '#required' => TRUE,
    ];

    $form[self::INDEX_TIMEOUT] = [
      '#type' => 'number',
      '#min' => 1,
      '#max' => 180,
      '#title' => $this->t('Index timeout'),
      '#description' => $this->t('The timeout in seconds for indexing requests to the Solr server.'),
      '#default_value' => $this->configuration[self::INDEX_TIMEOUT] ?? 5,
      '#required' => TRUE,
    ];

    $form[self::OPTIMIZE_TIMEOUT] = [
      '#type' => 'number',
      '#min' => 1,
      '#max' => 180,
      '#title' => $this->t('Optimize timeout'),
      '#description' => $this->t('The timeout in seconds for background index optimization queries on a Solr server.'),
      '#default_value' => $this->configuration[self::OPTIMIZE_TIMEOUT] ?? 10,
      '#required' => TRUE,
    ];

    $form[self::FINALIZE_TIMEOUT] = [
      '#type' => 'number',
      '#min' => 1,
      '#max' => 180,
      '#title' => $this->t('Finalize timeout'),
      '#description' => $this->t('The timeout in seconds for index finalization queries on a Solr server.'),
      '#default_value' => $this->configuration[self::FINALIZE_TIMEOUT] ?? 30,
      '#required' => TRUE,
    ];

    $form['commit_within'] = [
      '#type' => 'number',
      '#min' => 0,
      '#title' => $this->t('Commit within'),
      '#description' => $this->t('The limit in milliseconds within a (soft) commit on Solr is forced after any updating the index in any way. Setting the value to "0" turns off this dynamic enforcement and lets Solr behave like configured solrconf.xml.'),
      '#default_value' => $this->configuration['commit_within'] ?? 1000,
      '#required' => TRUE,
    ];

    $form['workarounds'] = [
      '#type' => 'details',
      '#title' => $this->t('Connector Workarounds'),
    ];

    $form['workarounds']['solr_version'] = [
      '#type' => 'select',
      '#title' => $this->t('Solr version override'),
      '#description' => $this->t('Specify the Solr version manually in case it cannot be retrieved automatically. The version can be found in the Solr admin interface under "Solr Specification Version" or "solr-spec"'),
      '#options' => [
        '' => $this->t('Determine automatically'),
        '6' => '6.x',
        '7' => '7.x',
        '8' => '8.x',
        '9' => '9.x',
      ],
      '#default_value' => $this->configuration['solr_version'] ?? '',
    ];

    $form['workarounds']['http_method'] = [
      '#type' => 'select',
      '#title' => $this->t('HTTP method'),
      '#description' => $this->t('The HTTP method to use for sending queries. GET will often fail with larger queries, while POST should not be cached. AUTO will use GET when possible, and POST for queries that are too large.'),
      '#default_value' => $this->configuration['http_method'] ?? 'AUTO',
      '#options' => [
        'AUTO' => $this->t('AUTO'),
        'POST' => $this->t('POST'),
        'GET' => $this->t('GET'),
      ],
    ];

    $form['workarounds']['skip_schema_check'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip schema verification'),
      '#description' => $this->t('Skip the automatic check for schema-compatibility. Use this override if you are seeing an error-message about an incompatible schema.xml configuration file, and you are sure the configuration is compatible.'),
      '#default_value' => $this->configuration['skip_schema_check'] ?? FALSE,
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced server configuration'),
    ];

    $form['advanced']['jmx'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable JMX'),
      '#description' => $this->t('Enable JMX based monitoring. Note: Only valid for Solr versions before Solr 9.'),
      '#default_value' => $this->configuration['jmx'] ?? FALSE,
    ];

    $form['advanced']['jts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable JTS'),
      '#description' => $this->t('Enable JTS (java topographic suite). Be sure to follow instructions in last solr reference guide about how to use spatial search.'),
      '#default_value' => $this->configuration['jts'] ?? FALSE,
    ];

    $form['advanced']['solr_install_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('solr.install.dir'),
      '#description' => $this->t('The path where Solr is installed on the server, relative to the configuration or absolute. Some examples are "../../.." for Solr downloaded from apache.org, "/usr/local/opt/solr" for installations via homebrew on macOS or "/opt/solr" for some linux distributions and for the official Solr docker container. If you use different systems for development, testing and production you can use drupal config overwrites to adjust the value per environment or adjust the generated solrcore.properties per environment or use java virtual machine options (-D) to set the property. Modern Solr installations should set that virtual machine option correctly in their start script by themselves. In this case this field should be left empty!'),
      '#default_value' => $this->configuration['solr_install_dir'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (isset($values['port']) && (!is_numeric($values['port']) || $values['port'] < 0 || $values['port'] > 65535)) {
      $form_state->setError($form['port'], $this->t('The port has to be an integer between 0 and 65535.'));
    }
    if (!empty($values['path']) && strpos($values['path'], '/') !== 0) {
      $form_state->setError($form['path'], $this->t('If provided the path has to start with "/".'));
    }
    if (!empty($values['core']) && strpos($values['core'], '/') === 0) {
      $form_state->setError($form['core'], $this->t('Core or collection must not start with "/".'));
    }

    if (!$form_state->hasAnyErrors()) {
      // Try to orchestrate a server link from form values.
      $values_copied = $values;
      $solr = $this->createClient($values_copied);
      $solr->createEndpoint($values_copied + ['key' => 'search_api_solr'], TRUE);
      try {
        $this->getServerLink();
      }
      catch (\InvalidArgumentException $e) {
        foreach (['scheme', 'host', 'port', 'path', 'core'] as $part) {
          $form_state->setError($form[$part], $this->t('The server link generated from the form values is illegal.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Since the form is nested into another, we can't simply use #parents for
    // doing this array restructuring magic. (At least not without creating an
    // unnecessary dependency on internal implementation.)
    foreach ($values['workarounds'] as $key => $value) {
      $form_state->setValue($key, $value);
    }
    foreach ($values['advanced'] as $key => $value) {
      $form_state->setValue($key, $value);
    }

    // Clean-up the form to avoid redundant entries in the stored configuration.
    $form_state->unsetValue('workarounds');
    $form_state->unsetValue('advanced');

    $this->traitSubmitConfigurationForm($form, $form_state);
  }

  /**
   * Prepares the connection to the Solr server.
   */
  protected function connect() {
    if (!$this->solr) {
      $configuration = $this->configuration;
      $this->solr = $this->createClient($configuration);
      $this->solr->createEndpoint($configuration + ['key' => 'search_api_solr'], TRUE);
    }
  }

  /**
   * Create a Client.
   */
  protected function createClient(array &$configuration) {
    // @todo For backward compatibility we didn't rename 'timeout' yet. We
    // should do so in an update hook.
    $configuration[self::QUERY_TIMEOUT] = $configuration['timeout'] ?? 5;
    unset($configuration['timeout']);

    $adapter = extension_loaded('curl') ? new Curl() : new Http();
    $adapter->setTimeout($configuration[self::QUERY_TIMEOUT]);

    return new Client($adapter, $this->eventDispatcher);
  }

  /**
   * {@inheritdoc}
   */
  public function isCloud() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isTrustedContextSupported() {
    return FALSE;
  }

  /**
   * Returns the Solr server URI.
   */
  protected function getServerUri() {
    $this->connect();

    return $this->solr->getEndpoint()->getServerUri();
  }

  /**
   * {@inheritdoc}
   */
  public function getServerLink() {
    $url_path = $this->getServerUri();
    if ($this->configuration['host'] === 'localhost' && !empty($_SERVER['SERVER_NAME'])) {
      // In most cases "localhost" could not be resolved from the UI in the
      // browser. Try the 'SERVER_NAME'.
      $url_path = str_replace('localhost', $_SERVER['SERVER_NAME'], $url_path);
    }
    $url = Url::fromUri($url_path);

    return Link::fromTextAndUrl($url_path, $url);
  }

  /**
   * {@inheritdoc}
   */
  public function getCoreLink() {
    $url_path = $this->getServerUri() . 'solr/#/' . $this->configuration['core'];
    if ($this->configuration['host'] === 'localhost' && !empty($_SERVER['SERVER_NAME'])) {
      // In most cases "localhost" could not be resolved from the UI in the
      // browser. Try the 'SERVER_NAME'.
      $url_path = str_replace('localhost', $_SERVER['SERVER_NAME'], $url_path);
    }
    $url = Url::fromUri($url_path);

    return Link::fromTextAndUrl($url_path, $url);
  }

  /**
   * {@inheritdoc}
   */
  public function getSolrVersion($force_auto_detect = FALSE) {
    // Allow for overrides by the user.
    if (!$force_auto_detect && !empty($this->configuration['solr_version'])) {
      // In most cases the already stored solr_version is just the major version
      // number as integer. In this case we will expand it to the minimum
      // corresponding full version string.
      $min_version = ['0', '0', '0'];
      $version = implode('.', explode('.', $this->configuration['solr_version']) + $min_version);
      switch ($version) {
        case '3.0.0':
          // 3.6.0 is the minimum supported Solr 3 version by the
          // search_api_solr_legacy module.
          $version = '3.6.0';
          break;

        case '4.0.0':
          // 4.5.0 is the minimum supported Solr 4 version by the
          // search_api_solr_legacy module.
          $version = '4.5.0';
          break;

        case '6.0.0':
          // 6.4.0 is the minimum supported Solr version. Earlier Solr 6
          // versions should run in Solr 5 compatibility mode using the
          // search_api_solr_legacy module.
          $version = '6.4.0';
          break;
      }
      return $version;
    }

    $info = [];
    try {
      $info = $this->getCoreInfo();
    }
    catch (\Exception $e) {
      try {
        $info = $this->getServerInfo();
      }
      catch (SearchApiSolrException $e) {
      }
    }

    // Get our solr version number.
    if (isset($info['lucene']['solr-spec-version'])) {
      // Some Solr distributions or docker images append additional info to the
      // version number, for example the build date: 3.6.2.2012.12.18.19.52.27.
      if (preg_match('/^(\d+\.\d+\.\d+)/', $info['lucene']['solr-spec-version'], $matches)) {
        return $matches[1];
      }
    }

    return '0.0.0';
  }

  /**
   * {@inheritdoc}
   */
  public function getLuceneVersion(): string {
    $info = [];
    try {
      $info = $this->getCoreInfo();
    }
    catch (\Exception $e) {
      try {
        $info = $this->getServerInfo();
      }
      catch (SearchApiSolrException $e) {
      }
    }

    // If the APIs used above aren't blocked, we can use their result to get
    // the exact lucene version.
    if (isset($info['lucene']['lucene-spec-version'])) {
      if (preg_match('/^(\d+\.\d+\.\d+)/', $info['lucene']['lucene-spec-version'], $matches)) {
        return $matches[1];
      }
    }

    // Before Solr 9, the lucene and the Solr versions were in sync. If we don't
    // have access to the exact lucene version above, we just can assume a
    // lucene version.
    $version = $this->getSolrVersion();
    if (version_compare($version, '9.0.0', '<')) {
      [$major, $minor] = explode('.', $version);
      return $major . '.' . $minor;
    }
    else {
      if (version_compare($version, '9.2.0', '>=')) {
        if (version_compare($version, '9.4.0', '<')) {
          return '9.4.2';
        }
        if (version_compare($version, '9.6.0', '<')) {
          return '9.8.0';
        }
        // Solr 9.6.0 uses lucene 9.10.0.
        return '9.10.0';
      }
    }

    return '9.1.0';
  }

  /**
   * {@inheritdoc}
   */
  public function getSolrMajorVersion($version = ''): int {
    [$major] = explode('.', $version ?: $this->getSolrVersion());
    return (int) $major;
  }

  /**
   * {@inheritdoc}
   */
  public function getSolrBranch($version = '') {
    return $this->getSolrMajorVersion($version) . '.x';
  }

  /**
   * {@inheritdoc}
   */
  public function getLuceneMatchVersion($minimal_version = '') {
    $preferred_version = $this->getLuceneVersion();
    if ($minimal_version && version_compare($preferred_version, $minimal_version, '<=')) {
      return $minimal_version;
    }

    return $preferred_version;
  }

  /**
   * {@inheritdoc}
   */
  public function getServerInfo($reset = FALSE) {
    $this->useTimeout();
    return $this->getDataFromHandler('admin/info/system', $reset);
  }

  /**
   * {@inheritdoc}
   */
  public function getCoreInfo($reset = FALSE) {
    $this->useTimeout();
    return $this->getDataFromHandler($this->configuration['core'] . '/admin/system', $reset);
  }

  /**
   * {@inheritdoc}
   */
  public function getLuke() {
    $this->useTimeout();
    return $this->getDataFromHandler($this->configuration['core'] . '/admin/luke', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSetName(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSchemaVersionString($reset = FALSE) {
    return $this->getCoreInfo($reset)['core']['schema'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSchemaVersion($reset = FALSE) {
    $parts = explode('-', $this->getSchemaVersionString($reset));
    return $parts[1];
  }

  /**
   * {@inheritdoc}
   */
  public function getSchemaTargetedSolrBranch($reset = FALSE) {
    $parts = explode('-', $this->getSchemaVersionString($reset));
    return $parts[3];
  }

  /**
   * {@inheritdoc}
   */
  public function isJumpStartConfigSet(bool $reset = FALSE): bool {
    $parts = explode('-', $this->getSchemaVersionString($reset));
    return (bool) ($parts[4] ?? 0);
  }

  /**
   * Gets data from a Solr endpoint using a given handler.
   *
   * @param string $handler
   *   The handler used for the API query.
   * @param bool $reset
   *   If TRUE the server will be asked regardless if a previous call is cached.
   *
   * @return array
   *   Response data with system information.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function getDataFromHandler($handler, $reset = FALSE) {
    static $previous_calls = [];

    $this->connect();

    // We keep the results in a state instead of a cache because we want to
    // access parts of this data even if Solr is temporarily not reachable and
    // caches have been cleared.
    $state_key = 'search_api_solr.endpoint.data';
    $state = \Drupal::state();
    $endpoint_data = $state->get($state_key);
    $server_uri = $this->getServerUri();

    if (!isset($previous_calls[$server_uri][$handler]) || !isset($endpoint_data[$server_uri][$handler]) || $reset) {
      // Don't retry multiple times in case of an exception.
      $previous_calls[$server_uri][$handler] = TRUE;

      if (!is_array($endpoint_data) || !isset($endpoint_data[$server_uri][$handler]) || $reset) {
        $query = $this->solr->createApi([
          'handler' => $handler,
          'version' => Request::API_V1,
        ]);
        $endpoint_data[$server_uri][$handler] = $this->execute($query)->getData();
        $state->set($state_key, $endpoint_data);
      }
    }

    return $endpoint_data[$server_uri][$handler];
  }

  /**
   * {@inheritdoc}
   */
  public function pingCore(array $options = []) {
    return $this->pingEndpoint(NULL, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function pingServer() {
    return $this->getServerInfo(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function pingEndpoint(?Endpoint $endpoint = NULL, array $options = []) {
    $this->connect();
    $this->useTimeout(self::QUERY_TIMEOUT, $endpoint);

    $query = $this->solr->createPing($options);

    try {
      $start = microtime(TRUE);
      $result = $this->solr->execute($query, $endpoint);
      if ($result->getResponse()->getStatusCode() == 200) {
        // Add 1 µs to the ping time so we never return 0.
        return (microtime(TRUE) - $start) + 1E-6;
      }
    }
    catch (HttpException $e) {
      // Don't handle the exception. Just return FALSE below.
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatsSummary() {
    $this->connect();
    $this->useTimeout();

    $summary = [
      '@pending_docs' => '',
      '@autocommit_time_seconds' => '',
      '@autocommit_time' => '',
      '@deletes_by_id' => '',
      '@deletes_by_query' => '',
      '@deletes_total' => '',
      '@schema_version' => '',
      '@core_name' => '',
      '@index_size' => '',
    ];

    $query = $this->solr->createPing();
    $query->setResponseWriter(Query::WT_PHPS);
    $query->setHandler('admin/mbeans?stats=true');
    $stats = $this->execute($query)->getData();
    if (!empty($stats)) {
      $solr_version = $this->getSolrVersion(TRUE);
      $max_time = -1;
      if (version_compare($solr_version, '7.0', '>=')) {
        $update_handler_stats = $stats['solr-mbeans']['UPDATE']['updateHandler']['stats'];
        $summary['@pending_docs'] = (int) $update_handler_stats['UPDATE.updateHandler.docsPending'];
        if (isset($update_handler_stats['UPDATE.updateHandler.softAutoCommitMaxTime'])) {
          $max_time = (int) $update_handler_stats['UPDATE.updateHandler.softAutoCommitMaxTime'];
        }
        $summary['@deletes_by_id'] = (int) $update_handler_stats['UPDATE.updateHandler.deletesById'];
        $summary['@deletes_by_query'] = (int) $update_handler_stats['UPDATE.updateHandler.deletesByQuery'];
        $summary['@core_name'] = $stats['solr-mbeans']['CORE']['core']['stats']['CORE.coreName'] ?? $this->t('No information available.');
        $summary['@index_size'] = $stats['solr-mbeans']['CORE']['core']['stats']['INDEX.size'] ?? $this->t('No information available.');
      }
      else {
        $update_handler_stats = $stats['solr-mbeans']['UPDATEHANDLER']['updateHandler']['stats'];
        $summary['@pending_docs'] = (int) $update_handler_stats['docsPending'];
        $max_time = (int) $update_handler_stats['autocommit maxTime'];
        $summary['@deletes_by_id'] = (int) $update_handler_stats['deletesById'];
        $summary['@deletes_by_query'] = (int) $update_handler_stats['deletesByQuery'];
        $summary['@core_name'] = $stats['solr-mbeans']['CORE']['core']['stats']['coreName'] ?? $this->t('No information available.');
        ;
        if (version_compare($solr_version, '6.4', '>=')) {
          // @see https://issues.apache.org/jira/browse/SOLR-3990
          $summary['@index_size'] = $stats['solr-mbeans']['CORE']['core']['stats']['size'] ?? $this->t('No information available.');
          ;
        }
        else {
          $summary['@index_size'] = $stats['solr-mbeans']['QUERYHANDLER']['/replication']['stats']['indexSize'] ?? $this->t('No information available.');
          ;
        }
      }

      $summary['@autocommit_time_seconds'] = $max_time / 1000;
      $summary['@autocommit_time'] = \Drupal::service('date.formatter')->formatInterval($max_time / 1000);
      $summary['@deletes_total'] = $summary['@deletes_by_id'] + $summary['@deletes_by_query'];
      $summary['@schema_version'] = $this->getSchemaVersionString(TRUE);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function coreRestGet($path, ?Endpoint $endpoint = NULL) {
    $this->useTimeout();
    return $this->restRequest($this->configuration['core'] . '/' . ltrim($path, '/'), Request::METHOD_GET, '', $endpoint);
  }

  /**
   * {@inheritdoc}
   */
  public function coreRestPost($path, $command_json = '', ?Endpoint $endpoint = NULL) {
    $this->useTimeout(self::INDEX_TIMEOUT);
    return $this->restRequest($this->configuration['core'] . '/' . ltrim($path, '/'), Request::METHOD_POST, $command_json, $endpoint);
  }

  /**
   * {@inheritdoc}
   */
  public function serverRestGet($path) {
    $this->useTimeout();
    return $this->restRequest($path);
  }

  /**
   * {@inheritdoc}
   */
  public function serverRestPost($path, $command_json = '') {
    $this->useTimeout(self::INDEX_TIMEOUT);
    return $this->restRequest($path, Request::METHOD_POST, $command_json);
  }

  /**
   * Sends a REST request to the Solr server endpoint and returns the result.
   *
   * @param string $handler
   *   The handler used for the API query.
   * @param string $method
   *   The HTTP request method.
   * @param string $command_json
   *   The command to send encoded as JSON.
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   The endpoint.
   *
   * @return array
   *   The decoded response.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function restRequest($handler, $method = Request::METHOD_GET, $command_json = '', ?Endpoint $endpoint = NULL) {
    $this->connect();
    $query = $this->solr->createApi([
      'handler' => $handler,
      'accept' => 'application/json',
      'contenttype' => 'application/json',
      'method' => $method,
      'rawdata' => (Request::METHOD_POST == $method ? $command_json : NULL),
    ]);

    $response = $this->execute($query, $endpoint);
    $output = $response->getData();
    // \Drupal::logger('search_api_solr')->info(print_r($output, true));.
    if (!empty($output['errors'])) {
      throw new SearchApiSolrException('Error trying to send a REST request.' .
        "\nError message(s):" . print_r($output['errors'], TRUE));
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdateQuery() {
    $this->connect();
    return $this->solr->createUpdate();
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectQuery() {
    $this->connect();
    return $this->solr->createSelect();
  }

  /**
   * {@inheritdoc}
   */
  public function getMoreLikeThisQuery() {
    $this->connect();
    return $this->solr->createMoreLikeThis();
  }

  /**
   * {@inheritdoc}
   */
  public function getTermsQuery() {
    $this->connect();
    return $this->solr->createTerms();
  }

  /**
   * {@inheritdoc}
   */
  public function getSpellcheckQuery() {
    $this->connect();
    return $this->solr->createSpellcheck();
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggesterQuery() {
    $this->connect();
    return $this->solr->createSuggester();
  }

  /**
   * {@inheritdoc}
   */
  public function getAutocompleteQuery() {
    $this->connect();
    $this->solr->registerQueryType('autocomplete', AutocompleteQuery::class);
    return $this->solr->createQuery('autocomplete');
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryHelper(?QueryInterface $query = NULL) {
    if ($query) {
      return $query->getHelper();
    }

    return \Drupal::service('solarium.query_helper');
  }

  /**
   * {@inheritdoc}
   */
  public function getExtractQuery() {
    $this->connect();
    return $this->solr->createExtract();
  }

  /**
   * {@inheritdoc}
   */
  public function getAnalysisQueryField(): Field {
    $this->connect();
    return $this->solr->createAnalysisField();
  }

  /**
   * Creates a CustomizeRequest object.
   *
   * @return \Solarium\Plugin\CustomizeRequest\CustomizeRequest|\Solarium\Core\Plugin\PluginInterface
   *   The Solarium CustomizeRequest object.
   */
  protected function customizeRequest() {
    $this->connect();
    return $this->solr->getPlugin('customizerequest');
  }

  /**
   * {@inheritdoc}
   */
  public function search(Query $query, ?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }

    $this->useTimeout(self::QUERY_TIMEOUT, $endpoint);

    // Use the 'postbigrequest' plugin if no specific http method is
    // configured. The plugin needs to be loaded before the request is
    // created.
    $plugin = NULL;
    if ($this->configuration['http_method'] === 'AUTO') {
      $plugin = $this->solr->getPlugin('postbigrequest');
    }

    // Use the manual method of creating a Solarium request so we can control
    // the HTTP method.
    $request = $this->solr->createRequest($query);

    // Set the configured HTTP method.
    if ($this->configuration['http_method'] === 'POST') {
      $request->setMethod(Request::METHOD_POST);
    }
    elseif ($this->configuration['http_method'] === 'GET') {
      $request->setMethod(Request::METHOD_GET);
    }

    $result = $this->executeRequest($request, $endpoint);

    if ($plugin) {
      $this->solr->removePlugin($plugin);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function createSearchResult(QueryInterface $query, Response $response) {
    return $this->solr->createResult($query, $response);
  }

  /**
   * {@inheritdoc}
   */
  public function update(UpdateQuery $query, ?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }
    // The default timeout is set for search queries. The configured timeout
    // might differ and needs to be set now because solarium doesn't
    // distinguish between these types.
    $this->useTimeout(self::INDEX_TIMEOUT, $endpoint);

    if ($this->configuration['commit_within']) {
      // Do a commitWithin since that is automatically a softCommit since Solr 4
      // and a delayed hard commit with Solr 3.4+.
      // By default we wait 1 second after the request arrived for solr to parse
      // the commit. This allows us to return to Drupal and let Solr handle what
      // it needs to handle.
      // @see http://wiki.apache.org/solr/NearRealtimeSearch
      /** @var \Solarium\Plugin\CustomizeRequest\CustomizeRequest $request */
      $request = $this->customizeRequest();
      if (!$request->getCustomization('commitWithin')) {
        $request->createCustomization('commitWithin')
          ->setType('param')
          ->setName('commitWithin')
          ->setValue($this->configuration['commit_within']);
      }
    }

    return $this->execute($query, $endpoint);
  }

  /**
   * {@inheritdoc}
   */
  public function autocomplete(AutocompleteQuery $query, ?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }

    $this->useTimeout(self::QUERY_TIMEOUT, $endpoint);

    // Use the 'postbigrequest' plugin if no specific http method is
    // configured. The plugin needs to be loaded before the request is
    // created.
    $plugin = NULL;
    if ($this->configuration['http_method'] === 'AUTO') {
      $plugin = $this->solr->getPlugin('postbigrequest');
    }

    $result = $this->execute($query, $endpoint);

    if ($plugin) {
      $this->solr->removePlugin($plugin);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function analyze(AbstractQuery $query, ?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }

    $this->useTimeout(self::QUERY_TIMEOUT, $endpoint);

    return $this->execute($query, $endpoint);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(QueryInterface $query, ?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }

    try {
      return $this->solr->execute($query, $endpoint);
    }
    catch (HttpException $e) {
      $this->handleHttpException($e, $endpoint);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeRequest(Request $request, ?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }

    try {
      return $this->solr->executeRequest($request, $endpoint);
    }
    catch (HttpException $e) {
      $this->handleHttpException($e, $endpoint);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fireAndForget(QueryInterface $query, ?Endpoint $endpoint = NULL): void {
    $this->connect();
    $plugin = $this->solr->getPlugin('nowaitforresponserequest');
    $this->execute($query, $endpoint);
    $this->solr->removePlugin($plugin);
  }


  /**
   * Converts a HttpException in an easier to read SearchApiSolrException.
   *
   * Connectors must not overwrite this function. Otherwise support requests are
   * hard to handle in the issue queue. If you want to extend this function and
   * add more sophisticated error handling, please contribute a patch to
   * the search_api_solr project on drupal.org.
   *
   * @param \Solarium\Exception\HttpException $e
   *   The HttpException object.
   * @param \Solarium\Core\Client\Endpoint $endpoint
   *   The Solarium endpoint.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  final protected function handleHttpException(HttpException $e, Endpoint $endpoint) {
    $body = $e->getBody();
    $response_code = (int) $e->getCode();
    switch ((string) $response_code) {
      // Bad Request.
      case '400':
        $description = 'bad request';
        $response_decoded = Json::decode($body);
        if ($response_decoded && isset($response_decoded['error'])) {
          $body = $response_decoded['error']['msg'] ?? $body;
        }
        break;

      // Not Found.
      case '404':
        $description = 'not found';
        break;

      // Unauthorized.
      case '401':
        // Forbidden.
      case '403':
        $description = 'access denied';
        break;

      // Internal Server Error.
      case '500':
      case '0':
        $description = 'internal Solr server error';
        break;

      default:
        $description = 'unreachable or returned unexpected response code';
    }
    throw new SearchApiSolrException(sprintf('Solr endpoint %s %s (code: %d, body: %s, message: %s).', $this->getEndpointUri($endpoint), $description, $response_code, $body, $e->getMessage()), $response_code, $e);
  }

  /**
   * Gets a string representation of the endpoint URI.
   *
   * Could be overwritten by other connectors according to their needs.
   *
   * @param \Solarium\Core\Client\Endpoint $endpoint
   *   The endpoint.
   *
   * @return string
   *   Returns the server uri, required for non core/collection specific
   *   requests.
   */
  protected function getEndpointUri(Endpoint $endpoint): string {
    return $endpoint->getServerUri();
  }

  /**
   * {@inheritdoc}
   */
  public function optimize(?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }
    // The default timeout is set for search queries. The configured timeout
    // might differ and needs to be set now because solarium doesn't
    // distinguish between these types.
    $this->useTimeout(self::OPTIMIZE_TIMEOUT, $endpoint);

    $update_query = $this->solr->createUpdate();
    $update_query->addOptimize(TRUE, FALSE);

    $this->execute($update_query, $endpoint);
  }

  /**
   * {@inheritdoc}
   */
  public function adjustTimeout(int $seconds, string $timeout = self::QUERY_TIMEOUT, ?Endpoint &$endpoint = NULL): int {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }

    $previous_timeout = $endpoint->getOption($timeout);
    $options = $endpoint->getOptions();
    $options[$timeout] = $seconds;
    $endpoint = new Endpoint($options);
    return $previous_timeout;
  }

  /**
   * Set the timeout.
   *
   * @param string $timeout
   *   (optional) The configured timeout to use. Default is self::QUERY_TIMEOUT.
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint object.
   *
   * @return mixed
   */
  protected function useTimeout(string $timeout = self::QUERY_TIMEOUT, ?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }
    $seconds = $endpoint->getOption($timeout);
    if ($seconds) {
      $adapter = $this->solr->getAdapter();
      if ($adapter instanceof TimeoutAwareInterface) {
        $adapter->setTimeout($seconds);
      }
      else {
        $this->getLogger()->warning('The function SolrConnectorPluginBase::useTimeout() has no effect because you use a HTTP adapter that is not implementing TimeoutAwareInterface. You need to adjust your SolrConnector accordingly.');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeout(?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }

    return $endpoint->getOption(self::QUERY_TIMEOUT);
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexTimeout(?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }

    return $endpoint->getOption(self::INDEX_TIMEOUT);
  }

  /**
   * {@inheritdoc}
   */
  public function getOptimizeTimeout(?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }

    return $endpoint->getOption(self::OPTIMIZE_TIMEOUT);
  }

  /**
   * {@inheritdoc}
   */
  public function getFinalizeTimeout(?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }

    return $endpoint->getOption(self::FINALIZE_TIMEOUT);
  }

  /**
   * {@inheritdoc}
   */
  public function extract(QueryInterface $query, ?Endpoint $endpoint = NULL) {
    $this->useTimeout(self::INDEX_TIMEOUT, $endpoint);
    return $this->execute($query, $endpoint);
  }

  /**
   * {@inheritdoc}
   */
  public function getContentFromExtractResult(ExtractResult $result, $filepath) {
    $array_data = $result->getData();

    if (isset($array_data[basename($filepath)])) {
      return $array_data[basename($filepath)];
    }

    // In case of file hosted on s3fs array_data has full file path as key.
    // Example:
    // https://[s3-bucket.s3-domain.com]/s3fs-public/document/[document-name.pdf?VersionId=123]
    if (isset($array_data[$filepath])) {
      return $array_data[$filepath];
    }

    // In most (or every) cases when an error happens we won't reach that point,
    // because a Solr exception is already passed through. Anyway, this
    // exception will be thrown if the solarium library surprises us again. ;-)
    throw new SearchApiSolrException('Unable to find extracted files within the Solr response body.');
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoint($key = 'search_api_solr') {
    $this->connect();
    return $this->solr->getEndpoint($key);
  }

  /**
   * {@inheritdoc}
   */
  public function createEndpoint(string $key, array $additional_configuration = []) {
    $this->connect();
    $configuration = [
      'key' => $key,
      self::QUERY_TIMEOUT => $this->configuration['timeout'],
    ] + $additional_configuration + $this->configuration;
    unset($configuration['timeout']);

    return $this->solr->createEndpoint($configuration, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getFile($file = NULL) {
    $this->connect();
    $query = $this->solr->createApi([
      'handler' => $this->configuration['core'] . '/admin/file',
    ]);
    if ($file) {
      $query->addParam('file', $file);
    }

    return $this->execute($query)->getResponse();
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep(): array {
    // It's safe to unset the solr client completely before serialization
    // because connect() will set it up again correctly after deserialization.
    unset($this->solr);
    return parent::__sleep();
  }

  /**
   * {@inheritdoc}
   */
  public function alterConfigFiles(array &$files, string $lucene_match_version, string $server_id = '') {
    if (!empty($this->configuration['jmx']) && version_compare($this->getSolrVersion(), '9.0', '<')) {
      $files['solrconfig_extra.xml'] .= "<jmx />\n";
    }

    if (!empty($this->configuration['jts'])) {
      $jts_arguments = 'spatialContextFactory="org.locationtech.spatial4j.context.jts.JtsSpatialContextFactory" autoIndex="true" validationRule="repairBuffer0"';
      $files['schema.xml'] = preg_replace("#\sclass\s*=\s*\"solr\.SpatialRecursivePrefixTreeFieldType\"#ms", "\\0\n        " . $jts_arguments, $files['schema.xml']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterConfigZip(ZipStream $zip, string $lucene_match_version, string $server_id = '') {
  }

}
