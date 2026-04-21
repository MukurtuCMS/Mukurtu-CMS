<?php

namespace Drupal\search_api_solr\Plugin\search_api\backend;

use Composer\InstalledVersions;
use Composer\Semver\Comparator;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\search_api\LoggerTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinitionInterface;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\search_api\Backend\BackendPluginBase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValue;
use Drupal\search_api\Plugin\search_api\processor\Property\AggregatedFieldProperty;
use Drupal\search_api\Processor\ProcessorInterface;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\Query\ConditionGroup;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\ConditionInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\DataTypeHelperInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api\Utility\Utility as SearchApiUtility;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Suggestion\SuggestionFactory;
use Drupal\search_api_solr\Entity\SolrFieldType;
use Drupal\search_api_solr\Event\PostConvertedQueryEvent;
use Drupal\search_api_solr\Event\PostCreateIndexDocumentEvent;
use Drupal\search_api_solr\Event\PostCreateIndexDocumentsEvent;
use Drupal\search_api_solr\Event\PostExtractFacetsEvent;
use Drupal\search_api_solr\Event\PostExtractResultsEvent;
use Drupal\search_api_solr\Event\PostFieldMappingEvent;
use Drupal\search_api_solr\Event\PostIndexFinalizationEvent;
use Drupal\search_api_solr\Event\PostSetFacetsEvent;
use Drupal\search_api_solr\Event\PreAddLanguageFallbackFieldEvent;
use Drupal\search_api_solr\Event\PreAutocompleteTermsQueryEvent;
use Drupal\search_api_solr\Event\PreCreateIndexDocumentEvent;
use Drupal\search_api_solr\Event\PreExtractFacetsEvent;
use Drupal\search_api_solr\Event\PreIndexFinalizationEvent;
use Drupal\search_api_solr\Event\PreQueryEvent;
use Drupal\search_api_solr\Event\PreSetFacetsEvent;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\Solarium\Autocomplete\Query as AutocompleteQuery;
use Drupal\search_api_solr\Solarium\Result\StreamDocument;
use Drupal\search_api_solr\SolrAutocompleteBackendTrait;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr\SolrCloudConnectorInterface;
use Drupal\search_api_solr\SolrConnector\SolrConnectorPluginManager;
use Drupal\search_api_solr\SolrConnectorInterface;
use Drupal\search_api_solr\SolrProcessorInterface;
use Drupal\search_api_solr\SolrSpellcheckBackendTrait;
use Drupal\search_api_solr\Utility\SolrCommitTrait;
use Drupal\search_api_solr\Utility\Utility;
use Laminas\Stdlib\ArrayUtils;
use Solarium\Component\ComponentAwareQueryInterface;
use Solarium\Core\Client\Endpoint;
use Solarium\Core\Client\Response;
use Solarium\Core\Query\Helper;
use Solarium\Core\Query\QueryInterface as SolariumQueryInterface;
use Solarium\Core\Query\Result\ResultInterface;
use Solarium\Exception\ExceptionInterface;
use Solarium\Exception\OutOfBoundsException;
use Solarium\Exception\StreamException;
use Solarium\Exception\UnexpectedValueException;
use Solarium\QueryType\Extract\Query as ExtractQuery;
use Solarium\QueryType\Select\Query\FilterQuery;
use Solarium\QueryType\Select\Query\Query;
use Solarium\QueryType\Select\Result\Result;
use Solarium\QueryType\Stream\ExpressionBuilder;
use Solarium\QueryType\Update\Query\Document;
use Solarium\QueryType\Update\Query\Query as UpdateQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Apache Solr backend for search api.
 *
 * @SearchApiBackend(
 *   id = "search_api_solr",
 *   label = @Translation("Solr"),
 *   description = @Translation("Index items using an Apache Solr search server.")
 * )
 */
class SearchApiSolrBackend extends BackendPluginBase implements SolrBackendInterface, PluginFormInterface {

  use PluginFormTrait {
    PluginFormTrait::submitConfigurationForm as traitSubmitConfigurationForm;
  }

  use PluginDependencyTrait;

  use SolrCommitTrait;

  use SolrAutocompleteBackendTrait;

  use SolrSpellcheckBackendTrait;

  use StringTranslationTrait;

  use LoggerTrait;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * A config object for 'search_api_solr.settings'.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $searchApiSolrSettings;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The backend plugin manager.
   *
   * @var \Drupal\search_api_solr\SolrConnector\SolrConnectorPluginManager
   */
  protected $solrConnectorPluginManager;

  /**
   * The Solr connector.
   *
   * @var \Drupal\search_api_solr\SolrConnectorInterface
   */
  protected $solrConnector;

  /**
   * The fields helper.
   *
   * @var \Drupal\search_api\Utility\FieldsHelperInterface
   */
  protected $fieldsHelper;

  /**
   * The data type helper.
   *
   * @var \Drupal\search_api\Utility\DataTypeHelper|null
   */
  protected $dataTypeHelper;

  /**
   * The Solarium query helper.
   *
   * @var \Solarium\Core\Query\Helper
   */
  protected $queryHelper;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The state storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The locking layer instance.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ModuleHandlerInterface $module_handler, Config $search_api_solr_settings, LanguageManagerInterface $language_manager, SolrConnectorPluginManager $solr_connector_plugin_manager, FieldsHelperInterface $fields_helper, DataTypeHelperInterface $dataTypeHelper, Helper $query_helper, EntityTypeManagerInterface $entityTypeManager, EventDispatcherInterface $eventDispatcher, TimeInterface $time, StateInterface $state, MessengerInterface $messenger, LockBackendInterface $lock, ModuleExtensionList $module_extension_list) {
    $this->moduleHandler = $module_handler;
    $this->searchApiSolrSettings = $search_api_solr_settings;
    $this->languageManager = $language_manager;
    $this->solrConnectorPluginManager = $solr_connector_plugin_manager;
    $this->fieldsHelper = $fields_helper;
    $this->dataTypeHelper = $dataTypeHelper;
    $this->queryHelper = $query_helper;
    $this->entityTypeManager = $entityTypeManager;
    $this->eventDispatcher = $eventDispatcher;
    $this->time = $time;
    $this->state = $state;
    $this->messenger = $messenger;
    $this->lock = $lock;
    $this->moduleExtensionList = $module_extension_list;

    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
      $container->get('config.factory')->get('search_api_solr.settings'),
      $container->get('language_manager'),
      $container->get('plugin.manager.search_api_solr.connector'),
      $container->get('search_api.fields_helper'),
      $container->get('search_api.data_type_helper'),
      $container->get('solarium.query_helper'),
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('datetime.time'),
      $container->get('state'),
      $container->get('messenger'),
      $container->get('lock'),
      $container->get('extension.list.module')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPreferredSchemaVersion(): string {
    $installed_version = InstalledVersions::getPrettyVersion('drupal/search_api_solr');

    if (!preg_match('/^\d+\.\d+\.\d+$/', $installed_version, $matches)) {
      return self::SEARCH_API_SOLR_MIN_SCHEMA_VERSION;
    }

    return $installed_version;
  }

  /**
   * {@inheritdoc}
   */
  public function getMinimalRequiredSchemaVersion(): string {
    return self::SEARCH_API_SOLR_MIN_SCHEMA_VERSION;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'retrieve_data' => FALSE,
      'highlight_data' => FALSE,
      'site_hash' => FALSE,
      'server_prefix' => '',
      'domain' => 'generic',
      'environment' => 'default',
      // Set the default for new servers to NULL to force "safe" un-selected
      // radios. @see https://www.drupal.org/node/2820244
      'connector' => NULL,
      'connector_config' => [],
      'optimize' => FALSE,
      'fallback_multiple' => FALSE,
      // 10 is Solr's default limit if rows is not set.
      'rows' => 10,
      'index_single_documents_fallback_count' => 10,
      'index_empty_text_fields' => FALSE,
      'suppress_missing_languages' => FALSE,
    ];
  }

  /**
   * Add the default configuration for config-set generation.
   *
   * DefaultConfiguration() is called on any search. Loading the defaults only
   * required for config-set generation is an overhead that isn't required.
   */
  protected function addDefaultConfigurationForConfigGeneration() {
    if (!isset($this->configuration['disabled_field_types'])) {
      /** @var \Drupal\search_api_solr\Controller\AbstractSolrEntityListBuilder $solr_field_type_list_builder */
      $solr_field_type_list_builder = $this->entityTypeManager->getListBuilder('solr_field_type');
      $this->configuration['disabled_field_types'] = array_keys($solr_field_type_list_builder->getAllNotRecommendedEntities());
    }

    if (!isset($this->configuration['disabled_caches'])) {
      /** @var \Drupal\search_api_solr\Controller\AbstractSolrEntityListBuilder $solr_cache_list_builder */
      $solr_cache_list_builder = $this->entityTypeManager->getListBuilder('solr_cache');
      $this->configuration['disabled_caches'] = array_keys($solr_cache_list_builder->getAllNotRecommendedEntities());
    }

    if (!isset($this->configuration['disabled_request_handlers'])) {
      /** @var \Drupal\search_api_solr\Controller\AbstractSolrEntityListBuilder $solr_request_handler_list_builder */
      $solr_request_handler_list_builder = $this->entityTypeManager->getListBuilder('solr_request_handler');
      $this->configuration['disabled_request_handlers'] = array_keys($solr_request_handler_list_builder->getAllNotRecommendedEntities());
    }

    if (!isset($this->configuration['disabled_request_dispatchers'])) {
      /** @var \Drupal\search_api_solr\Controller\AbstractSolrEntityListBuilder $solr_request_dispatcher_list_builder */
      $solr_request_dispatcher_list_builder = $this->entityTypeManager->getListBuilder('solr_request_dispatcher');
      $this->configuration['disabled_request_dispatchers'] = array_keys($solr_request_dispatcher_list_builder->getAllNotRecommendedEntities());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $configuration['retrieve_data'] = (bool) ($configuration['retrieve_data'] ?? FALSE);
    $configuration['highlight_data'] = (bool) ($configuration['highlight_data'] ?? FALSE);
    $configuration['site_hash'] = (bool) ($configuration['site_hash'] ?? FALSE);
    $configuration['optimize'] = (bool) ($configuration['optimize'] ?? FALSE);
    $configuration['fallback_multiple'] = (bool) ($configuration['fallback_multiple'] ?? FALSE);
    $configuration['rows'] = (int) ($configuration['rows'] ?? 10);
    $configuration['index_single_documents_fallback_count'] = (int) ($configuration['index_single_documents_fallback_count'] ?? 10);
    $configuration['index_empty_text_fields'] = (bool) ($configuration['index_empty_text_fields'] ?? FALSE);
    $configuration['suppress_missing_languages'] = (bool) ($configuration['suppress_missing_languages'] ?? FALSE);

    parent::setConfiguration($configuration);

    // Update the configuration of the Solr connector as well by replacing it by
    // a new instance with the latest configuration.
    $this->solrConnector = NULL;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $solr_connector_options = $this->getSolrConnectorOptions();
    $form['connector'] = [
      '#type' => 'radios',
      '#title' => $this->t('Solr Connector'),
      '#description' => $this->t('Choose a connector to use for this Solr server.'),
      '#options' => $solr_connector_options,
      '#default_value' => $this->configuration['connector'],
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [get_class($this), 'buildAjaxSolrConnectorConfigForm'],
        'wrapper' => 'search-api-solr-connector-config-form',
        'method' => 'replaceWith',
        'effect' => 'fade',
      ],
    ];

    $this->buildConnectorConfigForm($form, $form_state);

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced'),
    ];

    $form['advanced']['rows'] = [
      '#type' => 'number',
      '#min' => 0,
      // The max rows that could be returned by Solr are the max 32bit integer.
      '#max' => 2147483630,
      '#title' => $this->t('Default result rows'),
      '#description' => $this->t('Solr always requires to limit the search results. This default value will be set if the Search API query itself is not limited. 2147483630 is the theoretical maximum since the result pointer is an integer. But be careful! Especially in Solr Cloud setups too high values might cause an OutOfMemoryException because Solr reserves this rows limit per shard for sorting the combined result. This sum must not exceed the maximum integer value! And even if there is no exception any too high memory consumption per query on your server is a bad thing in general.'),
      '#default_value' => $this->configuration['rows'] ?: 10,
      '#required' => TRUE,
    ];

    $form['advanced']['index_single_documents_fallback_count'] = [
      '#type' => 'number',
      '#min' => 0,
      '#max' => 100,
      '#title' => $this->t('Index single documents fallback count'),
      '#description' => $this->t('In case of an erroneous document that causes a Solr exception, the entire batch of documents will not be indexed. In order to identify the erroneous document and to keep indexing the others, the indexing process falls back to index documents one by one instead of a batch. This setting limits the amount of single documents to be indexed per batch to avoid too many commits that might slow doen the Solr server. Setting the value to "0" disables the fallback.'),
      '#default_value' => $this->configuration['index_single_documents_fallback_count'] ?: 10,
      '#required' => TRUE,
    ];

    $form['advanced']['index_empty_text_fields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Index empty Fulltext fields'),
      '#description' => $this->t('By default, empty fields of type fulltext will be removed from the indexed document. In some cases like multilingual searches across different language-specific fields that might impact the IDF similarity and therefore the scoring in an unwanted way. By indexing a dummy value instead you can "normalize" the IDF by ensuring the same number of total documents for each field (per language).'),
      '#default_value' => $this->configuration['index_empty_text_fields'],
    ];

    $form['advanced']['retrieve_data'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Retrieve result data from Solr'),
      '#description' => $this->t('When checked, result data will be retrieved directly from the Solr server. This might make item loads unnecessary. Only indexed fields can be retrieved. Note also that the returned field data might not always be correct, due to preprocessing and caching issues.'),
      '#default_value' => $this->configuration['retrieve_data'],
    ];

    $form['advanced']['highlight_data'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Retrieve highlighted snippets'),
      '#description' => $this->t('Return a highlighted version of the indexed fulltext fields. These will be used by the "Highlighting Processor" directly instead of applying its own PHP algorithm.'),
      '#default_value' => $this->configuration['highlight_data'],
    ];

    $form['advanced']['suppress_missing_languages'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Suppress warnings about missing language-specific field types'),
      '#description' => $this->t("By default, fields of type fulltext will be indexed using language-specific Solr field types. But Search API Solr doesn't provide such a language-specific filed type configuration for any language supported by Drupal. In this case the language-undefined field type will be used as fall-back. Or in case of language variations like 'de-at' the 'de' will be used as fallback. But in both cases a warning will be shown on the status report page to inform about this fact. By activating this chackbox you can suppress these warnings permanently."),
      '#default_value' => $this->configuration['suppress_missing_languages'],
    ];

    $form['advanced']['fallback_multiple'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Fallback to multiValued field types'),
      '#description' => $this->t('If the cardinality of a field or a property could not be detected (due to incomplete custom module implementations), a single value field type will be used within the Solr index for better performance. If this leads to "multiple values encountered for non multiValued field" exceptions you can set this option to change the fallback to multiValued.'),
      '#default_value' => $this->configuration['fallback_multiple'],
    ];

    $form['advanced']['server_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('All index prefix'),
      '#description' => $this->t("By default, the index ID in the Solr server is the same as the index's machine name in Drupal. This setting will let you specify an additional prefix. Only use alphanumeric characters and underscores. Since changing the prefix makes the currently indexed data inaccessible, you should not change this variable when no data is indexed."),
      '#default_value' => $this->configuration['server_prefix'],
    ];

    $domains = SolrFieldType::getAvailableDomains();
    $form['advanced']['domain'] = [
      '#type' => 'select',
      '#options' => array_combine($domains, $domains),
      '#title' => $this->t('Targeted content domain'),
      '#description' => $this->t('For example "UltraBot3000" would be indexed as "Ultra" "Bot" "3000" in a generic domain, "CYP2D6" has to stay like it is in a scientific domain.'),
      '#default_value' => $this->configuration['domain'] ?? 'generic',
    ];

    $environments = Utility::getAvailableEnvironments();
    $form['advanced']['environment'] = [
      '#type' => 'select',
      '#options' => array_combine($environments, $environments),
      '#title' => $this->t('Targeted environment'),
      '#description' => $this->t('For example "dev", "stage" or "prod".'),
      '#default_value' => $this->configuration['environment'] ?? 'default',
    ];

    $form['advanced']['i_know_what_i_do'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Optimize the Solr index'),
      '#description' => $this->t('Optimize the Solr index once a day. Even if this option "sounds good", think twice before activating it! For most Solr setups it\'s recommended to NOT enable this feature!'),
      '#default_value' => $this->configuration['optimize'],
    ];

    $form['advanced']['optimize'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Yes, I know what I'm doing and want to enable a daily optimization!"),
      '#default_value' => $this->configuration['optimize'],
      '#states' => [
        'invisible' => [':input[name="backend_config[advanced][i_know_what_i_do]"]' => ['checked' => FALSE]],
      ],
    ];

    $form['multisite'] = [
      '#type' => 'details',
      '#title' => $this->t('Multi-site compatibility'),
      '#description' => $this->t("By default a single Solr backend based Search API server is able to index the data of multiple Drupal sites. But this is an expert-only and dangerous feature that mainly exists for backward compatibility. If you really index multiple sites in one index and don't activate 'Retrieve results for this site only' below you have to ensure that you enable 'Retrieve result data from Solr'! Otherwise it could lead to any kind of errors!"),
    ];
    $description = $this->t("Automatically filter all searches to only retrieve results from this Drupal site. The default and intended behavior is to display results from all sites. WARNING: Enabling this filter might break features like autocomplete, spell checking or suggesters!");
    $form['multisite']['site_hash'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Retrieve results for this site only'),
      '#description' => $description,
      '#default_value' => $this->configuration['site_hash'],
    ];

    $form['disabled_field_types'] = [
      '#type' => 'value',
      '#value' => $this->getDisabledFieldTypes(),
    ];

    $form['disabled_caches'] = [
      '#type' => 'value',
      '#value' => $this->getDisabledCaches(),
    ];

    $form['disabled_request_handlers'] = [
      '#type' => 'value',
      '#value' => $this->getDisabledRequestHandlers(),
    ];

    $form['disabled_request_dispatchers'] = [
      '#type' => 'value',
      '#value' => $this->getDisabledRequestDispatchers(),
    ];

    return $form;
  }

  /**
   * Returns all available backend plugins, as an options list.
   *
   * @return string[]
   *   An associative array mapping backend plugin IDs to their (HTML-escaped)
   *   labels.
   */
  protected function getSolrConnectorOptions() {
    $options = [];
    foreach ($this->solrConnectorPluginManager->getDefinitions() as $plugin_id => $plugin_definition) {
      $options[$plugin_id] = Html::escape($plugin_definition['label']);
    }
    return $options;
  }

  /**
   * Builds the backend-specific configuration form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function buildConnectorConfigForm(array &$form, FormStateInterface $form_state) {
    $form['connector_config'] = [];

    $connector_id = $this->configuration['connector'];
    if ($connector_id) {
      $connector = $this->solrConnectorPluginManager->createInstance($connector_id, $this->configuration['connector_config']);
      if ($connector instanceof PluginFormInterface) {
        $form_state->set('connector', $connector_id);
        if ($form_state->isRebuilding()) {
          $this->messenger->addWarning($this->t('Please configure the selected Solr connector.'));
        }
        // Attach the Solr connector plugin configuration form.
        $connector_form_state = SubformState::createForSubform($form['connector_config'], $form, $form_state);
        $form['connector_config'] = $connector->buildConfigurationForm($form['connector_config'], $connector_form_state);

        // Modify the backend plugin configuration container element.
        $form['connector_config']['#type'] = 'details';
        $form['connector_config']['#title'] = $this->t('Configure %plugin Solr connector', ['%plugin' => $connector->label()]);
        $form['connector_config']['#description'] = $connector->getDescription();
        $form['connector_config']['#open'] = TRUE;
      }
    }
    $form['connector_config'] += ['#type' => 'container'];
    $form['connector_config']['#attributes'] = [
      'id' => 'search-api-solr-connector-config-form',
    ];
    $form['connector_config']['#tree'] = TRUE;

  }

  /**
   * Handles switching the selected Solr connector plugin.
   */
  public static function buildAjaxSolrConnectorConfigForm(array $form, FormStateInterface $form_state) {
    // The work is already done in form(), where we rebuild the entity according
    // to the current form values and then create the backend configuration form
    // based on that. So we just need to return the relevant part of the form
    // here.
    return $form['backend_config']['connector_config'];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Check if the Solr connector plugin changed.
    if ($form_state->getValue('connector') != $form_state->get('connector')) {
      $new_connector = $this->solrConnectorPluginManager->createInstance($form_state->getValue('connector'));
      if ($new_connector instanceof PluginFormInterface) {
        $form_state->setRebuild();
      }
      else {
        $form_state->setError($form['connector'], $this->t('The connector could not be activated.'));
      }
    }
    // Check before loading the backend plugin so we don't throw an exception.
    else {
      $this->configuration['connector'] = $form_state->get('connector');
      $connector = $this->getSolrConnector();
      if ($connector instanceof PluginFormInterface) {
        $connector_form_state = SubformState::createForSubform($form['connector_config'], $form, $form_state);
        $connector->validateConfigurationForm($form['connector_config'], $connector_form_state);
      }
      else {
        $form_state->setError($form['connector'], $this->t('The connector could not be activated.'));
      }
    }

    // @todo If any Solr Document datasource is selected, retrieve_data must be set.
    // @todo If solr_document is the only datasource, skip_schema_check must be set.
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['connector'] = $form_state->get('connector');
    $connector = $this->getSolrConnector();
    if ($connector instanceof PluginFormInterface) {
      $connector_form_state = SubformState::createForSubform($form['connector_config'], $form, $form_state);
      $connector->submitConfigurationForm($form['connector_config'], $connector_form_state);
      // Overwrite the form values with type casted values.
      // @see \Drupal\search_api_solr\SolrConnector\SolrConnectorPluginBase::setConfiguration()
      $form_state->setValue('connector_config', $connector->getConfiguration());
    }

    $values = $form_state->getValues();
    // Since the form is nested into another, we can't simply use #parents for
    // doing this array restructuring magic. (At least not without creating an
    // unnecessary dependency on internal implementation.)
    $values += $values['advanced'];
    $values += $values['multisite'];
    $values['optimize'] &= $values['i_know_what_i_do'];

    foreach ($values as $key => $value) {
      $form_state->setValue($key, $value);
    }

    // Clean-up the form to avoid redundant entries in the stored configuration.
    $form_state->unsetValue('advanced');
    $form_state->unsetValue('multisite');
    // The server description is a #type item element, which means it has a
    // value, do not save it.
    $form_state->unsetValue('server_description');
    $form_state->unsetValue('i_know_what_i_do');

    $this->traitSubmitConfigurationForm($form, $form_state);

    // Delete cached endpoint data.
    $this->state->delete('search_api_solr.endpoint.data');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getSolrConnector() {
    if (!$this->solrConnector) {
      if (!($this->solrConnector = $this->solrConnectorPluginManager->createInstance($this->configuration['connector'], $this->configuration['connector_config']))) {
        throw new SearchApiException("The Solr Connector with ID '$this->configuration['connector']' could not be retrieved.");
      }
    }

    return $this->solrConnector->setEventDispatcher($this->eventDispatcher);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function isAvailable() {
    try {
      $connector = $this->getSolrConnector();
      $server_available = $connector->pingServer() !== FALSE;
      $core_available = $connector->pingCore() !== FALSE;
      if ($server_available && !$core_available) {
        $this->messenger
          ->addWarning($this->t('Server %server is reachable but the configured %core is not available.', [
            '%server' => $this->getServer()->label(),
            '%core' => $connector->isCloud() ? 'collection' : 'core',
          ]));
      }
      return $server_available && $core_available;
    }
    catch (\Exception $e) {
      $this->logException($e);
    }
    // If any exception was thrown we consider the server to be unavailable.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getSupportedFeatures() {
    $features = [
      'search_api_facets',
      'search_api_facets_operator_or',
      'search_api_granular',
      'search_api_mlt',
      'search_api_random_sort',
      'search_api_spellcheck',
    ];

    if ($this->moduleHandler->moduleExists('search_api_solr_autocomplete')) {
      $features[] = 'search_api_autocomplete';
    }

    // @see https://lucene.apache.org/solr/guide/7_6/result-grouping.html#distributed-result-grouping-caveats
    if (!$this->getSolrConnector()->isCloud()) {
      $features[] = 'search_api_grouping';
    }

    return $features;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDataType($type) {
    static $custom_codes = [];

    if (str_starts_with($type, 'solr_text_custom')) {
      [, $custom_code] = explode(':', $type);
      if (empty($custom_codes)) {
        $custom_codes = SolrFieldType::getAvailableCustomCodes();
      }
      return in_array($custom_code, $custom_codes);
    }

    $built_in_support = [
      'location',
      'rpt',
      'solr_date_range',
      'solr_string_storage',
      'solr_string_docvalues',
      'solr_text_omit_norms',
      'solr_text_suggester',
      'solr_text_spellcheck',
      'solr_text_unstemmed',
      'solr_text_wstoken',
      'solr_text_custom',
      'solr_text_custom_omit_norms',
    ];
    if (in_array($type, $built_in_support)) {
      return TRUE;
    }

    // @see search_api_solr_hook_search_api_data_type_info()
    $type_info = Utility::getDataTypeInfo($type);
    return !empty($type_info['prefix']);
  }

  /**
   * {@inheritdoc}
   */
  public function getDiscouragedProcessors() {
    return [
      'ignorecase',
      // https://www.drupal.org/project/snowball_stemmer
      'snowball_stemmer',
      'stemmer',
      'stopwords',
      'tokenizer',
      'transliteration',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function viewSettings() {
    /** @var \Drupal\search_api_solr\Plugin\SolrConnector\StandardSolrCloudConnector $connector */
    $connector = $this->getSolrConnector();
    $cloud = $connector instanceof SolrCloudConnectorInterface;

    $info[] = [
      'label' => $this->t('Solr connector plugin'),
      'info' => $connector->label(),
    ];

    $info[] = [
      'label' => $this->t('Solr server URI'),
      'info' => $connector->getServerLink(),
    ];

    if ($cloud) {
      $info[] = [
        'label' => $this->t('Solr collection URI'),
        'info' => $connector->getCollectionLink(),
      ];
    }
    else {
      $info[] = [
        'label' => $this->t('Solr core URI'),
        'info' => $connector->getCoreLink(),
      ];
    }

    // Add connector-specific information.
    $info = array_merge($info, $connector->viewSettings());

    if ($this->server->status()) {
      // If the server is enabled, check whether Solr can be reached.
      try {
        $ping_server = $connector->pingServer();
      }
      catch (\Exception $e) {
        $ping_server = FALSE;
      }
      if ($ping_server) {
        $msg = $this->t('The Solr server could be reached.');
      }
      else {
        $msg = $this->t('The Solr server could not be reached or is protected by your service provider.');
        $this->messenger->addWarning($msg);
      }
      $info[] = [
        'label' => $this->t('Server Connection'),
        'info' => $msg,
        'status' => $ping_server ? 'ok' : 'error',
      ];

      try {
        $ping = $connector->pingCore();
      }
      catch (\Exception $e) {
        $ping = FALSE;
      }
      if ($ping) {
        $msg = $this->t('The Solr @core could be accessed (latency: @millisecs ms).', [
          '@core' => $cloud ? 'collection' : 'core',
          '@millisecs' => $ping * 1000,
        ]);
      }
      else {
        $msg = $this->t('The Solr @core could not be accessed. Further data is therefore unavailable.', [
          '@core' => $cloud ? 'collection' : 'core',
        ]);
      }
      $info[] = [
        'label' => $cloud ? $this->t('Collection Connection') : $this->t('Core Connection'),
        'info' => $msg,
        'status' => $ping ? 'ok' : 'error',
      ];

      $info[] = [
        'label' => $this->t('Minimal required schema version'),
        'info' => $this->getMinimalRequiredSchemaVersion(),
      ];

      $info[] = [
        'label' => $this->t('Preferred schema version'),
        'info' => $this->getPreferredSchemaVersion(),
      ];

      $version = $connector->getSolrVersion();
      $info[] = [
        'label' => $this->t('Configured Solr Version'),
        'info' => $version,
        'status' => version_compare($version, '0.0.0', '>') ? 'ok' : 'error',
      ];

      if ($ping_server || $ping) {
        $version = $connector->getSolrVersion(TRUE);
        $info[] = [
          'label' => $this->t('Detected Solr Version'),
          'info' => $version,
          'status' => 'ok',
        ];
        if (version_compare($connector->getSolrVersion(), '9.8.0', '>=')) {
          $this->messenger()->addWarning($this->t('"lib" directives in solrconfig.xml are deprecated and will be removed in Solr 10.0. Ensure to load the required modules in your Solr 9.8 or higher server. One way is to set the SOLR_MODULES environment variable to include the modules required by Search API Solr per default: SOLR_MODULES="extraction,langid,ltr,analysis-extras".'));
        }

        try {
          $endpoints[0] = $connector->getEndpoint();
          $endpoints_queried = [];

          foreach ($this->getServer()->getIndexes() as $index) {
            $endpoints[$index->id()] = $this->getCollectionEndpoint($index);
          }

          /** @var \Solarium\Core\Client\Endpoint $endpoint */
          foreach ($endpoints as $index_id => $endpoint) {
            try {
              $key = $endpoint->getBaseUri();
            }
            catch (UnexpectedValueException $e) {
              if ($cloud && 0 === $index_id) {
                $info[] = [
                  'label' => $this->t('Default Collection'),
                  'info' => $this->t("Default collection isn't set. Ensure that the collections are properly set on the indexes in their advanced section of the Solr specific index options."),
                  'status' => 'error',
                ];
              }
              else {
                $info[] = [
                  'label' => $this->t('Additional information'),
                  'info' => $this->t('Collection or core configuration for index %index is wrong or missing: %msg', [
                    '%index' => $index_id,
                    '%msg' => $e->getMessage(),
                  ]),
                  'status' => 'error',
                ];
              }
              continue;
            }

            if (!in_array($key, $endpoints_queried)) {
              $endpoints_queried[] = $key;
              if ($cloud) {
                $connector->setCollectionNameFromEndpoint($endpoint);
              }
              $data = $connector->getLuke();
              if (isset($data['index']['numDocs'])) {
                // Collect the stats.
                $stats_summary = $connector->getStatsSummary();

                if ($data['index']['numDocs'] !== -1) {
                  $pending_msg = $stats_summary['@pending_docs'] ? $this->t('(@pending_docs sent but not yet processed)', $stats_summary) : '';
                  $index_msg = $stats_summary['@index_size'] ? $this->t('(@index_size on disk)', $stats_summary) : '';
                  $indexed_message = $this->t('@num items @pending @index_msg', [
                    '@num' => $data['index']['numDocs'],
                    '@pending' => $pending_msg,
                    '@index_msg' => $index_msg,
                  ]);
                  $info[] = [
                    'label' => $this->t('%key: Indexed', ['%key' => $key]),
                    'info' => $indexed_message,
                  ];

                  if (!empty($stats_summary['@deletes_total'])) {
                    $info[] = [
                      'label' => $this->t('%key: Pending Deletions', ['%key' => $key]),
                      'info' => $stats_summary['@deletes_total'],
                    ];
                  }

                  if (!empty($stats_summary['@autocommit_time'])) {
                    $info[] = [
                      'label' => $this->t('%key: Delay', ['%key' => $key]),
                      'info' => $this->t('@autocommit_time before updates are processed.', $stats_summary),
                    ];
                  }
                }
                $status = 'ok';
                if (!$this->isNonDrupalOrOutdatedConfigSetAllowed()) {
                  $variables[':url'] = Url::fromUri('internal:/' . $this->moduleExtensionList->getPath('search_api_solr') . '/README.md')->toString();
                  $variables[':min_version'] = SolrBackendInterface::SEARCH_API_SOLR_MIN_SCHEMA_VERSION;
                  if (preg_match('/^drupal-(.*?)-solr/', $stats_summary['@schema_version'], $matches)) {
                    if (Comparator::lessThan($matches[1], SolrBackendInterface::SEARCH_API_SOLR_MIN_SCHEMA_VERSION)) {
                      $this->messenger->addError($this->t('Solr is using an outdated <a href="https://solr.apache.org/guide/solr/latest/configuration-guide/config-sets.html">configset</a>, created with a version of Search API Solr older than :min_version. Please follow the instructions in the <a href=":url">README.md</a> file, to create and deploy a fresh set of Solr configuration files, based on the currently installed version of Search API Solr.', $variables));
                      $status = 'error';
                    }
                  }
                  else {
                    $this->messenger->addError($this->t('You are using an incompatible Solr schema. Please follow the instructions described in the <a href=":url">README.md</a> file for setting up Solr.', $variables));
                    $status = 'error';
                  }
                }
                $info[] = [
                  'label' => $this->t('%key: Schema', ['%key' => $key]),
                  'info' => $stats_summary['@schema_version'],
                  'status' => $status,
                ];

                if (!empty($stats_summary['@collection_name'])) {
                  $info[] = [
                    'label' => $this->t('%key: Solr Collection Name', ['%key' => $key]),
                    'info' => $stats_summary['@collection_name'],
                  ];
                }
                elseif (!empty($stats_summary['@core_name'])) {
                  $info[] = [
                    'label' => $this->t('%key: Solr Core Name', ['%key' => $key]),
                    'info' => $stats_summary['@core_name'],
                  ];
                }
              }
            }
          }

          try {
            foreach ($this->getMaxDocumentVersions() as $site_hash => $indexes) {
              if ('#total' === $site_hash) {
                $info[] = [
                  'label' => $this->t('Max document _version_ for this server'),
                  'info' => $indexes,
                ];
              }
              else {
                foreach ($indexes as $index => $datasources) {
                  foreach ($datasources as $datasource => $max_version) {
                    $info[] = [
                      'label' => $this->t('Max _version_ for datasource %datasource in index %index on site %site', [
                        '%datasource' => $datasource,
                        '%index' => $index,
                        '%site' => $site_hash,
                      ]),
                      'info' => $max_version,
                    ];
                  }
                }
              }
            }
          }
          catch (UnexpectedValueException $e) {
            $info[] = [
              'label' => $this->t('Max document _version_ for this server'),
              'info' => $this->t('Collection or core configuration for at least one index on this server is wrong or missing: %msg', [
                '%index' => $index_id,
                '%msg' => $e->getMessage(),
              ]),
              'status' => 'error',
            ];
          }
        }
        catch (SearchApiException $e) {
          $info[] = [
            'label' => $this->t('Additional information'),
            'info' => $this->t('An error occurred while trying to retrieve additional information from the Solr server: %msg', ['%msg' => $e->getMessage()]),
            'status' => 'error',
          ];
        }
      }
    }

    $info[] = [
      'label' => $this->t('Targeted content domain'),
      'info' => $this->getDomain(),
    ];

    if (!empty($this->configuration['disabled_field_types'])) {
      $this->messenger
        ->addWarning($this->t('You disabled some Solr Field Types for this server.'));

      $info[] = [
        'label' => $this->t('Disabled Solr Field Types'),
        'info' => implode(', ', $this->configuration['disabled_field_types']),
      ];
    }

    $info[] = [
      'label' => $this->t('Targeted environment'),
      'info' => $this->getEnvironment(),
    ];

    if (!empty($this->configuration['disabled_caches'])) {
      $this->messenger
        ->addWarning($this->t('You disabled some Solr Caches for this server.'));

      $info[] = [
        'label' => $this->t('Disabled Solr Caches'),
        'info' => implode(', ', $this->configuration['disabled_caches']),
      ];
    }

    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index) {
    if ($this->indexFieldsUpdated($index)) {
      $index->reindex();
      $this->getSolrFieldNames($index, TRUE);
    }
  }

  /**
   * Checks if the recently updated index had any fields changed.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index that was just updated.
   *
   * @return bool
   *   TRUE if any of the fields were updated, FALSE otherwise.
   */
  protected function indexFieldsUpdated(IndexInterface $index) {
    // Get the original index, before the update. If it cannot be found, err on
    // the side of caution.
    if (!isset($index->original)) {
      return TRUE;
    }
    /** @var \Drupal\search_api\IndexInterface $original */
    $original = $index->original;

    $old_fields = $original->getFields();
    $new_fields = $index->getFields();
    if (!$old_fields && !$new_fields) {
      return FALSE;
    }
    if (array_diff_key($old_fields, $new_fields) || array_diff_key($new_fields, $old_fields)) {
      return TRUE;
    }
    $old_field_names = $this->getSolrFieldNames($original, TRUE);
    $new_field_names = $this->getSolrFieldNames($index, TRUE);
    return $old_field_names != $new_field_names;
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex($index) {
    parent::removeIndex($index);
    // Reset the static field names cache.
    $this->getLanguageSpecificSolrFieldNames(LanguageInterface::LANGCODE_NOT_SPECIFIED, NULL, TRUE);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function indexItems(IndexInterface $index, array $items) {
    $ret = [];
    $connector = $this->getSolrConnector();
    $update_query = $connector->getUpdateQuery();
    $documents = $this->getDocuments($index, $items, $update_query);
    if ($documents) {
      $field_names = $this->getSolrFieldNames($index);
      $endpoint = $this->getCollectionEndpoint($index);
      try {
        $update_query->addDocuments($documents);
        $connector->update($update_query, $endpoint);

        foreach ($documents as $document) {
          // We don't use $item->id() because we want have the real value that
          // went into the index.
          $ret[] = $document->getFields()[$field_names['search_api_id']];
        }
      }
      catch (SearchApiSolrException $e) {
        if ($this->configuration['index_single_documents_fallback_count']) {
          // It might be that a single document caused the exception. Try to
          // index one by one and create a meaningful error message if possible.
          $count = 0;
          foreach ($documents as $document) {
            if ($count++ < $this->configuration['index_single_documents_fallback_count']) {
              $id = $document->getFields()[$field_names['search_api_id']];

              try {
                $update_query = $connector->getUpdateQuery();
                $update_query->addDocument($document);
                $connector->update($update_query, $endpoint);
                $ret[] = $id;
              }
              catch (\Exception $e) {
                $this->logException($e, '%type while indexing item %id: @message in %function (line %line of %file).', ['%id' => $id]);
                // We must not throw an exception because we might have indexed
                // some documents successfully now and need to return these ids.
              }
            }
            else {
              break;
            }
          }
        }
        else {
          $this->logException($e, "%type while indexing: @message in %function (line %line of %file).");
          throw $e;
        }
      }
      catch (\Exception $e) {
        $this->logException($e, "%type while indexing: @message in %function (line %line of %file).");
        throw new SearchApiSolrException($e->getMessage(), $e->getCode(), $e);
      }

      if ($ret) {
        $this->state->set('search_api_solr.' . $index->id() . '.last_update', $this->time->getCurrentTime());
      }
    }
    return $ret;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getDocument(IndexInterface $index, ItemInterface $item) {
    $documents = $this->getDocuments($index, [$item->getId() => $item]);
    return reset($documents);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getDocuments(IndexInterface $index, array $items, ?UpdateQuery $update_query = NULL) {
    $index_third_party_settings = $index->getThirdPartySettings('search_api_solr') + search_api_solr_default_index_third_party_settings();
    $documents = [];
    $index_id = $this->getTargetedIndexId($index);
    $site_hash = $this->getTargetedSiteHash($index);
    $languages = $this->languageManager->getLanguages();
    $specific_languages = array_keys(array_filter($index_third_party_settings['multilingual']['specific_languages'] ?? []));
    $use_language_undefined_as_fallback_language = $index_third_party_settings['multilingual']['use_language_undefined_as_fallback_language'] ?? FALSE;
    $use_universal_collation = $index_third_party_settings['multilingual']['use_universal_collation'] ?? FALSE;
    $fulltext_fields = $index->getFulltextFields();
    $request_time = $this->formatDate($this->time->getRequestTime());
    $base_urls = [];

    if (!$update_query) {
      $connector = $this->getSolrConnector();
      $update_query = $connector->getUpdateQuery();
    }

    /** @var \Drupal\search_api\Item\ItemInterface[] $items */
    foreach ($items as $id => $item) {
      $language_id = $item->getLanguage();
      if (
        $language_id === LanguageInterface::LANGCODE_NOT_APPLICABLE ||
        (!empty($specific_languages) && !in_array($language_id, $specific_languages))
      ) {
        $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED;
        $item->setLanguage($language_id);
      }

      /* @see \Drupal\search_api\Plugin\search_api\processor\LanguageWithFallback */
      $fallback_languages = [];
      $fallback_field_names = [];
      $language_with_fallback_field = $item->getField('language_with_fallback', FALSE);
      if ($language_with_fallback_field) {
        $fallback_languages = array_diff($language_with_fallback_field->getValues(), [
          $language_id,
          LanguageInterface::LANGCODE_NOT_SPECIFIED,
        ]);
        if (!empty($specific_languages)) {
          $fallback_languages = array_intersect($fallback_languages, $specific_languages);
        }
      }

      foreach ($fallback_languages as $fallback_language) {
        $fallback_field_names[$fallback_language] = $this->getLanguageSpecificSolrFieldNames($use_language_undefined_as_fallback_language ? LanguageInterface::LANGCODE_NOT_SPECIFIED : $fallback_language, $index);
      }
      $field_names = $this->getLanguageSpecificSolrFieldNames($language_id, $index);
      $boost_terms = [];

      /** @var \Solarium\QueryType\Update\Query\Document $doc */
      $event = new PreCreateIndexDocumentEvent($item, $update_query->createDocument(), $index);
      $this->dispatch($event);
      $doc = $event->getSolariumDocument();

      $doc->setField('timestamp', $request_time);
      $doc->setField('id', $this->createId($site_hash, $index_id, $id));
      $doc->setField('index_id', $index_id);
      // Some processors might add an absolute boost factor to the item. Since
      // Solr doesn't support index time boosting anymore, we simply store that
      // factor and include it in the boost calculation at query time.
      // @see \Drupal\search_api\Plugin\search_api\processor\TypeBoost
      $doc->setField('boost_document', $item->getBoost());
      // Suggester context boolean filter queries have issues with special
      // characters like '/' or ':' if not properly quoted (by solarium). We
      // avoid that by reusing our field name encoding.
      $doc->addField('sm_context_tags', Utility::encodeSolrName('search_api/index:' . $index_id));
      // Add the site hash and language-specific base URL.
      $doc->setField('hash', $site_hash);
      $doc->addField('sm_context_tags', Utility::encodeSolrName('search_api_solr/site_hash:' . $site_hash));
      $doc->addField('sm_context_tags', Utility::encodeSolrName('drupal/langcode:' . $language_id));
      if (!isset($base_urls[$language_id])) {
        $url_options = ['absolute' => TRUE];
        if (isset($languages[$language_id])) {
          $url_options['language'] = $languages[$language_id];
        }
        // An exception is thrown if this is called during a non-HTML response
        // like REST or a redirect without collecting metadata. Avoid that by
        // collecting and discarding it.
        // See https://www.drupal.org/node/2638686.
        $base_urls[$language_id] = Url::fromRoute('<front>', [], $url_options)->toString(TRUE)->getGeneratedUrl();
      }
      $doc->setField('site', $base_urls[$language_id]);
      $item_fields = $item->getFields();
      $item_fields += $special_fields = $this->getSpecialFields($index, $item);
      $auto_aggregate_values = [];
      /** @var \Drupal\search_api\Item\FieldInterface $field */
      foreach ($item_fields as $name => $field) {
        // If the field is not known for the index, something weird has
        // happened. We refuse to index the items and hope that the others are
        // OK.
        if (!isset($field_names[$name])) {
          $vars = [
            '%field' => $name,
            '@id' => $id,
          ];
          $this->getLogger()->warning('Error while indexing: Unknown field %field on the item with ID @id.', $vars);
          $doc = NULL;
          break;
        }

        $type = $field->getType();
        $field_identifier = $field->getFieldIdentifier();
        switch ($field->getPropertyPath()) {
          case 'auto_aggregated_fulltext_field':
            if (!array_key_exists($type, $auto_aggregate_values)) {
              foreach ($item_fields as $tmp_field) {
                if ($tmp_field->getType() === $type && $tmp_field->getPropertyPath() !== 'auto_aggregated_fulltext_field') {
                  $auto_aggregate_values[$type][] = $tmp_field->getValues();
                }
              }
              if (array_key_exists($type, $auto_aggregate_values)) {
                $auto_aggregate_values[$type] = array_merge(...$auto_aggregate_values[$type]);
              }
            }

            $first_value = $this->addIndexField($doc, $field_names[$name], $auto_aggregate_values[$type] ?? [], $type, $boost_terms);
            $fallback_values = [];
            foreach ($fallback_languages as $fallback_language) {
              if (!isset($fallback_values[$fallback_language])) {
                $event = new PreAddLanguageFallbackFieldEvent($fallback_language, $auto_aggregate_values[$type] ?? [], $type, $item);
                $this->eventDispatcher->dispatch($event);
                $value = $event->getValue();
                if ($value) {
                  $this->addIndexField($doc, $fallback_field_names[$fallback_language][$name], $value, $type, $boost_terms);
                }
                $fallback_values[$fallback_language] = $value;
              }
            }
            break;

          case 'language_with_fallback':
            $values = $field->getValues();
            if (!empty($specific_languages)) {
              $values = array_intersect($values, $specific_languages);
            }
            $first_value = $this->addIndexField($doc, $field_names[$name], $values, $type, $boost_terms);
            break;

          default:
            $first_value = $this->addIndexField($doc, $field_names[$name], $field->getValues(), $type, $boost_terms);
            if (in_array($field_identifier, $fulltext_fields)) {
              $fallback_values = [];
              foreach ($fallback_languages as $fallback_language) {
                if (!isset($fallback_values[$fallback_language])) {
                  $event = new PreAddLanguageFallbackFieldEvent($fallback_language, $field->getValues(), $type, $item);
                  $this->eventDispatcher->dispatch($event);
                  $value = $event->getValue();
                  if ($value) {
                    $this->addIndexField($doc, $fallback_field_names[$fallback_language][$name], $value, $type, $boost_terms);
                  }
                  $fallback_values[$fallback_language] = $value;
                }
              }
            }
            break;
        }

        // Enable sorts in some special cases.
        if (($first_value !== NULL) && !array_key_exists($name, $special_fields)) {
          if (
            strpos($field_names[$name], 't') === 0 ||
            strpos($field_names[$name], 's') === 0
          ) {
            if (
              strpos($field_names[$name], 'twm_suggest') !== 0 &&
              strpos($field_names[$name], 'spellcheck') !== 0
            ) {
              // Truncate the string to avoid Solr string field limitation.
              // @see https://www.drupal.org/node/2809429
              // @see https://www.drupal.org/node/2852606
              // 128 characters should be enough for sorting and it makes no
              // sense to heavily increase the index size. The DB backend limits
              // the sort strings to 32 characters. But for example a
              // search_api_id quickly exceeds 32 characters and the interesting
              // ID is at the end of the string:
              // 'entity:entity_test_mulrev_changed/2:en'.
              if (mb_strlen($first_value) > 128) {
                $first_value = Unicode::truncate($first_value, 128);
              }

              $sort_languages = [];
              if (!$use_universal_collation) {
                // Copy fulltext and string fields to a dedicated sort fields
                // for faster sorts and language specific collations. To
                // allow sorted multilingual searches we need to fill *all*
                // language-specific sort fields!
                $sort_languages = array_keys($this->languageManager
                  ->getLanguages());
                if (!empty($specific_languages)) {
                  $sort_languages = array_intersect($sort_languages, $specific_languages);
                }
              }
              $sort_languages[] = LanguageInterface::LANGCODE_NOT_SPECIFIED;
              foreach ($sort_languages as $sort_language_id) {
                $key = Utility::encodeSolrName('sort' . SolrBackendInterface::SEARCH_API_SOLR_LANGUAGE_SEPARATOR . $sort_language_id . '_' . $name);
                if (!$doc->{$key}) {
                  $doc->addField($key, $first_value);
                }
              }
            }
          }
          elseif (preg_match('/^([a-z]+)m(_.*)/', $field_names[$name], $matches) && strpos($field_names[$name], 'random_') !== 0) {
            $key = $matches[1] . 's' . $matches[2];
            if (!$doc->{$key}) {
              // For other multi-valued fields (which aren't sortable by nature)
              // we use the same hackish workaround like the DB backend: just
              // copy the first value in a single value field for sorting.
              $doc->addField($key, $first_value);
            }
          }
        }
      }

      foreach ($boost_terms as $term => $boost) {
        $doc->addField('boost_term', sprintf('%s|%.1F', $term, $boost));
      }

      if ($doc) {
        $event = new PostCreateIndexDocumentEvent($item, $doc, $index);
        $this->dispatch($event);
        $documents[] = $event->getSolariumDocument();
      }
    }

    // Let other modules alter documents before sending them to solr.
    $event = new PostCreateIndexDocumentsEvent($items, $documents);
    $this->dispatch($event);

    return $event->getSolariumDocuments();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function deleteItems(IndexInterface $index, array $ids) {
    /** @var \Drupal\search_api_solr\Entity\Index $index */
    if ($index->isIndexingEmptyIndex()) {
      return;
    }

    try {
      $index_id = $this->getTargetedIndexId($index);
      $site_hash = $this->getTargetedSiteHash($index);
      $solr_ids = [];
      foreach ($ids as $id) {
        $solr_ids[] = $this->createId($site_hash, $index_id, $id);
      }
      $connector = $this->getSolrConnector();
      $update_query = $connector->getUpdateQuery();
      // Delete documents by IDs (this would cover the case if this index
      // contains stand-alone (childless) documents). And then also delete by
      // _root_ field which assures children (nested) documents will be removed
      // too. The field _root_ is assigned to the ID of the top-level document
      // across an entire block of parent + children.
      foreach (array_chunk($solr_ids, 30) as $solr_ids_chunk) {
        $update_query->addDeleteQuery('{!terms f=_root_}("' . implode('","', $solr_ids_chunk) . '")');
        $update_query->addDeleteByIds($solr_ids_chunk);
      }
      $connector->update($update_query, $this->getCollectionEndpoint($index));
      $this->state->set('search_api_solr.' . $index->id() . '.last_update', $this->time->getCurrentTime());
    }
    catch (ExceptionInterface $e) {
      throw new SearchApiSolrException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function deleteAllIndexItems(IndexInterface $index, $datasource_id = NULL) {
    // Since the index ID we use for indexing can contain arbitrary
    // prefixes, we have to escape it for use in the query.
    $connector = $this->getSolrConnector();
    $index_id = $this->queryHelper->escapeTerm($this->getTargetedIndexId($index));
    $site_hash = $this->queryHelper->escapeTerm($this->getTargetedSiteHash($index));
    $query = '+index_id:' . $index_id;
    $query .= ' +hash:' . $site_hash;
    if ($datasource_id) {
      $query .= ' +' . $this->getSolrFieldNames($index)['search_api_datasource'] . ':' . $this->queryHelper->escapeTerm($datasource_id);
    }
    $update_query = $connector->getUpdateQuery();
    $update_query->addDeleteQuery($query);
    $connector->update($update_query, $this->getCollectionEndpoint($index));

    // Delete corresponding checkpoints.
    if ($connector->isCloud()) {
      /** @var \Drupal\search_api_solr\SolrCloudConnectorInterface $connector */
      $connector->deleteCheckpoints($index_id, $site_hash);
    }

    $this->state->set('search_api_solr.' . $index->id() . '.last_update', $this->time->getCurrentTime());
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexFilterQueryString(IndexInterface $index) {
    $fq = '+index_id:' . $this->queryHelper->escapeTerm($this->getTargetedIndexId($index));

    // Set the site hash filter, if enabled.
    if ($this->configuration['site_hash']) {
      $fq .= ' +hash:' . $this->queryHelper->escapeTerm($this->getTargetedSiteHash($index));
    }

    return $fq;
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionEndpoint(IndexInterface $index) {
    $connector = $this->getSolrConnector();
    if ($this->solrConnector->isCloud()) {
      // Using the index_id as endpoint name leads to collisions in a multisite
      // setup. Using the index filter query string instead is a good
      // workaround.
      $endpoint_name = $this->getIndexFilterQueryString($index);
      try {
        return $this->solrConnector->getEndpoint($endpoint_name);
      }
      catch (OutOfBoundsException $e) {
        $additional_config = [];
        if ($settings = Utility::getIndexSolrSettings($index)) {
          if ($settings['advanced']['collection']) {
            $additional_config['core'] = $settings['advanced']['collection'];
          }
        }
        return $this->solrConnector->createEndpoint($endpoint_name, $additional_config);
      }
    }

    return $connector->getEndpoint();
  }

  /**
   * {@inheritdoc}
   */
  public function finalizeIndex(IndexInterface $index) {
    // Avoid endless loops if finalization hooks trigger searches or streaming
    // expressions themselves.
    static $finalization_in_progress = [];

    if ($index->status() && !isset($finalization_in_progress[$index->id()]) && !$index->isReadOnly()) {
      $settings = Utility::getIndexSolrSettings($index);
      if (
        // Not empty reflects the default FALSE for outdated index configs, too.
        !empty($settings['finalize']) &&
        $this->state->get('search_api_solr.' . $index->id() . '.last_update', 0) >= $this->state->get('search_api_solr.' . $index->id() . '.last_finalization', 0)
      ) {
        $lock_name = 'search_api_solr.' . $index->id() . '.finalization_lock';
        if ($this->lock->acquire($lock_name)) {
          if ($settings['debug_finalize']) {
            $vars = ['%index_id' => $index->id(), '%pid' => getmypid()];
            $this->getLogger()->debug('PID %pid, Index %index_id: Finalization lock acquired.', $vars);
          }
          $finalization_in_progress[$index->id()] = TRUE;
          $connector = $this->getSolrConnector();
          $previous_query_timeout = $connector->adjustTimeout($connector->getFinalizeTimeout(), SolrConnectorInterface::QUERY_TIMEOUT);
          $previous_index_timeout = $connector->adjustTimeout($connector->getFinalizeTimeout(), SolrConnectorInterface::INDEX_TIMEOUT);
          try {
            if (!empty($settings['commit_before_finalize'])) {
              $this->ensureCommit($index);
            }

            $this->dispatch(new PreIndexFinalizationEvent($index));

            if (!empty($settings['commit_after_finalize'])) {
              $this->ensureCommit($index);
            }

            $this->state
              ->set('search_api_solr.' . $index->id() . '.last_finalization',
                $this->time->getRequestTime());
            $this->lock->release($lock_name);
            if ($settings['debug_finalize']) {
              $vars = ['%index_id' => $index->id(), '%pid' => getmypid()];
              $this->getLogger()->debug('PID %pid, Index %index_id: Finalization lock released.', $vars);
            }

            $this->dispatch(new PostIndexFinalizationEvent($index));
          }
          catch (\Exception $e) {
            unset($finalization_in_progress[$index->id()]);
            $this->lock->release('search_api_solr.' . $index->id() . '.finalization_lock');
            if ($e instanceof StreamException) {
              throw new SearchApiSolrException($e->getMessage() . "\n" . ExpressionBuilder::indent($e->getExpression()), $e->getCode(), $e);
            }
            throw new SearchApiSolrException($e->getMessage(), $e->getCode(), $e);
          }
          unset($finalization_in_progress[$index->id()]);

          $connector->adjustTimeout($previous_query_timeout, SolrConnectorInterface::QUERY_TIMEOUT);
          $connector->adjustTimeout($previous_index_timeout, SolrConnectorInterface::INDEX_TIMEOUT);

          return TRUE;
        }

        if ($this->lock->wait($lock_name)) {
          // wait() returns TRUE if the lock isn't released within the given
          // timeout (default 30s).
          if ($settings['debug_finalize']) {
            $vars = ['%index_id' => $index->id(), '%pid' => getmypid()];
            $this->getLogger()->debug('PID %pid, Index %index_id: Waited unsuccessfully for finalization lock.', $vars);
          }
          throw new SearchApiSolrException('The search index currently being rebuilt. Try again later.');
        }

        $this->finalizeIndex($index);
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Options on $query prefixed by 'solr_param_' will be passed natively to Solr
   * as query parameter without the prefix. For example you can set the "Minimum
   * Should Match" parameter 'mm' to '75%' like this:
   * @code
   *   $query->setOption('solr_param_mm', '75%');
   * @endcode
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function search(QueryInterface $query) {
    try {
      /** @var \Drupal\search_api\Entity\Index $index */
      $index = $query->getIndex();

      $this->finalizeIndex($index);

      if ($query->getOption('solr_streaming_expression', FALSE)) {
        if ($solarium_result = $this->executeStreamingExpression($query)) {
          // Extract results.
          $search_api_result_set = $this->extractResults($query, $solarium_result);
        }
        else {
          throw new SearchApiSolrException('Streaming expression has no result.');
        }
      }
      else {
        $mlt_options = $query->getOption('search_api_mlt');
        if (!empty($mlt_options)) {
          $query->addTag('mlt');
        }

        // Ensure language(s) condition is set.
        $language_ids = Utility::ensureLanguageCondition($query);

        // Get field information.
        $connector = $this->getSolrConnector();
        $solarium_query = NULL;
        $edismax = NULL;
        $index_fields = $index->getFields();
        $index_fields += $this->getSpecialFields($index);

        if ($query->hasTag('mlt')) {
          $solarium_query = $this->getMoreLikeThisQuery($query);
        }
        else {
          // Instantiate a Solarium select query.
          $solarium_query = $connector->getSelectQuery();
          $edismax = $solarium_query->getEDisMax();

          $field_names = $this->getSolrFieldNamesKeyedByLanguage($language_ids, $index);

          // Set searched fields.
          $search_fields = $this->getQueryFulltextFields($query);
          $query_fields_boosted = [];
          foreach ($search_fields as $search_field) {
            /** @var \Drupal\search_api\Item\FieldInterface $field */
            $field = $index_fields[$search_field];
            $boost = $field->getBoost() ? '^' . $field->getBoost() : '';
            $names = [];
            $first_name = reset($field_names[$search_field]);
            if (strpos($first_name, 't') === 0) {
              // Add all language-specific field names. This should work for
              // non Drupal Solr Documents as well which contain only a single
              // name.
              $names = array_values($field_names[$search_field]);
            }
            else {
              $names[] = $first_name;
            }

            foreach (array_unique($names) as $name) {
              $query_fields_boosted[] = $name . $boost;
            }
          }
          $edismax->setQueryFields(implode(' ', $query_fields_boosted));

        }

        $options = $query->getOptions();

        // Set basic filters.
        $filter_queries = $this->getFilterQueries($query, $options);
        foreach ($filter_queries as $id => $filter_query) {
          $solarium_query->createFilterQuery('filters_' . $id)
            ->setQuery($filter_query['query'])
            ->addTags($filter_query['tags']);
        }

        if (!Utility::hasIndexJustSolrDocumentDatasource($index)) {
          // Set the Index (and site) filter.
          $solarium_query->createFilterQuery('index_filter')->setQuery(
            $this->getIndexFilterQueryString($index)
          );
        }
        else {
          // Set requestHandler for the query type, if necessary and configured.
          $config = $index->getDatasource('solr_document')->getConfiguration();
          if (!empty($config['request_handler'])) {
            $solarium_query->addParam('qt', $config['request_handler']);
          }

          // Set the default query, if necessary and configured.
          if (!$solarium_query->getQuery() && !empty($config['default_query'])) {
            $solarium_query->setQuery($config['default_query']);
          }

          // The query builder of Search API Solr Search bases on 'OR' which is
          // the default value for solr, too. But a foreign schema could have a
          // non-default config for q.op. Therefore we need to set it explicitly
          // if not set.
          $params = $solarium_query->getParams();
          if (!isset($params['q.op'])) {
            $solarium_query->addParam('q.op', 'OR');
          }
        }

        $search_api_language_ids = $query->getLanguages() ?? [];
        if (!empty($search_api_language_ids)) {
          $unspecific_field_names = $this->getSolrFieldNames($index);
          // For solr_document datasource, search_api_language might not be
          // mapped.
          if (!empty($unspecific_field_names['search_api_language'])) {
            $solarium_query->createFilterQuery('language_filter')->setQuery(
              $this->createFilterQuery($unspecific_field_names['search_api_language'], $language_ids, 'IN', $index_fields['search_api_language'], $options)
            );
          }
        }

        $search_api_retrieved_field_values = array_flip($query->getOption('search_api_retrieved_field_values', []));
        if (array_key_exists('search_api_solr_score_debugging', $search_api_retrieved_field_values)) {
          unset($search_api_retrieved_field_values['search_api_solr_score_debugging']);
          // Activate the debug query component.
          $solarium_query->getDebug();
        }
        $search_api_retrieved_field_values = array_keys($search_api_retrieved_field_values);

        if ($query->hasTag('mlt')) {
          // Set the list of fields to retrieve, but avoid highlighting and
          // different overhead.
          $this->setFields($solarium_query, $search_api_retrieved_field_values, $query, FALSE);
        }
        else {
          // Set the list of fields to retrieve.
          $this->setFields($solarium_query, $search_api_retrieved_field_values, $query);

          // Set sorts.
          $this->setSorts($solarium_query, $query);

          // Set facet fields. setSpatial() might add more facets.
          $this->setFacets($query, $solarium_query);

          // Handle spatial filters.
          if (isset($options['search_api_location'])) {
            $this->setSpatial($solarium_query, $options['search_api_location'], $query);
          }

          // Handle spatial filters.
          if (isset($options['search_api_rpt'])) {
            $this->setRpt($solarium_query, $options['search_api_rpt'], $query);
          }

          // Handle field collapsing / grouping.
          if (isset($options['search_api_grouping'])) {
            $this->setGrouping($solarium_query, $query, $options['search_api_grouping'], $index_fields, $field_names);
          }

          // Handle spellcheck.
          if (isset($options['search_api_spellcheck'])) {
            $this->setSpellcheck($solarium_query, $query, $options['search_api_spellcheck']);
          }
        }

        if (isset($options['offset'])) {
          $solarium_query->setStart($options['offset']);
        }

        // In previous versions we set a high value for rows if no limit was set
        // in the options. The intention was to retrieve "all" results instead
        // of falling back to Solr's default of 10. But for Solr Cloud it turned
        // out that independent of the real number of documents, Solr seems to
        // allocate rows*shards memory for sorting the distributed result. That
        // could lead to out of memory exceptions. The default limit is now
        // configurable as advanced server option.
        $solarium_query->setRows($query->getOption('limit') ?? ($this->configuration['rows'] ?? 10));

        foreach ($options as $option => $value) {
          if (strpos($option, 'solr_param_') === 0) {
            $solarium_query->addParam(substr($option, 11), $value);
          }
        }

        $this->applySearchWorkarounds($solarium_query, $query);

        // Allow modules to alter the solarium query.
        $event = new PreQueryEvent($query, $solarium_query);
        $this->dispatch($event);
        $solarium_query = $event->getSolariumQuery();

        // Since Solr 7.2 the edismax query parser doesn't allow local
        // parameters anymore. But since we don't want to force all modules that
        // implemented our hooks to re-write their code, we transform the query
        // back into a lucene query. flattenKeys() was adjusted accordingly, but
        // in a backward compatible way.
        // @see https://lucene.apache.org/solr/guide/7_2/solr-upgrade-notes.html#solr-7-2
        if ($edismax) {
          $parse_mode = $query->getParseMode();
          $parse_mode_id = $parse_mode->getPluginId();
          /** @var \Solarium\Core\Query\AbstractQuery $solarium_query */
          $params = $solarium_query->getParams();
          // Extract keys.
          $keys = $query->getKeys();
          $query_fields_boosted = $edismax->getQueryFields() ?? '';

          if (isset($params['defType']) && 'edismax' === $params['defType']) {
            // Edismax was forced via API. In case of parse mode 'direct' we get
            // a string we use as it is. In the other cases we just need to
            // escape the keys.
            $flatten_keys = 'direct' === $parse_mode_id ? $keys : Utility::flattenKeys($keys, [], 'keys');
          }
          else {
            $settings = Utility::getIndexSolrSettings($index);
            $flatten_keys = Utility::flattenKeys(
              $keys,
              ($query_fields_boosted ? explode(' ', $query_fields_boosted) : []),
              $parse_mode_id,
              $settings['term_modifiers']
            );
          }

          if ('direct' !== $parse_mode_id && strpos($flatten_keys, '-(') === 0) {
            // flattenKeys() always wraps the query in parenthesis. If the query
            // is negated we need to extend it by *:* which logically means 'all
            // documents' except the ones that match the flatten keys.
            $flatten_keys = '*:* ' . $flatten_keys;
          }

          $flatten_query = [];
          if (!Utility::hasIndexJustSolrDocumentDatasource($index) && (!isset($params['defType']) || 'edismax' !== $params['defType'])) {
            // Apply term boosts if configured via a Search API processor if
            // sort by search_api_relevance is present.
            $sorts = $solarium_query->getSorts();
            $relevance_field = reset($field_names['search_api_relevance']);
            if (isset($sorts[$relevance_field])) {
              if ($boosts = $query->getOption('solr_document_boost_factors', [])) {
                $sum[] = 'boost_document';
                foreach ($boosts as $field_id => $boost) {
                  $boostable_solr_field_name = Utility::getBoostableSolrField($field_id, $field_names, $query);
                  $sum[] = str_replace(self::FIELD_PLACEHOLDER, $boostable_solr_field_name, $boost);
                }
                $flatten_query[] = '{!boost b=sum(' . implode(',', $sum) . ')}';
              }
              else {
                $flatten_query[] = '{!boost b=boost_document}';
              }
              // @todo Remove condition together with search_api_solr_legacy.
              if (version_compare($connector->getSolrMajorVersion(), '6', '>=')) {
                // Since Solr 6 we could use payload_score!
                $flatten_query[] = Utility::flattenKeysToPayloadScore($keys, $parse_mode);
              }
            }
          }

          $flatten_query[] = trim($flatten_keys ?: '*:*');

          $solarium_query->setQuery(implode(' ', $flatten_query));

          if (!isset($params['defType']) || 'edismax' !== $params['defType']) {
            $solarium_query->removeComponent(ComponentAwareQueryInterface::COMPONENT_EDISMAX);
          }
          else {
            // Remove defType 'edismax' because the solarium query still has the
            // edismax component which will set defType itself. We should avoid
            // to have this parameter twice.
            $solarium_query->removeParam('defType');
          }
        }

        // Allow modules to alter the converted solarium query.
        $event = new PostConvertedQueryEvent($query, $solarium_query);
        $this->dispatch($event);
        $solarium_query = $event->getSolariumQuery();

        // Send search request.
        $response = $connector->search($solarium_query, $this->getCollectionEndpoint($index));
        $body = $response->getBody();
        if (200 != $response->getStatusCode()) {
          throw new SearchApiSolrException(strip_tags($body), $response->getStatusCode());
        }
        $search_api_response = new Response($body, $response->getHeaders());

        $solarium_result = $connector->createSearchResult($solarium_query, $search_api_response);

        // Extract results.
        $search_api_result_set = $this->extractResults($query, $solarium_result, $language_ids);

        if ($solarium_result instanceof Result) {
          // Extract facets.
          if ($solarium_facet_set = $solarium_result->getFacetSet()) {
            $search_api_result_set->setExtraData('facet_set', $solarium_facet_set);
            if ($search_api_facets = $this->extractFacets($query, $solarium_result)) {
              $search_api_result_set->setExtraData('search_api_facets', $search_api_facets);
            }
          }

          // Extract spellcheck suggestions.
          if (isset($options['search_api_spellcheck'])) {
            $search_api_spellcheck['suggestions'] = $this->extractSpellCheckSuggestions($solarium_result);
            if (!empty($options['search_api_spellcheck']['collate'])) {
              /** @var \Solarium\Component\Result\Spellcheck\Result $spellcheck_result */
              if ($spellcheck_result = $solarium_result->getComponent(ComponentAwareQueryInterface::COMPONENT_SPELLCHECK)) {
                if ($collation = $spellcheck_result->getCollation()) {
                  $search_api_spellcheck['collation'] = $collation->getQuery();
                }
              }
            }
            $search_api_result_set->setExtraData('search_api_spellcheck', $search_api_spellcheck);
          }
        }
      }

      $event = new PostExtractResultsEvent($search_api_result_set, $query, $solarium_result);
      $this->dispatch($event);
    }
    catch (\Exception $e) {
      if ($query instanceof RefinableCacheableDependencyInterface) {
        // Avoid caching of an empty result in Search API and views.
        // @see https://www.drupal.org/project/search_api_solr/issues/3133997
        $query->mergeCacheMaxAge(0);
      }

      // Don't expose Solr error message details to the user. The search_api
      // views integration forwards the exception message to the end user. Just
      // log the datails.
      $this->getLogger()->error('@exception', ['@exception' => $e->getMessage()]);

      throw new SearchApiSolrException('An error occurred while searching, try again later.', $e->getCode(), $e);
    }
  }

  /**
   * Apply workarounds for special Solr versions before searching.
   *
   * @param \Solarium\Core\Query\QueryInterface $solarium_query
   *   The Solarium select query object.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object representing the executed
   *   search query.
   */
  protected function applySearchWorkarounds(SolariumQueryInterface $solarium_query, QueryInterface $query) {
    $solarium_query->setTimeZone(Utility::getTimeZone($query->getIndex()));

    // Do not modify 'Server index status' queries.
    // @see https://www.drupal.org/node/2668852
    if ($query->hasTag('server_index_status')) {
      return;
    }

    /* We keep this as an example.
    $connector = $this->getSolrConnector();
    $schema_version = $connector->getSchemaVersion();
    $solr_version = $connector->getSolrVersion();

    // Schema versions before 4.4 set the default query operator to 'AND'. But
    // incompatibilities since Solr 5.5.0 required a new query builder that
    // bases on 'OR'.
    // @see https://www.drupal.org/node/2724117
    if (version_compare($schema_version, '4.4', '<')) {
    $params = $solarium_query->getParams();
    if (!isset($params['q.op'])) {
    $solarium_query->addParam('q.op', 'OR');
    }
    }
     */
  }

  /**
   * Get the list of fields Solr must return as result.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object representing the executed
   *   search query.
   *
   * @return array
   *   An array of required fields as strings.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getRequiredFields(QueryInterface $query) {
    $index = $query->getIndex();
    $field_names = $this->getSolrFieldNames($index);
    // The list of fields Solr must return to build a Search API result.
    $required_fields = [
      $field_names['search_api_id'],
      $field_names['search_api_language'],
      $field_names['search_api_relevance'],
    ];

    if (!$this->configuration['site_hash']) {
      $required_fields[] = 'hash';
    }

    try {
      $index->getProcessor('language_with_fallback');
      if ($query->getIndex()->getField('language_with_fallback')) {
        $required_fields[] = $field_names['language_with_fallback'];
      }
    }
    catch (SearchApiException $exception) {
      // Processor not active.
    }

    if (Utility::hasIndexJustSolrDocumentDatasource($index)) {
      $config = $this->getDatasourceConfig($index);
      $extra_fields = [
        'label_field',
        'url_field',
      ];
      foreach ($extra_fields as $config_key) {
        if (!empty($config[$config_key])) {
          $required_fields[] = $config[$config_key];
        }
      }
    }

    return array_filter($required_fields);
  }

  /**
   * Set the list of fields Solr should return as result.
   *
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The solr query.
   * @param array $fields_to_be_retrieved
   *   The field values to be retrieved from Solr.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object representing the executed
   *   search query.
   * @param bool $highlight
   *   Wheter to highlight a field's content or not.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function setFields(Query $solarium_query, array $fields_to_be_retrieved, QueryInterface $query, $highlight = TRUE) {
    $required_fields = $this->getRequiredFields($query);
    $returned_fields = [];
    $highlight_fields = ['*'];

    if (!empty($this->configuration['retrieve_data'])) {
      $field_names = $this->getSolrFieldNamesKeyedByLanguage(Utility::ensureLanguageCondition($query), $query->getIndex());

      // If Search API provides information about the fields to retrieve, limit
      // the fields accordingly. ...
      foreach ($fields_to_be_retrieved as $field_name) {
        if (isset($field_names[$field_name])) {
          $returned_fields[] = array_values($field_names[$field_name]);
        }
      }
      if ($returned_fields) {
        // Flatten $returned_fields.
        $highlight_fields = array_unique(array_merge(...$returned_fields));
        // Ensure that required fields are returned.
        $returned_fields = array_unique(array_merge($highlight_fields, $required_fields));
        // Just highlight string and text fields to avoid Solr exceptions.
        $highlight_fields = array_filter($highlight_fields, function ($v) {
          return preg_match('/^t.*?[sm]_/', $v) || preg_match('/^s[sm]_/', $v);
        });
      }
      elseif ($query->hasTag('views')) {
        // The view seems to be configured to display rendered entities, just
        // return the required fields to identify the entities.
        $returned_fields = $required_fields;
      }
      else {
        // Otherwise, return all fields and score.
        $returned_fields = ['*', reset($field_names['search_api_relevance'])];
      }
    }
    else {
      $returned_fields = $required_fields;
    }

    $solarium_query->setFields(array_unique($returned_fields));

    if ($highlight) {
      try {
        $highlight_config = $query->getIndex()
          ->getProcessor('highlight')
          ->getConfiguration();
        if ($highlight_config['highlight'] !== 'never') {
          $this->setHighlighting($solarium_query, $query, $highlight_fields);
        }
      }
      catch (SearchApiException $exception) {
        // Highlighting processor is not enabled for this index. Just use the
        // index configuration.
        $this->setHighlighting($solarium_query, $query, $highlight_fields);
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function executeStreamingExpression(QueryInterface $query) {
    try {
      $stream_expression = $query->getOption('solr_streaming_expression', FALSE);
      if (!$stream_expression) {
        throw new SearchApiSolrException('Streaming expression missing.');
      }

      $connector = $this->getSolrConnector();
      if (!($connector instanceof SolrCloudConnectorInterface)) {
        throw new SearchApiSolrException('Streaming expression are only supported by a Solr Cloud connector.');
      }

      $index = $query->getIndex();
      $this->finalizeIndex($index);

      $stream = $connector->getStreamQuery();
      $stream->setExpression($stream_expression);
      $stream->setOptions(['documentclass' => StreamDocument::class]);
      $this->applySearchWorkarounds($stream, $query);

      $result = NULL;

      $result = $connector->stream($stream, $this->getCollectionEndpoint($index));

      if ($processors = $query->getIndex()->getProcessorsByStage(ProcessorInterface::STAGE_POSTPROCESS_QUERY)) {
        foreach ($processors as $key => $processor) {
          if (!($processor instanceof SolrProcessorInterface)) {
            unset($processors[$key]);
          }
        }

        if (count($processors)) {
          foreach ($processors as $processor) {
            /** @var \Drupal\search_api_solr\Solarium\Result\StreamDocument $document */
            foreach ($result as $document) {
              foreach ($document as $field_name => $field_value) {
                if (is_string($field_value)) {
                  $document->{$field_name} = $processor->decodeStreamingExpressionValue($field_value) ?: $field_value;
                }
                elseif (is_array($field_value)) {
                  foreach ($field_value as &$array_value) {
                    if (is_string($array_value)) {
                      $array_value = $processor->decodeStreamingExpressionValue($array_value) ?: $array_value;
                    }
                  }
                  unset($array_value);
                  $document->{$field_name} = $field_value;
                }
              }
            }
          }
        }
      }
    }
    catch (StreamException $e) {
      if ($query instanceof RefinableCacheableDependencyInterface) {
        // Avoid caching of an empty result in Search API and views.
        // @see https://www.drupal.org/project/search_api_solr/issues/3133997
        $query->mergeCacheMaxAge(0);
      }
      $message = $e->getMessage() . "\n" . ExpressionBuilder::indent($e->getExpression());
      if ($comment = $query->getOption('solr_streaming_expression_comment', FALSE)) {
        $message .= "\nComment: " . $comment;
      }
      throw new SearchApiSolrException($message, $e->getCode(), $e);
    }
    catch (\Exception $e) {
      if ($query instanceof RefinableCacheableDependencyInterface) {
        // Avoid caching of an empty result in Search API and views.
        // @see https://www.drupal.org/project/search_api_solr/issues/3133997
        $query->mergeCacheMaxAge(0);
      }
      throw $e;
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function executeGraphStreamingExpression(QueryInterface $query) {
    try {
      $stream_expression = $query->getOption('solr_streaming_expression', FALSE);
      if (!$stream_expression) {
        throw new SearchApiSolrException('Streaming expression missing.');
      }

      $connector = $this->getSolrConnector();
      if (!($connector instanceof SolrCloudConnectorInterface)) {
        throw new SearchApiSolrException('Streaming expression are only supported by a Solr Cloud connector.');
      }

      $index = $query->getIndex();
      $this->finalizeIndex($index);

      $graph = $connector->getGraphQuery();
      $graph->setExpression($stream_expression);
      $this->applySearchWorkarounds($graph, $query);

      return $connector->graph($graph, $this->getCollectionEndpoint($index));
    }
    catch (\Exception $e) {
      if ($query instanceof RefinableCacheableDependencyInterface) {
        // Avoid caching of an empty result in Search API and views.
        // @see https://www.drupal.org/project/search_api_solr/issues/3133997
        $query->mergeCacheMaxAge(0);
      }
      throw $e;
    }
  }

  /**
   * Creates an ID used as the unique identifier at the Solr server.
   *
   * This has to consist of both index and item ID. Optionally, the site hash is
   * also included.
   *
   * @param string $site_hash
   *   The site hash.
   * @param string $index_id
   *   The index ID.
   * @param string|int $item_id
   *   The item ID.
   *
   * @return string
   *   A unique identifier for the given item.
   */
  protected function createId($site_hash, $index_id, $item_id) {
    return "$site_hash-$index_id-$item_id";
  }

  /**
   * Returns the datasource configuration for the given index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index entity.
   *
   * @return array
   *   An array representing the datasource configuration.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getDatasourceConfig(IndexInterface $index) {
    $config = [];
    if ($index->isValidDatasource('solr_document')) {
      $config = $index->getDatasource('solr_document')->getConfiguration();
    }
    elseif ($index->isValidDatasource('solr_multisite_document')) {
      $config = $index->getDatasource('solr_multisite_document')->getConfiguration();
    }
    return $config;
  }

  /**
   * Returns a language-specific mapping from Drupal to Solr field names.
   *
   * @param string $language_id
   *   The language to get the mapping for.
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index entity.
   *
   * @return array
   *   The language-specific mapping from Drupal to Solr field names.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function formatSolrFieldNames($language_id, IndexInterface $index) {
    // Caching is done by getLanguageSpecificSolrFieldNames().
    // This array maps "local property name" => "solr doc property name".
    $field_mapping = [
      'search_api_relevance' => 'score',
      'search_api_random' => 'random',
      'boost_document' => 'boost_document',
    ];

    // Add the names of any fields configured on the index.
    $fields = $index->getFields();
    $fields += $this->getSpecialFields($index);
    foreach ($fields as $search_api_name => $field) {
      switch ($field->getDatasourceId()) {
        case 'solr_document':
          $field_mapping[$search_api_name] = $field->getPropertyPath();
          break;

        case 'solr_multisite_document':
          $field_mapping[$search_api_name] =
            Utility::encodeSolrName(
              preg_replace(
                '/^(t[a-z0-9]*[ms]' . SolrBackendInterface::SEARCH_API_SOLR_LANGUAGE_SEPARATOR . ')' . LanguageInterface::LANGCODE_NOT_SPECIFIED . '(.+)/',
                '$1' . $language_id . '$2',
                Utility::decodeSolrName($field->getPropertyPath())
              )
            );
          break;

        default:
          if (empty($field_mapping[$search_api_name])) {
            // Generate a field name; this corresponds with naming conventions
            // in our schema.xml.
            $type = $field->getType();

            if ('solr_text_suggester' === $type) {
              // Any field of this type will be indexed in the same Solr field.
              // The 'twm_suggest' is the backend for the suggester component.
              $field_mapping[$search_api_name] = 'twm_suggest';
              break;
            }

            if ('solr_text_spellcheck' === $type) {
              // Any field of this type will be indexed in the same Solr field.
              // Don't use the language separator here! This field name is used
              // without in solrconfig.xml.
              $field_mapping[$search_api_name] = 'spellcheck_' . str_replace('-', '_', $language_id);
              break;
            }

            $type_info = Utility::getDataTypeInfo($type);
            $pref = $type_info['prefix'] ?? '';
            if (strpos($pref, 't') === 0) {
              // All text types need to be treated as multiple because some
              // Search API processors produce boosted string tokens for
              // a single valued drupal field. We need to store such tokens and
              // their boost, too.
              // The dynamic field tm_* will become tm;en* for English.
              // Following this pattern we also have fall backs automatically:
              // - tm;de-AT_*
              // - tm;de_*
              // - tm_*
              // This concept bases on the fact that "longer patterns will be
              // matched first. If equal size patterns both match, the first
              // appearing in the schema will be used." This is not obvious from
              // the example above. But you need to take into account that the
              // real field name for solr will be encoded. So the real values
              // for the example above are:
              // - tm_X3b_de_X2d_AT_*
              // - tm_X3b_de_*
              // - tm_*
              // See also:
              // @see \Drupal\search_api_solr\Utility\Utility::encodeSolrName()
              // @see https://wiki.apache.org/solr/SchemaXml#Dynamic_fields
              $pref .= 'm' . SolrBackendInterface::SEARCH_API_SOLR_LANGUAGE_SEPARATOR . $language_id;
            }
            else {
              if ($this->fieldsHelper->isFieldIdReserved($search_api_name)) {
                $pref .= 's';
              }
              else {
                if ($field->getDataDefinition()->isList() || $this->isHierarchicalField($field)) {
                  $pref .= 'm';
                }
                elseif ($field->getDataDefinition() instanceof AggregatedFieldProperty) {
                  $pref .= $field->getDataDefinition()->isList() ? 'm' : 's';
                }
                else {
                  try {
                    // Returns the correct list of field definitions including
                    // processor-added properties.
                    $index_properties = $index->getPropertyDefinitions($field->getDatasourceId());
                    $pref .= $this->getPropertyPathCardinality($field->getPropertyPath(), $index_properties) != 1 ? 'm' : 's';
                  }
                  catch (SearchApiException $e) {
                    // Thrown by $field->getDatasource(). As all conditions for
                    // multiple values are not met, it seems to be a single
                    // value field. Note: If the assumption is wrong, Solr will
                    // throw exceptions when indexing this field. In this case
                    // you should add an explicit 'isList' => TRUE to your
                    // property or data definition! Or activate
                    // fallback_multiple in the advanced server settings.
                    $pref .= empty($this->configuration['fallback_multiple']) ? 's' : 'm';
                  }
                }
              }
            }
            $name = $pref . '_' . $search_api_name;
            $field_mapping[$search_api_name] = Utility::encodeSolrName($name);

            // Add a distance pseudo field for any location field. These fields
            // don't really exist in the solr core, but we tell solr to name the
            // distance calculation results that way. Later we directly pass
            // these as "fields" to Drupal and especially Views.
            if ($type === 'location') {
              // Solr returns the calculated distance value as a single decimal
              // value (even for multi-valued location fields). Therefore we
              // have to prefix the field name accordingly by fts_*.
              // This ensures that this field works as for sorting, too.
              // 'ft' is the prefix for decimal (at the moment).
              $dist_info = Utility::getDataTypeInfo('decimal');
              $field_mapping[$search_api_name . '__distance'] = Utility::encodeSolrName($dist_info['prefix'] . 's_' . $search_api_name . '__distance');
            }
          }
      }
    }

    if (Utility::hasIndexJustSolrDatasources($index)) {
      // No other datasource than solr_*, overwrite some search_api_* fields.
      $config = $this->getDatasourceConfig($index);
      $field_mapping['search_api_id'] = $config['id_field'];
      $field_mapping['search_api_language'] = $config['language_field'];
    }

    // Let modules adjust the field mappings.
    $event = new PostFieldMappingEvent($index, $field_mapping, $language_id);
    $this->dispatch($event);

    return $event->getFieldMapping();
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageSpecificSolrFieldNames($language_id, ?IndexInterface $index, $reset = FALSE) {
    static $field_names = [];

    if ($reset) {
      $field_names = [];
    }

    if ($index) {
      $index_id = $index->id();
      if (!isset($field_names[$index_id]) || !isset($field_names[$index_id][$language_id])) {
        $field_names[$index_id][$language_id] = $this->formatSolrFieldNames($language_id, $index);
      }

      return $field_names[$index_id][$language_id];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSolrFieldNamesKeyedByLanguage(array $language_ids, IndexInterface $index, $reset = FALSE) {
    $field_names = [];

    foreach ($language_ids as $language_id) {
      foreach ($this->getLanguageSpecificSolrFieldNames($language_id, $index, $reset) as $name => $solr_name) {
        $field_names[$name][$language_id] = $solr_name;
        // Just reset once.
        $reset = FALSE;
      }
    }

    return $field_names;
  }

  /**
   * {@inheritdoc}
   */
  public function getSolrFieldNames(IndexInterface $index, $reset = FALSE) {
    // Backwards compatibility.
    return $this->getLanguageSpecificSolrFieldNames(LanguageInterface::LANGCODE_NOT_SPECIFIED, $index, $reset);
  }

  /**
   * Computes the cardinality of a complete property path.
   *
   * @param string $property_path
   *   The property path of the property.
   * @param \Drupal\Core\TypedData\DataDefinitionInterface[] $properties
   *   The properties which form the basis for the property path.
   * @param int $cardinality
   *   The cardinality of the property path so far (for recursion).
   *
   * @return int
   *   The cardinality.
   */
  protected function getPropertyPathCardinality($property_path, array $properties, $cardinality = 1) {
    [$key, $nested_path] = SearchApiUtility::splitPropertyPath($property_path, FALSE);
    if (isset($properties[$key])) {
      $property = $properties[$key];
      if ($property instanceof FieldDefinitionInterface) {
        $storage = $property->getFieldStorageDefinition();
        if ($storage instanceof FieldStorageDefinitionInterface) {
          if ($storage->getCardinality() == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
            // Shortcut. We reached the maximum.
            return FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
          }
          $cardinality *= $storage->getCardinality();
        }
      }
      elseif ($property->isList() || $property instanceof ListDataDefinitionInterface) {
        // Lists have unspecified cardinality. Unfortunately BaseFieldDefinition
        // implements ListDataDefinitionInterface. So the safety net check for
        // this interface needs to be the last one!
        return FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
      }

      if (isset($nested_path)) {
        $property = $this->fieldsHelper->getInnerProperty($property);
        if ($property instanceof ComplexDataDefinitionInterface) {
          $cardinality = $this->getPropertyPathCardinality($nested_path, $this->fieldsHelper->getNestedProperties($property), $cardinality);
        }
      }
    }

    return $cardinality;
  }

  /**
   * Checks if a field is (potentially) hierarchical.
   *
   * Fields are (potentially) hierarchical if:
   * - they point to an entity type; and
   * - that entity type contains a property referencing the same type of entity
   *   (so that a hierarchy could be built from that nested property).
   *
   * @see \Drupal\search_api\Plugin\search_api\processor\AddHierarchy::getHierarchyFields()
   *
   * @return bool
   *   TRUE if the field is hierarchical, FALSE otherwise.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function isHierarchicalField(FieldInterface $field) {
    $definition = $field->getDataDefinition();
    if ($definition instanceof ComplexDataDefinitionInterface) {
      $properties = $this->fieldsHelper->getNestedProperties($definition);
      // The property might be an entity data definition itself.
      $properties[''] = $definition;
      foreach ($properties as $property) {
        $property = $this->fieldsHelper->getInnerProperty($property);
        if ($property instanceof EntityDataDefinitionInterface) {
          if ($this->hasHierarchicalProperties($property)) {
            return TRUE;
          }
        }
      }
    }

    return FALSE;
  }

  /**
   * Checks if hierarchical properties are nested on an entity-typed property.
   *
   * @param \Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface $property
   *   The property to be searched for hierarchical nested properties.
   *
   * @return bool
   *   TRUE if the property contains hierarchical properies, FALSE otherwise.
   *
   * @see \Drupal\search_api\Plugin\search_api\processor\AddHierarchy::findHierarchicalProperties()
   */
  protected function hasHierarchicalProperties(EntityDataDefinitionInterface $property) {
    $entity_type_id = $property->getEntityTypeId();

    // Check properties for potential hierarchy. Check two levels down, since
    // Core's entity references all have an additional "entity" sub-property for
    // accessing the actual entity reference, which we'd otherwise miss.
    foreach ($this->fieldsHelper->getNestedProperties($property) as $property_2) {
      $property_2 = $this->fieldsHelper->getInnerProperty($property_2);
      if ($property_2 instanceof EntityDataDefinitionInterface) {
        if ($property_2->getEntityTypeId() == $entity_type_id) {
          return TRUE;
        }
      }
      elseif ($property_2 instanceof ComplexDataDefinitionInterface) {
        foreach ($property_2->getPropertyDefinitions() as $property_3) {
          $property_3 = $this->fieldsHelper->getInnerProperty($property_3);
          if ($property_3 instanceof EntityDataDefinitionInterface) {
            if ($property_3->getEntityTypeId() == $entity_type_id) {
              return TRUE;
            }
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * Helper method for indexing.
   *
   * Adds $value with field name $key to the document $doc. The format of $value
   * is the same as specified in
   * \Drupal\search_api\Backend\BackendSpecificInterface::indexItems().
   *
   * @param \Solarium\QueryType\Update\Query\Document $doc
   *   The Solarium document.
   * @param string $key
   *   The key to use for the field.
   * @param array $values
   *   The values for the field.
   * @param string $type
   *   The field type.
   * @param array $boost_terms
   *   Reference to an array where special boosts per term should be stored.
   *
   * @return bool|float|int|string|null
   *   The first value of $values that has been added to the index.
   */
  protected function addIndexField(Document $doc, $key, array $values, $type, array &$boost_terms) {
    if (strpos($type, 'solr_text_') === 0) {
      $type = 'text';
    }

    if (empty($values)) {
      if ('text' !== $type || !$this->configuration['index_empty_text_fields']) {
        // Don't index empty values (i.e., when field is missing).
        return NULL;
      }
    }

    $first_value = NULL;

    // All fields.
    foreach ($values as $value) {
      if (NULL === $value && 'text' === $type && $this->configuration['index_empty_text_fields']) {
        // Index a dummy value to keep the number of total documents
        // containing a field consistent for IDF based similarity
        // calculations, especially for multilingual searches.
        $value = new TextValue(SolrBackendInterface::EMPTY_TEXT_FIELD_DUMMY_VALUE);
      }

      if (NULL !== $value) {
        switch ($type) {
          case 'boolean':
            $value = $value ? 'true' : 'false';
            break;

          case 'date':
            $value = $this->formatDate($value);
            if ($value === FALSE) {
              continue 2;
            }
            break;

          case 'solr_date_range':
            $start = $this->formatDate($value->getStart());
            $end = $this->formatDate($value->getEnd());
            $value = '[' . $start . ' TO ' . $end . ']';
            break;

          case 'integer':
            $value = (int) $value;
            break;

          case 'decimal':
            $value = (float) $value;
            break;

          case 'text':
            /** @var \Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface $value */
            $tokens = $value->getTokens();
            if (is_array($tokens) && !empty($tokens)) {
              // @todo Remove together with search_api_solr_legacy.
              $legacy_solr_version = FALSE;
              try {
                $connector = $this->getSolrConnector();
                if ($legacy_solr_version = (version_compare($connector->getSolrMajorVersion(), '6', '<')  && version_compare($connector->getSolrMajorVersion(), '4', '>='))) {
                  $boost = 0.0;
                }
              }
              catch (\Exception $e) {
              }

              foreach ($tokens as $token) {
                $value = $token->getText();

                if (is_object($value)) {
                  // It might happen that we get TranslatableMarkup here.
                  $value = (string) $value;
                }

                if (!$value && $this->configuration['index_empty_text_fields']) {
                  // Index a dummy value to keep the number of total documents
                  // containing a field consistent for IDF based similarity
                  // calculations, especially for multilingual searches.
                  $value = SolrBackendInterface::EMPTY_TEXT_FIELD_DUMMY_VALUE;
                }

                if ($value) {
                  if ($legacy_solr_version) {
                    // Boosting field values at index time is only supported in
                    // old Solr versions.
                    // @todo Remove together with search_api_solr_legacy.
                    if ($token->getBoost() > $boost) {
                      $boost = $token->getBoost();
                    }
                    $doc->addField($key, $value, $boost);
                  }
                  else {
                    $doc->addField($key, $value);

                    $boost = $token->getBoost();
                    if (0.0 != $boost && 1.0 != $boost) {
                      // This regular expressions are a first approach to
                      // isolate the terms to be boosted. It might be that
                      // there's some more sophisticated logic required here.
                      // The unicode mode is required to handle multibyte white
                      // spaces of languages like Japanese.
                      $terms = preg_split('/\s+/u', str_replace('|', ' ', $value));
                      foreach ($terms as $term) {
                        $len = mb_strlen($term);
                        // The length boundaries are defined as this for
                        // fieldType name="boost_term_payload" in schema.xml.
                        // Shorter or longer terms will be skipped anyway.
                        if ($len >= 2 && $len <= 100) {
                          if (!array_key_exists($term, $boost_terms) || $boost_terms[$term] < $boost) {
                            $boost_terms[$term] = $boost;
                          }
                        }
                      }
                    }
                  }
                  if (!$first_value) {
                    $first_value = $value;
                  }
                }
              }

              continue 2;
            }

            $value = $value->getText();
            if (is_object($value)) {
              // It might happen that we get TranslatableMarkup here.
              $value = (string) $value;
            }

            if (!$value && $this->configuration['index_empty_text_fields']) {
              // Index a dummy value to keep the number of total documents
              // containing a field consistent for IDF based similarity
              // calculations, especially for multilingual searches.
              $value = SolrBackendInterface::EMPTY_TEXT_FIELD_DUMMY_VALUE;
            }

            // No break, now we have a string.
          case 'string':
          default:
            // Keep $value as it is. Keep '0' string.
            if (!$value && $value !== '0') {
              continue 2;
            }

            if (is_object($value)) {
              // It might happen that we get TranslatableMarkup here.
              $value = (string) $value;
            }

        }

        $doc->addField($key, $value);
        if (!$first_value) {
          $first_value = $value;
        }
      }
    }

    return $first_value;
  }

  /**
   * Applies custom modifications to indexed Solr documents.
   *
   * This method allows subclasses to easily apply custom changes before the
   * documents are sent to Solr. The method is empty by default.
   *
   * @param \Solarium\QueryType\Update\Query\Document[] $documents
   *   An array of \Solarium\QueryType\Update\Query\Document\Document objects
   *   ready to be indexed, generated from $items array.
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index for which items are being indexed.
   * @param array $items
   *   An array of items being indexed.
   *
   * @see hook_search_api_solr_documents_alter()
   */
  protected function alterSolrDocuments(array &$documents, IndexInterface $index, array $items) {
  }

  /**
   * Extract results from a Solr response.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query object.
   * @param \Solarium\Core\Query\Result\ResultInterface $result
   *   A Solarium select response object.
   * @param $languages
   *
   * @return \Drupal\search_api\Query\ResultSetInterface
   *   A result set object.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function extractResults(QueryInterface $query, ResultInterface $result, $languages = []) {
    $index = $query->getIndex();
    $fields = $index->getFields(TRUE);
    $site_hash = $this->getTargetedSiteHash($index);
    // We can find the item ID and the score in the special 'search_api_*'
    // properties. Mappings are provided for these properties in
    // SearchApiSolrBackend::getSolrFieldNames().
    $language_unspecific_field_names = $this->getSolrFieldNames($index);
    $id_field = $language_unspecific_field_names['search_api_id'];
    $score_field = $language_unspecific_field_names['search_api_relevance'];
    $language_field = $language_unspecific_field_names['search_api_language'];
    $fallback_language_field = $language_unspecific_field_names['language_with_fallback'] ?? NULL;
    $backend_defined_fields = [];

    /** @var \Solarium\Component\Result\Debug\DocumentSet $explain */
    $explain = NULL;
    $search_api_retrieved_field_values = $query->getOption('search_api_retrieved_field_values', []);
    if (in_array('search_api_solr_score_debugging', $search_api_retrieved_field_values)) {
      if ($debug = $result->getDebug()) {
        $explain = $debug->getExplain();
        $backend_defined_fields = $this->getBackendDefinedFields($query->getIndex());
      }
    }

    // Set up the results array.
    $result_set = $query->getResults();
    $result_set->setExtraData('search_api_solr_response', $result->getData());

    // In some rare cases (e.g., MLT query with nonexistent ID) the response
    // will be NULL.
    $is_grouping = $result instanceof Result && $result->getGrouping();
    if (!$result->getResponse() && !$is_grouping) {
      $result_set->setResultCount(0);
      return $result_set;
    }

    // If field collapsing has been enabled for this query, we need to process
    // the results differently.
    $grouping = $query->getOption('search_api_grouping');
    if (!empty($grouping['use_grouping'])) {
      $docs = [];
      $resultCount = 0;
      if ($result_set->hasExtraData('search_api_solr_response')) {
        $response = $result_set->getExtraData('search_api_solr_response');
        foreach ($grouping['fields'] as $field) {
          // @todo handle languages
          $solr_field_name = $language_unspecific_field_names[$field];
          if (!empty($response['grouped'][$solr_field_name])) {
            $resultCount = count($response['grouped'][$solr_field_name]);
            foreach ($response['grouped'][$solr_field_name]['groups'] as $group) {
              foreach ($group['doclist']['docs'] as $doc) {
                $docs[] = $doc;
              }
            }
          }
        }
        // Set a default number then get the groups number if possible.
        $result_set->setResultCount($resultCount);
        if (count($grouping['fields']) == 1) {
          $field = reset($grouping['fields']);
          // @todo handle languages
          $solr_field_name = $language_unspecific_field_names[$field];
          if (isset($response['grouped'][$solr_field_name]['ngroups'])) {
            $result_set->setResultCount($response['grouped'][$solr_field_name]['ngroups']);
          }
        }
      }
    }
    else {
      $result_set->setResultCount($result->getNumFound());
      $docs = $result->getDocuments();
    }

    // Add each search result to the results array.
    /** @var \Solarium\QueryType\Select\Result\Document $doc */
    foreach ($docs as $doc) {
      if (is_array($doc)) {
        $doc_fields = $doc;
      }
      else {
        /** @var \Solarium\QueryType\Select\Result\Document $doc */
        $doc_fields = $doc->getFields();
      }
      if (empty($doc_fields[$id_field])) {
        throw new SearchApiSolrException(sprintf('The result does not contain the essential ID field "%s".', $id_field));
      }

      // For an unknown reason we sometimes get arrays here.
      // @see https://www.drupal.org/project/search_api_solr/issues/3281703
      // @see https://www.drupal.org/project/search_api_solr/issues/3320713
      $item_id = $doc_fields[$id_field];
      if (is_array($item_id)) {
        $item_id = current($item_id);
      }

      $hash = NULL;
      if (isset($doc_fields['hash'])) {
        $hash = $doc_fields['hash'];
        if (is_array($hash)) {
          $hash = current($hash);
        }
      }

      // For items coming from a different site, we need to adapt the item ID.
      if (!is_null($hash) && !$this->configuration['site_hash'] && $hash != $site_hash) {
        $item_id = $hash . '--' . $item_id;
      }

      $result_item = NULL;
      if (Utility::hasIndexJustSolrDatasources($index)) {
        $datasource = '';
        if ($index->isValidDatasource('solr_document')) {
          $datasource = 'solr_document';
        }
        elseif ($index->isValidDatasource('solr_multisite_document')) {
          $datasource = 'solr_multisite_document';
        }
        /** @var \Drupal\search_api_solr\SolrDocumentFactoryInterface $solr_document_factory */
        $solr_document_factory = \Drupal::getContainer()->get($datasource . '.factory');
        $result_item = $this->fieldsHelper->createItem($index, $datasource . '/' . $item_id);
        // Create the typed data object for the Item immediately after the query
        // has been run. Doing this now can prevent the Search API from having
        // to query for individual documents later.
        $result_item->setOriginalObject($solr_document_factory->create($result_item));
      }
      else {
        $result_item = $this->fieldsHelper->createItem($index, $item_id);
      }

      $language_id = '';

      if ($fallback_language_field && !empty($languages) && isset($doc_fields[$fallback_language_field])) {
        $fallback_languages = $doc_fields[$fallback_language_field];
        $language_id = array_intersect($languages, $fallback_languages);
      }

      if (!$language_id && $language_field && isset($doc_fields[$language_field])) {
        $language_id = $doc_fields[$language_field];
      }

      // For an unknown reason we sometimes get an array here. See
      // https://www.drupal.org/project/search_api_solr/issues/3281703
      // Fallback languages are arrays as well.
      if (is_array($language_id)) {
        $language_id = reset($language_id);
      }

      if ($language_id) {
        $result_item->setLanguage($language_id);
        $field_names = $this->getLanguageSpecificSolrFieldNames($language_id, $index);
      }
      else {
        $field_names = $language_unspecific_field_names;
      }

      $result_item->setExtraData('search_api_solr_document', $doc);

      if (isset($doc_fields[$score_field])) {
        $result_item->setScore($doc_fields[$score_field]);
        unset($doc_fields[$score_field]);
      }
      if (!in_array($id_field, $search_api_retrieved_field_values)) {
        unset($doc_fields[$id_field]);
      }
      // The language field should not be removed. We keep it in the values as
      // well for backward compatibility and for easy access.
      // Extract properties from the Solr document, translating from Solr to
      // Search API property names. This reverses the mapping in
      // SearchApiSolrBackend::getSolrFieldNames().
      foreach ($field_names as $search_api_property => $solr_property) {
        if (isset($doc_fields[$solr_property]) && isset($fields[$search_api_property])) {
          $doc_field = is_array($doc_fields[$solr_property]) ? $doc_fields[$solr_property] : [$doc_fields[$solr_property]];
          $field = clone $fields[$search_api_property];
          foreach ($doc_field as &$value) {
            // The prefixes returned by Utility::getDataTypeInfo() are suitable
            // even for non Drupal Solr Documents here.
            $type_info = Utility::getDataTypeInfo($field->getType()) + ['prefix' => '_'];
            switch (substr($type_info['prefix'], 0, 1)) {
              case 'd':
                // Field type conversions
                // Date fields need some special treatment to become valid date
                // values (i.e., timestamps) again.
                if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value)) {
                  $value = strtotime($value);
                }
                break;

              case 't':
                if ($this->configuration['index_empty_text_fields'] && SolrBackendInterface::EMPTY_TEXT_FIELD_DUMMY_VALUE === $value) {
                  // Don't add the EMPTY_TEXT_FIELD_DUMMY_VALUE to the search
                  // search result field. Continue with next value.
                  // @see addIndexField().
                  continue 2;
                }

                $value = new TextValue($value);
            }
          }
          unset($value);
          $field->setValues($doc_field);
          $result_item->setField($search_api_property, $field);
        }
      }

      $solr_id = Utility::hasIndexJustSolrDatasources($index) ?
        str_replace('solr_document/', '', $result_item->getId()) :
        $this->createId($this->getTargetedSiteHash($index), $this->getTargetedIndexId($index), $result_item->getId());
      $this->getHighlighting($result->getData(), $solr_id, $result_item, $field_names);

      if ($explain) {
        if ($explain_doc = $explain->getDocument($solr_id)) {
          $backend_defined_fields['search_api_solr_score_debugging']->setValues([$explain_doc->__toString()]);
          $result_item->setField('search_api_solr_score_debugging', clone $backend_defined_fields['search_api_solr_score_debugging']);
        }
      }

      $result_set->addResultItem($result_item);
    }

    return $result_set;
  }

  /**
   * Extracts facets from a Solarium result set.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search query.
   * @param \Solarium\QueryType\Select\Result\Result $resultset
   *   A Solarium select response object.
   *
   * @return array
   *   An array describing facets that apply to the current results.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function extractFacets(QueryInterface $query, Result $resultset) {
    $event = new PreExtractFacetsEvent($query, $resultset);
    $this->dispatch($event);
    $resultset = $event->getSolariumResult();

    if (!$resultset->getFacetSet()) {
      return [];
    }

    $field_names = $this->getSolrFieldNames($query->getIndex());
    $connector = $this->getSolrConnector();
    $solr_version = $connector->getSolrVersion();

    $facets = [];
    $index = $query->getIndex();
    $fields = $index->getFields();

    $extract_facets = $query->getOption('search_api_facets', []);

    if ($facet_fields = $resultset->getFacetSet()->getFacets()) {
      foreach ($extract_facets as $delta => $info) {
        $field = $field_names[$info['field']];
        if (!empty($facet_fields[$field])) {
          $min_count = $info['min_count'];
          $terms = $facet_fields[$field]->getValues();
          if ($info['missing']) {
            // We have to correctly incorporate the "_empty_" term.
            // This will ensure that the term with the least results is dropped,
            // if the limit would be exceeded.
            if (isset($terms[''])) {
              if ($terms[''] < $min_count) {
                unset($terms['']);
              }
              else {
                arsort($terms);
                if ($info['limit'] > 0 && count($terms) > $info['limit']) {
                  array_pop($terms);
                }
              }
            }
          }
          elseif (isset($terms[''])) {
            unset($terms['']);
          }
          $type = isset($fields[$info['field']]) ? $fields[$info['field']]->getType() : 'string';
          foreach ($terms as $term => $count) {
            if ($count >= $min_count) {
              if ($term === '') {
                $term = '!';
              }
              elseif ($type === 'boolean') {
                if ($term === 'true') {
                  $term = '"1"';
                }
                elseif ($term === 'false') {
                  $term = '"0"';
                }
              }
              elseif ($type === 'date') {
                $term = $term ? '"' . strtotime($term) . '"' : NULL;
              }
              else {
                $term = "\"$term\"";
              }
              if ($term) {
                $facets[$delta][] = [
                  'filter' => $term,
                  'count' => $count,
                ];
              }
            }
          }
          if (empty($facets[$delta])) {
            unset($facets[$delta]);
          }
        }
      }
    }

    $result_data = $resultset->getData();
    if (isset($result_data['facet_counts']['facet_queries'])) {
      $spatials = $query->getOption('search_api_location');
      if ($spatials !== NULL) {
        foreach ($result_data['facet_counts']['facet_queries'] as $key => $count) {
          // This special key is defined in setSpatial().
          if (!preg_match('/^spatial-(.*)-(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)$/', $key, $matches)) {
            continue;
          }
          if (empty($extract_facets[$matches[1]])) {
            continue;
          }
          $facet = $extract_facets[$matches[1]];
          if ($count >= $facet['min_count']) {
            $facets[$matches[1]][] = [
              'filter' => "[{$matches[2]} {$matches[3]}]",
              'count' => $count,
            ];
          }
        }
      }
    }
    // Extract heatmaps.
    if (isset($result_data['facet_counts']['facet_heatmaps'])) {
      $spatials = $query->getOption('search_api_rpt');
      if ($spatials !== NULL) {
        foreach ($result_data['facet_counts']['facet_heatmaps'] as $key => $value) {
          if (!preg_match('/^rpts_(.*)$/', $key, $matches)) {
            continue;
          }
          if (empty($extract_facets[$matches[1]])) {
            continue;
          }
          $heatmaps = [];
          if (version_compare($solr_version, '7.5', '>=')) {
            $heatmaps = $value['counts_ints2D'];
          }
          else {
            $heatmaps = array_slice($value, 15);
          }

          $heatmap = [];
          array_walk_recursive($heatmaps, function ($heatmaps) use (&$heatmap) {
            $heatmap[] = $heatmaps;
          });
          $count = array_sum($heatmap);
          $facets[$matches[1]][] = [
            'filter' => $value,
            'count' => $count,
          ];
        }
      }
    }

    $event = new PostExtractFacetsEvent($query, $resultset, $facets);
    $this->dispatch($event);

    return $event->getFacets();
  }

  /**
   * Serializes a query's conditions as Solr filter queries.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query to get the conditions from.
   * @param array $options
   *   The query options.
   *
   * @return array
   *   Array of filter query strings.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getFilterQueries(QueryInterface $query, array &$options) {
    return $this->createFilterQueries($query->getConditionGroup(), $options, $query);
  }

  /**
   * Recursively transforms conditions into a flat array of Solr filter queries.
   *
   * @param \Drupal\search_api\Query\ConditionGroupInterface $condition_group
   *   The group of conditions.
   * @param array $options
   *   The query options.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query to apply the filter queries to.
   * @param array $language_ids
   *   (optional) The language IDs required for recursion. Should be empty on
   *   initial call!
   *
   * @return array
   *   Array of filter query strings.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function createFilterQueries(ConditionGroupInterface $condition_group, array &$options, QueryInterface $query, array $language_ids = []) {
    static $index_fields = [];
    static $index_fulltext_fields = [];

    $index = $query->getIndex();
    $index_id = $index->id();

    if (empty($language_ids)) {
      // Reset.
      unset($index_fields[$index_id]);
      unset($index_fulltext_fields[$index_id]);
    }

    if (!isset($index_fields[$index_id])) {
      $index_fields[$index_id] = $index->getFields(TRUE) + $this->getSpecialFields($index);
    }

    if (!isset($index_fulltext_fields[$index_id])) {
      $index_fulltext_fields[$index_id] = $index->getFulltextFields();
    }

    // If there's a language condition take this one and keep it for nested
    // conditions until we get a new language condition.
    $conditions = $condition_group->getConditions();
    foreach ($conditions as $condition) {
      if ($condition instanceof ConditionInterface) {
        $field = $condition->getField();
        $use_condition_languages = ('search_api_language' === $field);
        if (!$use_condition_languages) {
          if ($field_instance = $query->getIndex()->getField($field)) {
            $dataDefinition = $field_instance->getDataDefinition();
            if ($dataDefinition instanceof ProcessorProperty && $dataDefinition->getProcessorId() === 'language_with_fallback') {
              $use_condition_languages = TRUE;
            }
          }
        }

        if ($use_condition_languages) {
          $language_ids = $condition->getValue();
          if ($language_ids && !is_array($language_ids)) {
            $language_ids = [$language_ids];
          }
        }
      }
    }

    // If there's no language condition on the first level, take the one from
    // the query.
    if (!$language_ids) {
      $language_ids = Utility::ensureLanguageCondition($query);
    }

    if (!$language_ids) {
      throw new SearchApiSolrException('Unable to create filter queries if no language is set on any condition or the query itself.');
    }

    $solr_fields = $this->getSolrFieldNamesKeyedByLanguage($language_ids, $index);

    $fq = [];

    foreach ($conditions as $condition) {
      if ($condition instanceof ConditionInterface) {
        // Nested condition.
        $field = $condition->getField();
        if (!isset($solr_fields[$field])) {
          throw new SearchApiException("Filter term on unknown or unindexed field $field.");
        }
        $value = $condition->getValue();
        $filter_query = '';

        if (in_array($field, $index_fulltext_fields[$index_id])) {
          if ($value) {
            if (empty($language_ids)) {
              throw new SearchApiException('Conditon on fulltext field without corresponding condition on search_api_language detected.');
            }

            // Fulltext fields.
            $parse_mode_id = $query->getParseMode()->getPluginId();
            $keys = [
              '#conjunction' => 'OR',
              '#negation' => $condition->getOperator() === '<>',
            ];
            switch ($parse_mode_id) {
              // This is a hack. We assume that the user filters for any term /
              // phrase. But this prevents an explicit selection of all terms.
              // @see https://www.drupal.org/project/search_api/issues/2991134
              case 'terms':
              case 'phrase':
              case 'sloppy_phrase':
              case 'sloppy_terms':
              case 'fuzzy_terms':
              case 'edismax':
                if (is_array($value)) {
                  $keys += $value;
                }
                else {
                  $keys[] = $value;
                }
                break;

              case 'direct':
                $keys = $value;
                break;

              default:
                throw new SearchApiSolrException('Incompatible parse mode.');
            }
            $settings = Utility::getIndexSolrSettings($index);
            $filter_query = Utility::flattenKeys(
              $keys,
              $solr_fields[$field],
              $parse_mode_id,
              $settings['term_modifiers']
            );
          }
          else {
            // Fulltext fields checked against NULL.
            $nested_fqs = [];
            foreach ($solr_fields[$field] as $solr_field) {
              $nested_fqs[] = [
                'query' => $this->createFilterQuery($solr_field, $value, $condition->getOperator(), $index_fields[$index_id][$field], $options),
                'tags' => $condition_group->getTags(),
              ];
            }
            $fq[] = $this->reduceFilterQueries($nested_fqs, new ConditionGroup(
              '=' === $condition->getOperator() ? 'AND' : 'OR',
              $condition_group->getTags()
            ));
          }
        }
        else {
          // Non-fulltext fields.
          $filter_query = $this->createFilterQuery(reset($solr_fields[$field]), $value, $condition->getOperator(), $index_fields[$index_id][$field], $options);
        }

        if ($filter_query) {
          $fq[] = [
            [
              'query' => $filter_query,
              'tags' => $condition_group->getTags(),
            ],
          ];
        }
      }
      else {
        // Nested condition group.
        $nested_fqs = $this->createFilterQueries($condition, $options, $query, $language_ids);
        $fq[] = $this->reduceFilterQueries($nested_fqs, $condition);
      }
    }

    if ($fq) {
      return array_merge(...$fq);
    }
    return [];
  }

  /**
   * Reduces an array of filter queries to an array containing one filter query.
   *
   * The queries will be logically combined and their tags will be merged.
   *
   * @param array $filter_queries
   *   The array of filter queries.
   * @param \Drupal\search_api\Query\ConditionGroupInterface $condition_group
   *   The group of conditions.
   * @param bool $last
   *   (optional) If TRUE, the reduced filter query will be wrapped into
   *   parentheses when multiple filter queries are passed.
   *   Defaults to FALSE.
   *
   * @return array
   *   The reduced array of filter queries.
   */
  protected function reduceFilterQueries(array $filter_queries, ConditionGroupInterface $condition_group, $last = FALSE) {
    $fq = [];
    if (count($filter_queries) > 1) {
      $queries = [];
      $tags = [];
      $pre = $condition_group->getConjunction() === 'OR' ? '' : '+';
      foreach ($filter_queries as $nested_fq) {
        if (strpos($nested_fq['query'], '-') !== 0) {
          $queries[] = $pre . $nested_fq['query'];
        }
        elseif (!$pre) {
          $queries[] = '(' . $nested_fq['query'] . ')';
        }
        else {
          $queries[] = $nested_fq['query'];
        }
        $tags += $nested_fq['tags'];
      }
      $fq[] = [
        'query' => (!$last ? '(' : '') . implode(' ', $queries) . (!$last ? ')' : ''),
        'tags' => array_unique($tags + $condition_group->getTags()),
      ];
    }
    elseif (!empty($filter_queries)) {
      $fq[] = [
        'query' => $filter_queries[0]['query'],
        'tags' => array_unique($filter_queries[0]['tags'] + $condition_group->getTags()),
      ];
    }

    return $fq;
  }

  /**
   * Create a single search query string.
   *
   * @return string|null
   *   A filter query.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function createFilterQuery($field, $value, $operator, FieldInterface $index_field, array &$options) {
    if (!is_array($value)) {
      $value = [$value];
    }
    elseif (empty($value)) {
      // An empty array is an invalid value, empty strings or NULL is already
      // accepted above.
      throw new \InvalidArgumentException('An empty array is not allowed as value.');
    }

    foreach ($value as &$v) {
      if ('*' === $v) {
        if (!in_array($operator, ['=', 'BETWEEN', 'NOT BETWEEN'])) {
          throw new SearchApiSolrException('Unsupported operator for wildcard searches');
        }
        elseif (in_array($operator, ['BETWEEN', 'NOT BETWEEN'])) {
          // Range queries treat NULL as '*' in solarium.
          $v = NULL;
        }
      }
      elseif (NULL === $v && in_array($operator, ['BETWEEN', 'NOT BETWEEN'])) {
        // Range queries treat NULL as '*' in solarium.
      }
      elseif (NULL !== $v || !in_array($operator, ['=', '<>', 'IN', 'NOT IN'])) {
        $v = $this->formatFilterValue($v, $index_field);
        // NULL values are now converted to empty strings.
      }
    }
    unset($v);

    // In case of an "erroneous" query that only provides a single value in
    // combination with a multi-value operator, convert it into a single value
    // and a single value operator.
    if (1 === count($value)) {
      $value = array_shift($value);

      switch ($operator) {
        case 'IN':
        case 'BETWEEN':
          $operator = '=';
          break;

        case 'NOT IN':
        case 'NOT BETWEEN':
          $operator = '<>';
          break;
      }
    }

    if (NULL !== $value && isset($options['search_api_location'])) {
      foreach ($options['search_api_location'] as &$spatial) {
        if (!empty($spatial['field']) && $index_field->getFieldIdentifier() == $spatial['field']) {
          // Spatial filter queries need modifications to the query itself.
          // Therefore, we just store the parameters and let them be handled
          // later.
          // @see setSpatial()
          // @see createLocationFilterQuery()
          $spatial['filter_query_conditions'] = [
            'field' => $field,
            'value' => $value,
            'operator' => $operator,
          ];
          return NULL;
        }
      }
      unset($spatial);
    }

    switch ($operator) {
      case '<>':
        if (NULL === $value) {
          if ('location' === $index_field->getType()) {
            return $field . ':[-90,-180 TO 90,180]';
          }
          return $this->queryHelper->rangeQuery($field, NULL, NULL);
        }
        return '(*:* -' . $field . ':' . $this->queryHelper->escapePhrase($value) . ')';

      case '<':
        return $this->queryHelper->rangeQuery($field, NULL, $value, FALSE);

      case '<=':
        return $this->queryHelper->rangeQuery($field, NULL, $value);

      case '>=':
        return $this->queryHelper->rangeQuery($field, $value, NULL);

      case '>':
        return $this->queryHelper->rangeQuery($field, $value, NULL, FALSE);

      case 'BETWEEN':
        if ('location' === $index_field->getType()) {
          return $this->queryHelper->rangeQuery($field, array_shift($value), array_shift($value), TRUE, FALSE);
        }
        return $this->queryHelper->rangeQuery($field, array_shift($value), array_shift($value));

      case 'NOT BETWEEN':
        if ('location' === $index_field->getType()) {
          return '(+' . $field . ':[-90,-180 TO 90,180] -' . $this->queryHelper->rangeQuery($field, array_shift($value), array_shift($value), TRUE, FALSE) . ')';
        }
        return '(*:* -' . $this->queryHelper->rangeQuery($field, array_shift($value), array_shift($value)) . ')';

      case 'IN':
        $parts = [];
        $null = FALSE;
        foreach ($value as $v) {
          if (NULL === $v) {
            $null = TRUE;
            break;
          }
          $parts[] = $this->queryHelper->escapePhrase($v);
        }
        if ($null) {
          // @see https://stackoverflow.com/questions/4238609/how-to-query-solr-for-empty-fields/28859224#28859224
          return '(*:* -' . $this->queryHelper->rangeQuery($field, NULL, NULL) . ')';
        }
        return $field . ':(' . implode(' ', $parts) . ')';

      case 'NOT IN':
        $parts = [];
        $null = FALSE;
        foreach ($value as $v) {
          if (NULL === $v) {
            $null = TRUE;
          }
          else {
            $parts[] = $this->queryHelper->escapePhrase($v);
          }
        }
        return '(' . ($null ? $this->queryHelper->rangeQuery($field, NULL, NULL) : '*:*') . ($parts ? ' -' . $field . ':(' . implode(' ', $parts) . ')' : '') . ')';

      case '=':
      default:
        if (NULL === $value) {
          // @see https://stackoverflow.com/questions/4238609/how-to-query-solr-for-empty-fields/28859224#28859224
          return '(*:* -' . $this->queryHelper->rangeQuery($field, NULL, NULL) . ')';
        }
        return $field . ':' . ($value === '*' ? '*' : $this->queryHelper->escapePhrase($value));
    }
  }

  /**
   * Create a single search query string.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function createLocationFilterQuery(&$spatial) {
    $spatial_method = (
      isset($spatial['method']) &&
      in_array($spatial['method'], ['geofilt', 'bbox'])
    ) ? $spatial['method'] : 'geofilt';
    $value = $spatial['filter_query_conditions']['value'];

    switch ($spatial['filter_query_conditions']['operator']) {
      case '<':
      case '<=':
        $spatial['radius'] = $value;
        return '{!' . $spatial_method . '}';

      case '>':
      case '>=':
        $spatial['min_radius'] = $value;
        return "{!frange l=$value}geodist()";

      case 'BETWEEN':
        $spatial['min_radius'] = array_shift($value);
        $spatial['radius'] = array_shift($value);
        return '{!frange l=' . $spatial['min_radius'] . ' u=' . $spatial['radius'] . '}geodist()';

      case '=':
      case '<>':
      case 'NOT BETWEEN':
      case 'IN':
      case 'NOT IN':
      default:
        throw new SearchApiSolrException('Unsupported operator for location queries');
    }
  }

  /**
   * Format a value for filtering on a field of a specific type.
   *
   * All values that are used with text and string based Search API field types
   * will be escaped. But for other types.
   *
   * @param bool|float|int|string|null $value
   *   The value to be formatted.
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The Search API field the value should be formatted for.
   * @param string|null $type
   *   Optionally force a different Search API field type the value should be
   *   formatted for.
   *
   * @return float|int|string
   *   The formatted value.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function formatFilterValue($value, FieldInterface $field, ?string $type = NULL) {
    $value = trim($value ?? '');

    if (!$type) {
      $type = $field->getType();
    }

    switch ($type) {
      case 'boolean':
        $value = $value ? 'true' : 'false';
        break;

      case 'date':
        $value = $this->formatDate($value);
        if ($value === FALSE) {
          throw new SearchApiSolrException('Unsupported date value');
        }
        break;

      case 'decimal':
        $value = (float) $value;
        break;

      case 'integer':
        $value = (int) $value;
        break;

      case 'location':
        // Solr type point must be in 'lat, lon' or 'x y'. So it is a string.
        // Unfortunately search_api_location doesn't set the correct fallback
        // type.
      case 'string':
      case 'text':
        // In case these types are used as fallback types, don't touch the
        // value. Such values should be escaped by the caller. A NULL value has
        // been converted to an empty string at the beginning of this function.
        break;

      default:
        $fallback_type = $field->getDataTypePlugin()->getFallbackType();
        if ($fallback_type) {
          if ($fallback_type !== $type) {
            $value = $this->formatFilterValue($value, $field, $fallback_type);
          }
          else {
            throw new SearchApiSolrException('Unable to format field type ' . $type . '. Fallback type is not valid.');
          }
        }
        else {
          throw new SearchApiSolrException('Unable to format field type ' . $type . '. No fallback type specified.');
        }
    }

    return $value;
  }

  /**
   * Tries to format given date with solarium query helper.
   *
   * @param int|string $input
   *   The date to format (timestamp or string).
   *
   * @return bool|string
   *   The formatted date as string or FALSE in case of invalid input.
   */
  public function formatDate($input) {
    try {
      $input = is_numeric($input) ? (int) $input : new \DateTime($input, timezone_open(DateTimeItemInterface::STORAGE_TIMEZONE));
    }
    catch (\Exception $e) {
      return FALSE;
    }
    return $this->queryHelper->formatDate($input);
  }

  /**
   * Helper method for creating the facet field parameters.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query.
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The Solarium query.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function setFacets(QueryInterface $query, Query $solarium_query) {
    static $index_fulltext_fields = [];

    $this->dispatch(new PreSetFacetsEvent($query, $solarium_query));

    $facets = $query->getOption('search_api_facets', []);
    if (empty($facets)) {
      return;
    }

    $index = $query->getIndex();
    $index_id = $index->id();
    $field_names = $this->getSolrFieldNames($index);

    $facet_set = $solarium_query->getFacetSet();
    $facet_set->setSort('count');
    $facet_set->setLimit(10);
    $facet_set->setMinCount(1);
    $facet_set->setMissing(FALSE);

    foreach ($facets as $info) {
      if (empty($field_names[$info['field']])) {
        continue;
      }
      $solr_field = $field_names[$info['field']];
      $facet_field = NULL;

      // Backward compatibility for facets.
      $info += ['query_type' => 'search_api_string'];

      switch ($info['query_type']) {
        case 'search_api_granular':
          $facet_field = $facet_set->createFacetRange([
            'local_key' => $solr_field,
            'field' => $solr_field,
            'start' => $info['min_value'],
            'end' => $info['max_value'],
            'gap' => $info['granularity'],
          ]);
          $includes = [];
          if ($info['include_lower']) {
            $includes[] = 'lower';
          }
          if ($info['include_upper']) {
            $includes[] = 'upper';
          }
          if ($info['include_edges']) {
            $includes[] = 'edge';
          }
          $facet_field->setInclude($includes);
          break;

        case 'search_api_string':
        default:
          if (!isset($index_fulltext_fields[$index_id])) {
            $index_fulltext_fields[$index_id] = $index->getFulltextFields();
          }

          if (in_array($info['field'], $index_fulltext_fields[$index_id])) {
            // @todo For sure Solr can handle it. But it was a trade-off for
            //   3.x. For full multilingual support, fulltext fields are
            //   indexed in language specific fields. In case of facets it is
            //   hard to detect which language specific fields should be
            //   considered. And the results have to be combined across
            //   languages. One way to implement it might be facet queries.
            //   For now, log an error and throw an exception.
            $msg = 'Facets for fulltext fields are not yet supported. Consider converting the following field to a string or indexing it one more time as string:';
            $this->getLogger()->error($msg . ' @field', ['@field' => $info['field']]);
            throw new SearchApiSolrException(sprintf($msg . ' %s', $info['field']));
          }
          else {
            // Create the Solarium facet field object.
            $facet_field = $facet_set->createFacetField($solr_field)->setField($solr_field);
          }

          // Set limit, unless it's the default.
          if ($info['limit'] != 10) {
            $limit = $info['limit'] ? $info['limit'] : -1;
            $facet_field->setLimit($limit);
          }
          // Set missing, if specified.
          if ($info['missing']) {
            $facet_field->setMissing(TRUE);
          }
          else {
            $facet_field->setMissing(FALSE);
          }
      }

      // For "OR" facets, add the expected tag for exclusion.
      if (isset($info['operator']) && strtolower($info['operator']) === 'or') {
        // The tag "facet:field_name" is defined by the facets module. Therefore
        // we have to use the Search API field name here to create the same tag.
        // @see \Drupal\facets\QueryType\QueryTypeRangeBase::execute()
        // @see https://cwiki.apache.org/confluence/display/solr/Faceting#Faceting-LocalParametersforFaceting
        $facet_field->getLocalParameters()->clearExcludes()->addExcludes(['facet:' . $info['field']]);
      }

      // Set mincount, unless it's the default.
      if ($info['min_count'] != 1) {
        if (0 === (int) $info['min_count']) {
          $connector = $this->getSolrConnector();
          $solr_version = $connector->getSolrVersion();
          if (
            version_compare($solr_version, '7.0', '>=') &&
            preg_match('/^[ifpdh]/', $solr_field, $matches)
          ) {
            // Trie based field types were deprecated in Solr 6 and with Solr 7
            // we switched to the point based equivalents. But lucene doesn't
            // support a mincount of "0" for these field types.
            $msg = 'Facets having a mincount of "0" are not yet supported by Solr for point based field types. Consider converting the following field to a string or indexing it one more time as string:';
            $this->getLogger()->error($msg . ' @field', ['@field' => $info['field']]);
            throw new SearchApiSolrException(sprintf($msg . ' %s', $info['field']));
          }
        }

        $facet_field->setMinCount($info['min_count']);
      }
    }

    $this->dispatch(new PostSetFacetsEvent($query, $solarium_query));
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   *
   * @see \Drupal\search_api_autocomplete\AutocompleteBackendInterface
   */
  public function getAutocompleteSuggestions(QueryInterface $query, SearchInterface $search, string $incomplete_key, string $user_input): array {
    $suggestions = [];
    if ($solarium_query = $this->getAutocompleteQuery($this, $incomplete_key, $user_input)) {
      try {
        $suggestion_factory = new SuggestionFactory($user_input);
        Utility::ensureLanguageCondition($query);
        $this->setAutocompleteTermQuery($query, $solarium_query, $incomplete_key);
        // Allow modules to alter the solarium autocomplete query.
        $event = new PreAutocompleteTermsQueryEvent($query, $solarium_query);
        $this->dispatch($event);
        $result = $this->getSolrConnector()->autocomplete($solarium_query, $this->getCollectionEndpoint($query->getIndex()));
        $suggestions = $this->getAutocompleteTermSuggestions($result, $suggestion_factory, $incomplete_key);
        // Filter out duplicate suggestions.
        $this->filterDuplicateAutocompleteSuggestions($suggestions);
      }
      catch (SearchApiException $e) {
        $this->logException($e);
      }
    }

    return $suggestions;
  }

  /**
   * Get the fields to search for autocomplete terms.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query representing the completed user input so far.
   *
   * @return array
   *   An array of fulltext field definitions.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getAutocompleteFields(QueryInterface $query) {
    $fl = [];
    $field_names = $this->getSolrFieldNamesKeyedByLanguage(Utility::ensureLanguageCondition($query), $query->getIndex());
    // We explicit allow to get terms from twm_suggest. Therefore we call
    // parent::getQueryFulltextFields() to not filter twm_suggest.
    foreach (parent::getQueryFulltextFields($query) as $fulltext_field) {
      $fl[] = array_values($field_names[$fulltext_field]);
    }
    return array_unique(array_merge(...$fl));
  }

  /**
   * Set the term parameters for the solarium autocomplete query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query representing the completed user input so far.
   * @param \Drupal\search_api_solr\Solarium\Autocomplete\Query $solarium_query
   *   A Solarium autocomplete query.
   * @param string $incomplete_key
   *   The start of another fulltext keyword for the search, which should be
   *   completed.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function setAutocompleteTermQuery(QueryInterface $query, AutocompleteQuery $solarium_query, $incomplete_key) {
    $fl = $this->getAutocompleteFields($query);
    $terms_component = $solarium_query->getTerms();
    $terms_component->setFields($fl);
    $terms_component->setPrefix($incomplete_key);
    $terms_component->setLimit($query->getOption('limit') ?? 10);
  }

  /**
   * Get the term suggestions from the autocomplete query result.
   *
   * @param \Solarium\Core\Query\Result\ResultInterface $result
   *   An autocomplete query result.
   * @param \Drupal\search_api_autocomplete\Suggestion\SuggestionFactory $suggestion_factory
   *   The suggestion factory.
   * @param string $incomplete_key
   *   The start of another fulltext keyword for the search, which should be
   *   completed.
   *
   * @return \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface[]
   *   An array of suggestions.
   */
  protected function getAutocompleteTermSuggestions(ResultInterface $result, SuggestionFactory $suggestion_factory, $incomplete_key) {
    $suggestions = [];
    if ($terms_results = $result->getComponent(ComponentAwareQueryInterface::COMPONENT_TERMS)) {
      $autocomplete_terms = [];
      foreach ($terms_results as $fields) {
        foreach ($fields as $term => $count) {
          $autocomplete_terms[$term] = $count;
        }
      }

      foreach ($autocomplete_terms as $term => $count) {
        $suggestion_suffix = mb_substr($term, mb_strlen($incomplete_key));
        $suggestions[] = $suggestion_factory->createFromSuggestionSuffix($suggestion_suffix, $count);
      }
    }
    return $suggestions;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexId(IndexInterface $index) {
    $settings = Utility::getIndexSolrSettings($index);
    return $this->configuration['server_prefix'] . $settings['advanced']['index_prefix'] . $index->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetedIndexId(IndexInterface $index) {
    static $targeted_index = [];

    if (!isset($targeted_index[$index->id()])) {
      $config = $this->getDatasourceConfig($index);
      $targeted_index[$index->id()] = $config['target_index'] ?? $this->getIndexId($index);
    }

    return $targeted_index[$index->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetedSiteHash(IndexInterface $index) {
    static $targeted_site_hash = [];

    if (!isset($targeted_site_hash[$index->id()])) {
      $config = $this->getDatasourceConfig($index);
      $targeted_site_hash[$index->id()] = $config['target_hash'] ?? Utility::getSiteHash();
    }

    return $targeted_site_hash[$index->id()];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function calculateDependencies() {
    /** @var \Drupal\Component\Plugin\PluginInspectionInterface $connector */
    $connector = $this->getSolrConnector();
    $this->calculatePluginDependencies($connector);

    /** @var \Drupal\search_api_solr\Controller\SolrFieldTypeListBuilder $list_builder */
    $field_type_list_builder = $this->entityTypeManager->getListBuilder('solr_field_type');
    $field_type_list_builder->setBackend($this);
    $solr_field_types = $field_type_list_builder->getEnabledEntities();
    /** @var \Drupal\search_api_solr\Entity\SolrFieldType $solr_field_type */
    foreach ($solr_field_types as $solr_field_type) {
      $this->addDependency('config', $solr_field_type->getConfigDependencyName());
    }

    /** @var \Drupal\search_api_solr\Controller\SolrCacheListBuilder $cache_list_builder */
    $cache_list_builder = $this->entityTypeManager->getListBuilder('solr_cache');
    $cache_list_builder->setBackend($this);
    $solr_caches = $cache_list_builder->load();
    foreach ($solr_caches as $solr_cache) {
      if (!$solr_cache->isDisabledOnServer()) {
        $this->addDependency('config', $solr_cache->getConfigDependencyName());
      }
    }

    /** @var \Drupal\search_api_solr\Controller\SolrCacheListBuilder $request_handler_list_builder */
    $request_handler_list_builder = $this->entityTypeManager->getListBuilder('solr_request_handler');
    $request_handler_list_builder->setBackend($this);
    $solr_request_handlers = $request_handler_list_builder->load();
    foreach ($solr_request_handlers as $request_handler) {
      if (!$request_handler->isDisabledOnServer()) {
        $this->addDependency('config', $request_handler->getConfigDependencyName());
      }
    }

    /** @var \Drupal\search_api_solr\Controller\SolrCacheListBuilder $request_dispatcher_list_builder */
    $request_dispatcher_list_builder = $this->entityTypeManager->getListBuilder('solr_request_dispatcher');
    $request_dispatcher_list_builder->setBackend($this);
    $solr_request_dispatchers = $request_dispatcher_list_builder->load();
    foreach ($solr_request_dispatchers as $request_dispatcher) {
      if (!$request_dispatcher->isDisabledOnServer()) {
        $this->addDependency('config', $request_dispatcher->getConfigDependencyName());
      }
    }

    return $this->dependencies;
  }

  /**
   * Extract and format highlighting information for a specific item.
   *
   * Will also use highlighted fields to replace retrieved field data, if the
   * corresponding option is set.
   *
   * @param array $data
   *   The data extracted from a Solr result.
   * @param string $solr_id
   *   The ID of the result item.
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The fields of the result item.
   * @param array $field_mapping
   *   Mapping from search_api field names to Solr field names.
   */
  protected function getHighlighting(array $data, $solr_id, ItemInterface $item, array $field_mapping) {
    if (isset($data['highlighting'][$solr_id]) && !empty($this->configuration['highlight_data'])) {
      $prefix = '<strong>';
      $suffix = '</strong>';
      try {
        $highlight_config = $item->getIndex()->getProcessor('highlight')->getConfiguration();
        if ($highlight_config['highlight'] === 'never') {
          return;
        }
        $prefix = $highlight_config['prefix'];
        $suffix = $highlight_config['suffix'];
      }
      catch (SearchApiException $exception) {
        // Highlighting processor is not enabled for this index.
      }
      $snippets = [];
      $keys = [];
      foreach ($field_mapping as $search_api_property => $solr_property) {
        if (!empty($data['highlighting'][$solr_id][$solr_property])) {
          foreach ($data['highlighting'][$solr_id][$solr_property] as $value) {
            $keys[] = Utility::getHighlightedKeys($value);
            // Contrary to above, we here want to preserve HTML, so we just
            // replace the [HIGHLIGHT] tags with the appropriate format.
            $snippets[$search_api_property][] = Utility::formatHighlighting($value, $prefix, $suffix);
          }
        }
      }
      if ($snippets) {
        $item->setExtraData('highlighted_fields', $snippets);
        $item->setExtraData('highlighted_keys', array_unique(array_merge(...$keys)));
      }
    }
  }

  /**
   * Sets the highlighting parameters.
   *
   * (The $query parameter currently isn't used and only here for the potential
   * sake of subclasses.)
   *
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The Solarium select query object.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query object.
   * @param array $highlighted_fields
   *   (optional) The solr fields to be highlighted.
   */
  protected function setHighlighting(Query $solarium_query, QueryInterface $query, array $highlighted_fields = []) {
    if (!empty($this->configuration['highlight_data'])) {
      $settings = Utility::getIndexSolrSettings($query->getIndex());
      $highlighter = $settings['highlighter'];

      $hl = $solarium_query->getHighlighting();
      $hl->setSimplePrefix('[HIGHLIGHT]');
      $hl->setSimplePostfix('[/HIGHLIGHT]');
      $hl->setSnippets($highlighter['highlight']['snippets']);
      $hl->setFragSize($highlighter['highlight']['fragsize']);
      $hl->setMergeContiguous($highlighter['highlight']['mergeContiguous']);
      $hl->setRequireFieldMatch($highlighter['highlight']['requireFieldMatch']);

      // Overwrite Solr default values only if required to have shorter request
      // strings.
      if (51200 != $highlighter['maxAnalyzedChars']) {
        $hl->setMaxAnalyzedChars($highlighter['maxAnalyzedChars']);
      }
      if ('gap' !== $highlighter['fragmenter']) {
        $hl->setFragmenter($highlighter['fragmenter']);
        if ('regex' !== $highlighter['fragmenter']) {
          $hl->setRegexPattern($highlighter['regex']['pattern']);
          if (0.5 != $highlighter['regex']['slop']) {
            $hl->setRegexSlop($highlighter['regex']['slop']);
          }
          if (10000 != $highlighter['regex']['maxAnalyzedChars']) {
            $hl->setRegexMaxAnalyzedChars($highlighter['regex']['maxAnalyzedChars']);
          }
        }
      }
      if (!$highlighter['usePhraseHighlighter']) {
        $hl->setUsePhraseHighlighter(FALSE);
      }
      if (!$highlighter['highlightMultiTerm']) {
        $hl->setHighlightMultiTerm(FALSE);
      }
      if ($highlighter['preserveMulti']) {
        $hl->setPreserveMulti(TRUE);
      }

      foreach ($highlighted_fields as $highlighted_field) {
        // We must not set the fields at once using setFields() to not break
        // the altered queries.
        $hl->addField($highlighted_field);
      }
    }
  }

  /**
   * Changes the query to a "More Like This" query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query to build the mlt query from.
   *
   * @return \Solarium\QueryType\MoreLikeThis\Query
   *   The Solarium MorelikeThis query.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getMoreLikeThisQuery(QueryInterface $query) {
    $connector = $this->getSolrConnector();
    $solr_version = $connector->getSolrVersion();
    $solarium_query = $connector->getMoreLikeThisQuery();
    $mlt_options = $query->getOption('search_api_mlt');
    $language_ids = Utility::ensureLanguageCondition($query);
    $field_names = $this->getSolrFieldNamesKeyedByLanguage($language_ids, $query->getIndex());

    $ids = [];
    foreach ($query->getIndex()->getDatasources() as $datasource) {
      if ($entity_type_id = $datasource->getEntityTypeId()) {
        $entity = $this->entityTypeManager
          ->getStorage($entity_type_id)
          ->load($mlt_options['id']);

        if ($entity instanceof ContentEntityInterface) {
          $translated = FALSE;
          if ($entity->isTranslatable()) {
            foreach ($language_ids as $language_id) {
              if ($entity->hasTranslation($language_id)) {
                $ids[] = SearchApiUtility::createCombinedId(
                  $datasource->getPluginId(),
                  $datasource->getItemId(
                    $entity->getTranslation($language_id)->getTypedData()
                  )
                );
                $translated = TRUE;
              }
            }
          }

          if (!$translated) {
            // Fall back to the default language of the entity.
            $ids[] = SearchApiUtility::createCombinedId(
              $datasource->getPluginId(),
              $datasource->getItemId($entity->getTypedData())
            );
          }
        }
        else {
          $ids[] = $mlt_options['id'];
        }
      }
    }

    if (!empty($ids)) {
      $index = $query->getIndex();
      $index_id = $this->getTargetedIndexId($index);
      $site_hash = $this->getTargetedSiteHash($index);
      if (!Utility::hasIndexJustSolrDatasources($index)) {
        array_walk($ids, function (&$id, $key) use ($site_hash, $index_id) {
          $id = $this->createId($site_hash, $index_id, $id);
          $id = $this->queryHelper->escapePhrase($id);
        });
      }
      $solarium_query->setQuery('id:' . implode(' id:', $ids));
    }

    $mlt_fl = [];
    foreach ($mlt_options['fields'] as $mlt_field) {
      if ($mlt_field && isset($field_names[$mlt_field])) {
        $first_field = reset($field_names[$mlt_field]);
        if (
          strpos($first_field, 'd') === 0 ||
          (
            version_compare($solr_version, '7.0', '>=') &&
            preg_match('/^[ifph]/', $first_field, $matches)
          )
        ) {
          // Trie based field types were deprecated in Solr 6 and with Solr 7 we
          // switched to the point based equivalents. But lucene doesn't support
          // mlt based on these field types. Date fields don't seem to be
          // supported at all in MLT queries.
          $msg = 'More like this (MLT) is not yet supported by Solr for point based field types. Consider converting the following field to a string or indexing it one more time as string:';
          $this->getLogger()->error($msg . ' @field', ['@field' => $mlt_field]);
          throw new SearchApiSolrException(sprintf($msg . ' %s', $mlt_field));
        }
        if (strpos($first_field, 't') !== 0) {
          // Non-text fields are not language-specific.
          $mlt_fl[] = [$first_field];
        }
        else {
          // Add all language-specific field names. This should work for
          // non Drupal Solr Documents as well which contain only a single
          // name.
          $mlt_fl[] = array_values($field_names[$mlt_field]);
        }
      }

    }

    $settings = Utility::getIndexSolrSettings($query->getIndex());
    $solarium_query
      ->setMltFields(array_merge(...$mlt_fl))
      ->setMinimumTermFrequency($settings['mlt']['mintf'])
      ->setMinimumDocumentFrequency($settings['mlt']['mindf'])
      ->setMaximumQueryTerms($settings['mlt']['maxqt'])
      ->setMaximumNumberOfTokens($settings['mlt']['maxntp'])
      ->setBoost($settings['mlt']['boost'])
      ->setInterestingTerms($settings['mlt']['interestingTerms']);

    if ($settings['mlt']['maxdf']) {
      $solarium_query->addParam('mlt.maxdf', $settings['mlt']['maxdf']);
    }
    if ($settings['mlt']['maxdfpct']) {
      $solarium_query->addParam('mlt.maxdf', $settings['mlt']['maxdfpct']);
    }
    if ($settings['mlt']['minwl']) {
      $solarium_query->setMinimumWordLength($settings['mlt']['minwl']);
    }
    if ($settings['mlt']['maxwl']) {
      $solarium_query->setMaximumWordLength($settings['mlt']['maxwl']);
    }

    return $solarium_query;
  }

  /**
   * Adds spatial features to the search query.
   *
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The Solarium query.
   * @param array $spatial_options
   *   The spatial options to add.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function setSpatial(Query $solarium_query, array $spatial_options, QueryInterface $query) {
    if (count($spatial_options) > 1) {
      throw new SearchApiSolrException('Only one spatial search can be handled per query.');
    }

    $field_names = $this->getSolrFieldNames($query->getIndex());
    $spatial = reset($spatial_options);
    $solr_field = $field_names[$spatial['field']];
    $distance_field = $spatial['field'] . '__distance';
    $solr_distance_field = $field_names[$distance_field];
    $spatial['lat'] = (float) $spatial['lat'];
    $spatial['lon'] = (float) $spatial['lon'];
    $spatial['radius'] = isset($spatial['radius']) ? (float) $spatial['radius'] : 0.0;
    $spatial['min_radius'] = isset($spatial['min_radius']) ? (float) $spatial['min_radius'] : 0.0;

    if (!isset($spatial['filter_query_conditions'])) {
      $spatial['filter_query_conditions'] = [];
    }
    $spatial['filter_query_conditions'] += [
      'field' => $solr_field,
      'value' => $spatial['radius'],
      'operator' => '<',
    ];

    // Add a field to the result set containing the calculated distance.
    $solarium_query->addField($solr_distance_field . ':geodist()');
    // Set the common spatial parameters on the query.
    $spatial_query = $solarium_query->getSpatial();
    $spatial_query->setDistance($spatial['radius']);
    $spatial_query->setField($solr_field);
    $spatial_query->setPoint($spatial['lat'] . ',' . $spatial['lon']);
    // Add the conditions of the spatial query. This might adjust the values of
    // 'radius' and 'min_radius' required later for facets.
    $solarium_query->createFilterQuery($solr_field)
      ->setQuery($this->createLocationFilterQuery($spatial));

    // Tell solr to sort by distance if the field is given by Search API.
    $sorts = $solarium_query->getSorts();
    if (isset($sorts[$solr_distance_field])) {
      $new_sorts = [];
      foreach ($sorts as $key => $order) {
        if ($key == $solr_distance_field) {
          $new_sorts['geodist()'] = $order;
        }
        else {
          $new_sorts[$key] = $order;
        }
      }
      $solarium_query->clearSorts();
      $solarium_query->setSorts($new_sorts);
    }

    // Change the facet parameters for spatial fields to return distance
    // facets.
    $facet_set = $solarium_query->getFacetSet();
    /** @var \Solarium\Component\Facet\Field[] $facets */
    $facets = $facet_set->getFacets();
    foreach ($facets as $delta => $facet) {
      $facet_options = $facet->getOptions();
      if ($facet_options['field'] != $solr_distance_field) {
        continue;
      }
      $facet_set->removeFacet($delta);

      $limit = $facet->getLimit();

      // @todo Check if these defaults make any sense.
      $steps = $limit > 0 ? $limit : 5;
      $step = ($spatial['radius'] - $spatial['min_radius']) / $steps;

      for ($i = 0; $i < $steps; $i++) {
        $distance_min = $spatial['min_radius'] + ($step * $i);
        // @todo $step - 1 means 1km less. That opens a gap in the facets of
        //   1km that is not covered.
        $distance_max = $distance_min + $step - 1;
        $facet_set->createFacetQuery([
          // Define our own facet key to transport the min and max values. These
          // will be extracted in extractFacets().
          'local_key' => "spatial-{$distance_field}-{$distance_min}-{$distance_max}",
          'query' => '{!frange l=' . $distance_min . ' u=' . $distance_max . '}geodist()',
        ]);
      }
    }
  }

  /**
   * Adds rpt spatial features to the search query.
   *
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The Solarium query.
   * @param array $rpt_options
   *   The rpt spatial options to add.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   *   Thrown when more than one rpt spatial searches are added.
   */
  protected function setRpt(Query $solarium_query, array $rpt_options, QueryInterface $query) {
    // Add location filter.
    if (count($rpt_options) > 1) {
      throw new SearchApiSolrException('Only one spatial search can be handled per query.');
    }

    $field_names = $this->getSolrFieldNames($query->getIndex());
    $rpt = reset($rpt_options);
    $solr_field = $field_names[$rpt['field']];
    $rpt['geom'] = $rpt['geom'] ?? '["-180 -90" TO "180 90"]';

    // Add location filter.
    $solarium_query->createFilterQuery($solr_field)->setQuery($solr_field . ':' . $rpt['geom']);

    // Add Solr Query params.
    $solarium_query->addParam('facet', 'on');
    $solarium_query->addParam('facet.heatmap', $solr_field);
    $solarium_query->addParam('facet.heatmap.geom', $rpt['geom']);
    $solarium_query->addParam('facet.heatmap.format', $rpt['format']);
    $solarium_query->addParam('facet.heatmap.maxCells', $rpt['maxCells']);
    $solarium_query->addParam('facet.heatmap.gridLevel', $rpt['gridLevel']);
  }

  /**
   * Sets sorting for the query.
   *
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The Solarium query.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function setSorts(Query $solarium_query, QueryInterface $query) {
    $field_names = $this->getSolrFieldNamesKeyedByLanguage(Utility::ensureLanguageCondition($query), $query->getIndex());
    foreach ($query->getSorts() as $field => $order) {
      $solarium_query->addSort(Utility::getSortableSolrField($field, $field_names, $query), strtolower($order));
    }
  }

  /**
   * Sets grouping for the query.
   *
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The solarium query.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query.
   * @param array $grouping_options
   *   Grouping options array.
   * @param \Drupal\search_api\Item\FieldInterface[] $index_fields
   *   Index fields array.
   * @param array $field_names
   *   Field names array.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function setGrouping(Query $solarium_query, QueryInterface $query, array $grouping_options = [], array $index_fields = [], array $field_names = []) {
    if (!empty($grouping_options['use_grouping'])) {

      $group_fields = [];

      foreach ($grouping_options['fields'] as $collapse_field) {
        // @todo languages
        $first_name = reset($field_names[$collapse_field]);
        /** @var \Drupal\search_api\Item\Field $field */
        $field = $index_fields[$collapse_field];
        $type = $field->getType();
        // For the Solr Document datasource, determining whether a field is
        // single- or multivalued would be more complicated, so we just hope the
        // user knows what they're doing in that case.
        if (Utility::hasIndexJustSolrDocumentDatasource($query->getIndex())) {
          $known_to_be_multi_valued = FALSE;
        }
        else {
          $known_to_be_multi_valued = 's' !== Utility::getSolrFieldCardinality($first_name);
        }
        if ($this->dataTypeHelper->isTextType($type) || $known_to_be_multi_valued) {
          $this->getLogger()->error('Grouping is not supported for field @field. Only single-valued fields not indexed as "Fulltext" are supported.',
            ['@field' => $index_fields[$collapse_field]->getLabel()]);
        }
        else {
          $group_fields[] = $first_name;
        }
      }

      if (!empty($group_fields)) {
        // Activate grouping on the solarium query.
        $grouping_component = $solarium_query->getGrouping();

        $grouping_component->setFields($group_fields)
          // We always want the number of groups returned so that we get pagers
          // done right.
          ->setNumberOfGroups(TRUE)
          ->setTruncate(!empty($grouping_options['truncate']))
          ->setFacet(!empty($grouping_options['group_facet']));

        if (!empty($grouping_options['group_limit']) && ($grouping_options['group_limit'] != 1)) {
          $grouping_component->setLimit($grouping_options['group_limit']);
        }

        // Set group offset.
        if (isset($grouping_options['group_offset'])) {
          $grouping_component->setOffset($grouping_options['group_offset']);
        }

        if (!empty($grouping_options['group_sort'])) {
          $sorts = [];
          foreach ($grouping_options['group_sort'] as $group_sort_field => $order) {
            $sorts[] = Utility::getSortableSolrField($group_sort_field, $field_names, $query) . ' ' . strtolower($order);
          }

          $grouping_component->setSort(implode(', ', $sorts));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setSpellcheck(ComponentAwareQueryInterface $solarium_query, QueryInterface $query, array $spellcheck_options = []) {
    /** @var \Solarium\Component\Spellcheck $spellcheck */
    $spellcheck = $solarium_query->getSpellcheck();
    $schema_languages = $this->getSchemaLanguageStatistics();
    $dictionaries = [];

    foreach (Utility::ensureLanguageCondition($query) as $language_id) {
      if (isset($schema_languages[$language_id]) && $schema_languages[$language_id]) {
        $dictionaries[] = $schema_languages[$language_id];
      }
    }

    if ($dictionaries) {
      $spellcheck->setDictionary($dictionaries);
    }
    else {
      $spellcheck->setDictionary(LanguageInterface::LANGCODE_NOT_SPECIFIED);
    }

    if (!empty($spellcheck_options['keys'])) {
      $spellcheck->setQuery(implode(' ', $spellcheck_options['keys']));
    }

    if (!empty($spellcheck_options['count'])) {
      $spellcheck->setCount($spellcheck_options['count']);
    }

    if (!empty($spellcheck_options['collate'])) {
      $spellcheck->setCollate($spellcheck_options['collate']);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function extractContentFromFile(string $filepath, string $extract_format = ExtractQuery::EXTRACT_FORMAT_XML) {
    $connector = $this->getSolrConnector();

    $solr_version = $connector->getSolrVersion();
    if (version_compare($solr_version, '8.6', '>=') && version_compare($solr_version, '8.6.3', '<')) {
      $this->getLogger()
        ->error('Solr 8.6.0, 8.6.1 and 8.6.2 contain a bug that breaks content extraction form files. Upgrade to 8.6.3 at least.');
      return '';
    }

    $query = $connector->getExtractQuery();
    $query->setExtractOnly(TRUE);
    $query->setExtractFormat($extract_format);
    $query->setFile($filepath);

    // Execute the query.
    $result = $connector->extract($query);
    return $connector->getContentFromExtractResult($result, $filepath);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getBackendDefinedFields(IndexInterface $index) {
    $backend_defined_fields = [];

    foreach ($index->getFields() as $field) {
      if ($field->getType() === 'location') {
        $distance_field_name = $field->getFieldIdentifier() . '__distance';
        $property_path_name = $field->getPropertyPath() . '__distance';
        $distance_field = new Field($index, $distance_field_name);
        $distance_field->setLabel($field->getLabel() . ' (distance)');
        $distance_field->setDataDefinition(DataDefinition::create('decimal'));
        $distance_field->setType('decimal');
        $distance_field->setDatasourceId($field->getDatasourceId());
        $distance_field->setPropertyPath($property_path_name);

        $backend_defined_fields[$distance_field_name] = $distance_field;
      }
    }

    $backend_defined_fields['search_api_solr_score_debugging'] = $this->getFieldsHelper()
      ->createField($index, 'search_api_solr_score_debugging', [
        'label' => 'Solr score debugging',
        'description' => $this->t('Detailed information about the score calculation.'),
        'type' => 'string',
        'property_path' => 'search_api_solr_score_debugging',
        'data_definition' => DataDefinition::create('string'),
      ]);

    return $backend_defined_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getDomain() {
    return (isset($this->configuration['domain']) && !empty($this->configuration['domain'])) ? $this->configuration['domain'] : 'generic';
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironment() {
    return (isset($this->configuration['environment']) && !empty($this->configuration['environment'])) ? $this->configuration['environment'] : 'default';
  }

  /**
   * {@inheritdoc}
   */
  public function isManagedSchema() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isOptimizeEnabled() {
    return $this->configuration['optimize'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Don't return the big twm_suggest field.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getQueryFulltextFields(QueryInterface $query) {
    $fulltext_fields = parent::getQueryFulltextFields($query);
    $solr_field_names = $this->getSolrFieldNames($query->getIndex());
    return array_filter($fulltext_fields, function ($value) use ($solr_field_names) {
      return 'twm_suggest' !== $solr_field_names[$value] & strpos($solr_field_names[$value], 'spellcheck') !== 0;
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getSchemaLanguageStatistics(?Endpoint $endpoint = NULL) {
    $stats = [];
    $language_ids = array_keys($this->languageManager->getLanguages());
    $language_ids[] = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    foreach ($language_ids as $language_id) {
      // Convert zk-hans to zk_hans.
      $converted_language_id = str_replace('-', '_', $language_id);

      $stats[$language_id] = FALSE;
      try {
        $stats[$language_id] = $this->isPartOfSchema('fieldTypes', 'text_' . $converted_language_id, $endpoint) ? $converted_language_id : FALSE;
        if (!$stats[$language_id]) {
          // Try language fallback.
          $converted_language_id = preg_replace('/-.+$/', '', $language_id);
          $stats[$language_id] = $this->isPartOfSchema('fieldTypes', 'text_' . $converted_language_id, $endpoint) ? $converted_language_id : FALSE;
        }
      }
      catch (SearchApiSolrException $e) {
        $stats[$language_id] = FALSE;
      }
    }

    return $stats;
  }

  /**
   * Indicates if an 'element' is part of the Solr server's schema.
   *
   * @param string $kind
   *   The kind of the element, for example 'dynamicFields' or 'fieldTypes'.
   * @param string $name
   *   The name of the element.
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   The solarium endpoint.
   *
   * @return bool
   *   TRUE if an element of the given kind and name exists, FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function isPartOfSchema($kind, $name, ?Endpoint $endpoint = NULL) {
    static $previous_calls;

    $endpoint_key = $endpoint ? $endpoint->getKey() : $this->getServer()->id();

    $state = $this->state;
    // This state is resetted once a day via cron.
    $schema_parts = $state->get('search_api_solr.endpoint.schema_parts');

    if (
      !is_array($schema_parts) ||
      empty($schema_parts[$endpoint_key]) ||
      empty($schema_parts[$endpoint_key][$kind]) ||
      (!in_array($name, $schema_parts[$endpoint_key][$kind]) && !isset($previous_calls[$endpoint_key][$kind]))
    ) {
      $response = $this->getSolrConnector()
        ->coreRestGet('schema/' . strtolower($kind), $endpoint);
      if (empty($response[$kind])) {
        throw new SearchApiSolrException('Missing information about ' . $kind . ' in response to REST request.');
      }
      // Delete the old state.
      $schema_parts[$endpoint_key][$kind] = [];
      foreach ($response[$kind] as $row) {
        $schema_parts[$endpoint_key][$kind][] = $row['name'];
      }
      $state->set('search_api_solr.endpoint.schema_parts', $schema_parts);
      $previous_calls[$endpoint_key][$kind] = TRUE;
    }

    return in_array($name, $schema_parts[$endpoint_key][$kind]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentCounts() {
    $document_counts = [
      '#total' => 0,
    ];

    if ($indexes = $this->getServer()->getIndexes(['status' => TRUE])) {
      $connector_endpoints_queried = [];
      foreach ($indexes as $index) {
        $collection_endpoint = $this->getCollectionEndpoint($index);
        $key = $collection_endpoint->getBaseUri();
        if (!in_array($key, $connector_endpoints_queried)) {
          $connector_endpoints_queried[] = $key;
          $collection_document_counts = $this->doDocumentCounts($collection_endpoint);
          $collection_document_counts['#total'] += $document_counts['#total'];
          $document_counts = ArrayUtils::merge($document_counts, $collection_document_counts, TRUE);
        }
      }
    }
    else {
      $connector = $this->getSolrConnector();
      $connector_endpoint = $connector->getEndpoint();
      return $this->doDocumentCounts($connector_endpoint);
    }

    return $document_counts;
  }

  /**
   * Perform document count for a given endpoint, in total and per site / index.
   *
   * @param \Solarium\Core\Client\Endpoint $endpoint
   *   The solarium endpoint.
   *
   * @return array
   *   An associative array of document counts.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function doDocumentCounts(Endpoint $endpoint): array {
    $connector = $this->getSolrConnector();

    if (version_compare($connector->getSolrVersion(), '5.0.0', '<')) {
      // The code below doesn't work in Solr below 5.x anyway.
      return ['#total' => 0];
    }

    try {
      $query = $connector->getSelectQuery()
        ->addFilterQuery(new FilterQuery([
          'local_key' => 'search_api',
          'query' => '+hash:* +index_id:*',
        ]))
        ->setRows(1)
        ->setFields('id');

      $facet_set = $query->getFacetSet();

      $json_facet_query = $facet_set->createJsonFacetTerms([
        'local_key' => 'siteHashes',
        'limit' => -1,
        'field' => 'hash',
      ]);

      $nested_json_facet_terms = $facet_set->createJsonFacetTerms([
        'local_key' => 'numDocsPerIndex',
        'limit' => -1,
        'field' => 'index_id',
      ], /* Don't add to top level => nested. */ FALSE);

      $json_facet_query->addFacet($nested_json_facet_terms);

      /** @var \Solarium\QueryType\Select\Result\Result $result */
      $result = $connector->execute($query, $endpoint);
    }
    catch (\Exception $e) {
      // For non drupal indexes we only use the implicit "count" aggregation.
      // Therefore, we need one random facet. The only field we can be 99% sure
      // that it exists in any index is _version_. max(_version_) should be the
      // most minimalistic facet we can think of.
      $query = $connector->getSelectQuery()->setRows(1);
      $facet_set = $query->getFacetSet();
      $facet_set->createJsonFacetAggregation([
        'local_key' => 'maxVersion',
        'function' => 'max(_version_)',
      ]);

      if (version_compare($connector->getSolrVersion(), '8.1.0', '>=')) {
        // For whatever reason since Solr 8.1.0 the facet query above leads to
        // a NullPointerException in Solr itself if headers are omitted. But
        // omit headers is the default!
        // @todo track if this issue persists for later Solr versions, too.
        // @see https://issues.apache.org/jira/browse/SOLR-13509
        $query->setOmitHeader(FALSE);
      }

      /** @var \Solarium\QueryType\Select\Result\Result $result */
      $result = $connector->execute($query, $endpoint);
    }

    $facet_set = $result->getFacetSet();

    // The implicit "count" aggregation over all results matching the query
    // exists only any JSONFacet set.
    /** @var \Solarium\Component\Result\Facet\Aggregation $count */
    $count = $facet_set->getFacet('count');
    $document_counts = [
      '#total' => $count->getValue(),
    ];

    /** @var \Solarium\Component\Result\Facet\Buckets $site_hashes */
    if ($site_hashes = $facet_set->getFacet('siteHashes')) {
      /** @var \Solarium\Component\Result\Facet\Bucket $site_hash_bucket */
      foreach ($site_hashes->getBuckets() as $site_hash_bucket) {
        $site_hash = $site_hash_bucket->getValue();
        /** @var \Solarium\Component\Result\Facet\Bucket $index_bucket */
        foreach ($site_hash_bucket->getFacetSet()->getFacet('numDocsPerIndex') as $index_bucket) {
          $index = $index_bucket->getValue();
          $document_counts[$site_hash][$index] = $index_bucket->getCount();
        }
      }
    }

    return $document_counts;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxDocumentVersions() {
    $document_versions = [
      '#total' => 0,
    ];

    if ($indexes = $this->getServer()->getIndexes()) {
      $connector_endpoints_queried = [];
      foreach ($indexes as $index) {
        $collection_endpoint = $this->getCollectionEndpoint($index);
        $key = $collection_endpoint->getBaseUri();
        if (!in_array($key, $connector_endpoints_queried)) {
          $connector_endpoints_queried[] = $key;
          $collection_document_versions = $this->doGetMaxDocumentVersions($collection_endpoint);
          $collection_document_versions['#total'] += $document_versions['#total'];
          $document_versions = ArrayUtils::merge($document_versions, $collection_document_versions, TRUE);
        }
      }
    }
    else {
      // Try to list versions of orphaned or foreign documents.
      try {
        $connector = $this->getSolrConnector();
        $connector_endpoint = $connector->getEndpoint();
        return $this->doGetMaxDocumentVersions($connector_endpoint);
      }
      catch (\Exception $e) {
        // Do nothing.
      }
    }

    return $document_versions;
  }

  /**
   * Get the max document versions, in total and per site / index / datasource.
   *
   * _version_ numbers are important for replication and checkpoints.
   *
   * @param \Solarium\Core\Client\Endpoint $endpoint
   *   The solarium endpoint.
   *
   * @return array
   *   An associative array of max document versions.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function doGetMaxDocumentVersions(Endpoint $endpoint): array {
    $connector = $this->getSolrConnector();
    $document_versions = [
      '#total' => 0,
    ];

    try {
      $query = $connector->getSelectQuery()
        ->addFilterQuery(new FilterQuery([
          'local_key' => 'search_api',
          'query' => '+hash:* +index_id:*',
        ]))
        ->setRows(1)
        ->setFields('id');

      $facet_set = $query->getFacetSet();

      $facet_set->createJsonFacetAggregation([
        'local_key' => 'maxVersion',
        'function' => 'max(_version_)',
      ]);

      $siteHashes = $facet_set->createJsonFacetTerms([
        'local_key' => 'siteHashes',
        'limit' => -1,
        'field' => 'hash',
      ]);

      $indexes = $facet_set->createJsonFacetTerms([
        'local_key' => 'indexes',
        'limit' => -1,
        'field' => 'index_id',
      ], /* Don't add to top level => nested. */ FALSE);

      $dataSources = $facet_set->createJsonFacetTerms([
        'local_key' => 'dataSources',
        'limit' => -1,
        'field' => 'ss_search_api_datasource',
      ], /* Don't add to top level => nested. */ FALSE);

      $maxVersionPerDataSource = $facet_set->createJsonFacetAggregation([
        'local_key' => 'maxVersionPerDataSource',
        'function' => 'max(_version_)',
      ], /* Don't add to top level => nested. */ FALSE);

      $dataSources->addFacet($maxVersionPerDataSource);
      $indexes->addFacet($dataSources);
      $siteHashes->addFacet($indexes);

      /** @var \Solarium\QueryType\Select\Result\Result $result */
      $result = $connector->execute($query, $endpoint);
    }
    catch (\Exception $e) {
      $query = $connector->getSelectQuery()->setRows(1);
      $facet_set = $query->getFacetSet();
      $facet_set->createJsonFacetAggregation([
        'local_key' => 'maxVersion',
        'function' => 'max(_version_)',
      ]);
      /** @var \Solarium\QueryType\Select\Result\Result $result */
      $result = $connector->execute($query, $endpoint);
    }

    $facet_set = $result->getFacetSet();
    /** @var \Solarium\Component\Result\Facet\Aggregation $maxVersion */
    if ($maxVersion = $facet_set->getFacet('maxVersion')) {
      $document_versions = [
        '#total' => $maxVersion->getValue(),
      ];
      /** @var \Solarium\Component\Result\Facet\Buckets $site_hashes */
      if ($site_hashes = $facet_set->getFacet('siteHashes')) {
        /** @var \Solarium\Component\Result\Facet\Bucket $site_hash_bucket */
        foreach ($site_hashes->getBuckets() as $site_hash_bucket) {
          $site_hash = $site_hash_bucket->getValue();
          /** @var \Solarium\Component\Result\Facet\Bucket $index_bucket */
          foreach ($site_hash_bucket->getFacetSet()->getFacet('indexes') as $index_bucket) {
            $index = $index_bucket->getValue();
            /** @var \Solarium\Component\Result\Facet\Bucket $datasource_bucket */
            if ($datsources_facet = $index_bucket->getFacetSet()->getFacet('dataSources')) {
              foreach ($datsources_facet as $datasource_bucket) {
                $datasource = $datasource_bucket->getValue();
                /** @var \Solarium\Component\Result\Facet\Aggregation $maxVersionPerDataSource */
                if ($maxVersionPerDataSource = $datasource_bucket->getFacetSet()
                  ->getFacet('maxVersionPerDataSource')) {
                  $document_versions[$site_hash][$index][$datasource] = $maxVersionPerDataSource->getValue();
                }
              }
            }
          }
        }
      }
    }

    return $document_versions;
  }

  /**
   * {@inheritdoc}
   */
  public function getDisabledFieldTypes(): array {
    $this->addDefaultConfigurationForConfigGeneration();
    return $this->configuration['disabled_field_types'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDisabledCaches(): array {
    $this->addDefaultConfigurationForConfigGeneration();
    return $this->configuration['disabled_caches'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDisabledRequestHandlers(): array {
    $this->addDefaultConfigurationForConfigGeneration();
    return $this->configuration['disabled_request_handlers'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDisabledRequestDispatchers(): array {
    $this->addDefaultConfigurationForConfigGeneration();
    return $this->configuration['disabled_request_dispatchers'];
  }

  /**
   * {@inheritdoc}
   */
  public function isNonDrupalOrOutdatedConfigSetAllowed(): bool {
    $connector = $this->getSolrConnector();
    $configuration = $connector->getConfiguration();
    return (bool) ($configuration['skip_schema_check'] ?? FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function dispatch(object $event): void {
    $this->eventDispatcher->dispatch($event);
  }

  /**
   * Implements the magic __sleep() method.
   *
   * Prevents the Solr connector from being serialized. For Drupal >= 9.1
   * there's no need for a corresponding __wakeup() because of
   * getSolrConnector().
   *
   * @see getSolrConnector()
   */
  public function __sleep(): array {
    $properties = array_flip(parent::__sleep());

    unset($properties['solrConnector']);

    return array_keys($properties);
  }

}
