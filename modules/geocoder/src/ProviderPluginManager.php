<?php

namespace Drupal\geocoder;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\geocoder\Annotation\GeocoderProvider;

/**
 * Provides a plugin manager for geocoder providers.
 */
class ProviderPluginManager extends GeocoderPluginManagerBase {

  use StringTranslationTrait;

  /**
   * The Renderer service property.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $renderer;

  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $link;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Drupal messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new geocoder provider plugin manager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations,.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    TranslationInterface $string_translation,
    RendererInterface $renderer,
    LinkGeneratorInterface $link_generator,
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger,
  ) {
    parent::__construct('Plugin/Geocoder/Provider', $namespaces, $module_handler, ProviderInterface::class, GeocoderProvider::class);
    $this->alterInfo('geocoder_provider_info');
    $this->setCacheBackend($cache_backend, 'geocoder_provider_plugins');

    $this->stringTranslation = $string_translation;
    $this->renderer = $renderer;
    $this->link = $link_generator;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;

  }

  /**
   * Returns the defined plugins.
   *
   * Note that this method has been changed in Geocoder 3.x. It currently
   * returns the list of plugin definitions which is identical to the list
   * returned by ::getDefinitions().
   *
   * In Geocoder 2.x this was returning a mix of plugin definitions and
   * configured providers but this architecture has been replaced by the new
   * GeocoderProvider config entity.
   *
   * It is recommended to no longer use this method but instead use one of these
   * two alternatives:
   *
   * In order to get a list of all available plugin definitions:
   * @code
   * $definitions = \Drupal\geocoder\ProviderPluginManager::getDefinitions();
   * @endcode
   *
   * In order to get a list of all geocoding providers that are configured by
   * the site builder:
   * @code
   * $providers = \Drupal\geocoder\Entity\GeocoderProvider::loadMultiple();
   * @endcode
   *
   * @return array
   *   A list of plugins.
   */
  public function getPlugins(): array {
    return $this->getDefinitions();
  }

  /**
   * Generates the Draggable Table of Selectable Geocoder Plugins.
   *
   * @param array $enabled_provider_ids
   *   The IDs of the enabled Geocoder providers.
   *
   * @return array
   *   The plugins table list.
   */
  public function providersPluginsTableList(array $enabled_provider_ids): array {
    $providers = [];
    $providers_link = $this->link->generate($this->t('Geocoder providers configuration page'), Url::fromRoute('entity.geocoder_provider.collection', [], [
      'attributes' => ['target' => '_blank'],
    ]));

    $options_field_description = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Object literals in YAML format. Edit options in the @providers_link.', [
        '@providers_link' => $providers_link ,
      ]),
      '#attributes' => [
        'class' => [
          'options-field-description',
        ],
      ],
    ];

    $caption = [
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'label',
        '#value' => $this->t('Geocoder providers'),
      ],
      'caption' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Select and reorder the Geocoder providers to use. The first one returning a valid value will be used.<br>If the provider of your choice does not appear here, you have to create it first in the @providers_link.', [
          '@providers_link' => $providers_link,
        ]),
      ],
    ];

    $element['plugins'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Weight'),
        $this->t('Options<br>@options_field_description', [
          '@options_field_description' => $this->renderer->renderRoot($options_field_description),
        ]),
      ],
      '#tabledrag' => [[
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'plugins-order-weight',
      ],
      ],
      '#caption' => $this->renderer->renderRoot($caption),
      // We need this class for #states to hide the entire table.
      '#attributes' => ['class' => ['js-form-item', 'geocode-plugins-list']],
    ];

    foreach ($this->entityTypeManager->getStorage('geocoder_provider')->loadMultiple() as $provider_entity) {
      // Non-default values are appended at the end.
      $providers[$provider_entity->id()]['entity'] = $provider_entity;
    }

    // Check if there are orphaned providers being configured. This might happen
    // if a provider is deleted if it is still in use.
    $orphaned_provider_ids = array_keys($providers, NULL, TRUE);
    if (!empty($orphaned_provider_ids)) {
      // Remove the orphaned providers.
      $providers = array_filter($providers);

      // Show a warning to the user.
      $warning = new PluralTranslatableMarkup(count($orphaned_provider_ids), 'The @providers Geocoder provider was not found and has been removed.', 'The following Geocoder providers were not found and have been removed: @providers', [
        '@providers' => implode(', ', $orphaned_provider_ids),
      ]);
      $this->messenger->addWarning($warning);
    }

    if (empty($providers)) {
      $message = $this->t('No Geocoding providers have been configured yet. Please create one in the @providers_link.', [
        '@providers_link' => $providers_link,
      ]);
      return [
        '#theme' => 'status_messages',
        '#message_list' => ['warning' => [$message]],
        '#status_headings' => ['warning' => $this->t('Warning message')],
      ];
    }

    $providers = array_map(function ($provider, $weight) use ($enabled_provider_ids): array {
      /** @var \Drupal\geocoder\Entity\GeocoderProvider $provider_entity */
      $provider_entity = $provider['entity'];
      $checked = \in_array($provider_entity->id(), $enabled_provider_ids, TRUE);

      return array_merge($provider, [
        'checked' => $checked,
        'weight' => $checked ? array_search($provider_entity->getOriginalId(), $enabled_provider_ids) - 100 : 0,
        'arguments' => $provider_entity->isConfigurable() ? Yaml::encode($provider_entity->get('configuration')) : (string) $this->t("This plugin doesn't accept arguments."),
      ]);
    }, $providers, range(0, count($providers) - 1));

    uasort($providers, function ($providerA, $providerB): int {
      $order = $providerB['checked'] <=> $providerA['checked'];

      if (0 === $order) {
        $order = $providerA['weight'] - $providerB['weight'];

        if (0 === $order) {
          $order = strcmp($providerA['entity']->label(), $providerB['entity']->label());
        }
      }

      return $order;
    });

    foreach ($providers as $provider) {
      /** @var \Drupal\geocoder\Entity\GeocoderProvider $provider_entity */
      $provider_entity = $provider['entity'];
      $element['plugins'][$provider_entity->id()] = [
        'checked' => [
          '#type' => 'checkbox',
          '#title' => $provider_entity->label(),
          '#default_value' => $provider['checked'],
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @title', ['@title' => $provider_entity->label()]),
          '#title_display' => 'invisible',
          '#default_value' => $provider['weight'],
          '#delta' => 20,
          '#attributes' => ['class' => ['plugins-order-weight']],
        ],
        'arguments' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => $provider['arguments'],
        ],
        '#attributes' => ['class' => ['draggable']],
      ];
    }

    return $element['plugins'];
  }

}
