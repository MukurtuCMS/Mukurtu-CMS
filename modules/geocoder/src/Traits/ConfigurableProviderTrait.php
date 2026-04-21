<?php

declare(strict_types=1);

namespace Drupal\geocoder\Traits;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Config\Schema\SchemaIncompleteException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Url;

/**
 * Trait containing reusable code for configuring Geocoder provider plugins.
 */
trait ConfigurableProviderTrait {

  use LoggerChannelTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $id = $form['id']['#default_value'];
    try {
      foreach ($this->getPluginArguments() as $argument => $argument_definition) {
        switch ($argument_definition['type']) {
          case 'boolean':
            $type = 'checkbox';
            break;

          case 'string':
          case 'color_hex':
          case 'path':
          case 'label':
            $type = 'textfield';
            break;

          case 'text':
            $type = 'textarea';
            break;

          case 'integer':
            $type = 'number';
            break;

          default:
            $type = 'textfield';
        }

        $form['options'][$argument] = [
          '#type' => $type,
          '#title' => $argument_definition['label'] ?? '',
          '#description' => $argument_definition['description'] ?? '',
          '#default_value' => $this->configuration[$argument] ?? $argument_definition['default_value'],
          '#required' => empty($argument_definition['nullable']) || $argument_definition['nullable'] === FALSE,
          // Add support for COI module (https://www.drupal.org/project/coi)
          '#config' => [
            'key' => 'geocoder.geocoder_provider.' . $id . ':' . $argument,
            'secret' => in_array($argument, [
              'accessToken',
              'apiKey',
              'clientId',
              'privateKey',
            ],
            ),
          ],
        ];
      }
    }
    catch (\Exception $e) {
      $form['plugin_arguments_exception'] = [
        '#markup' => $this->t("No configurations options requested for this Provider: @message", [
          '@message' => $e->getMessage(),
        ]),
      ];
    }

    $form['options']['throttle'] = [
      '#type' => 'details',
      '#title' => $this->t("Throttle"),
      '#description' => $this->t("Limit the number of geocoding requests sent by a process for a given period of time.
      Be aware that if you bulk geocode with a hard throttle, it may take a long time or even reach the maximum execution time (Token replacements un-supported)."),
      '#open' => FALSE,
      '#tree' => TRUE,
      '#weight' => 10,
    ];
    foreach ($this->getThrottleOptions() as $option => $option_definition) {
      $form['options']['throttle'][$option] = [
        '#type' => 'number',
        '#title' => $option_definition['label'],
        '#description' => $option_definition['description'],
        '#default_value' => $this->configuration['throttle'][$option] ?? $this->pluginDefinition['throttle'][$option] ?? NULL,
        '#required' => FALSE,
        // Add support for COI module (https://www.drupal.org/project/coi)
        '#config' => [
          'key' => 'geocoder.geocoder_provider.' . $id . ':throttle.' . $option,
        ],
      ];
      if (!empty($form['options']['throttle'][$option]['#default_value'])) {
        $form['options']['throttle']['#open'] = TRUE;
      }
    }

    $form['options']['geocoder'] = [
      '#type' => 'details',
      '#title' => $this->t("Geocoder Additional Options"),
      '#weight' => 15,
    ];

    $form['options']['geocoder']['locale'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Locale'),
      '#placeholder' => $this->languageManager->getCurrentLanguage()->getId(),
      '#default_value' => $this->configuration['geocoder']['locale'] ?? '',
      '#description' => $this->t('Define your specific language code (en, fr, de, it, es ... etc.) that should be used / forced in the @geocoder_query_link.<br>Tokens replacements supported. Alter this only if specifically aware of its functionality.<br><u>If left empty the Current Interface Language code/id will be used.</u>', [
        '@geocoder_query_link' => Link::fromTextAndUrl('Geocoder Query withLocale() method', Url::fromUri('https://github.com/geocoder-php/php-common/blob/master/Query/GeocodeQuery.php#L81'))->toString(),
      ]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $period = $form_state->getValue(['throttle', 'period']);
    $limit = $form_state->getValue(['throttle', 'limit']);
    if (empty($period) && !empty($limit)) {
      $form_state->setErrorByName('throttle][period', $this->t('If you set a throttle limit, you must set a throttle period, like 60 for "per minute".'));
    }
    elseif (!empty($period) && empty($limit)) {
      $form_state->setErrorByName('throttle][limit', $this->t('If you set a throttle period, you must set the throttle limit for this period.'));
    }
    elseif (!empty($period) && $period <= 0) {
      $form_state->setErrorByName('throttle][period', $this->t('Throttle period must be strictly positive'));
    }
    elseif (!empty($limit) && $limit <= 0) {
      $form_state->setErrorByName('throttle][limit', $this->t('Throttle limit must be strictly positive'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    try {

      $this->configuration['geocoder']['locale'] = $form_state->getValue('locale');

      foreach (array_keys($this->getPluginArguments()) as $argument) {
        $this->configuration[$argument] = $form_state->getValue($argument);
      }
      foreach (array_keys($this->getThrottleOptions()) as $option) {
        $this->configuration['throttle'][$option] = $form_state->getValue([
          'throttle',
          $option,
        ]);
      }
    }
    catch (\Exception $e) {
      $this->getLogger('geocoder')->error($e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    return [];
  }

  /**
   * Returns the arguments of the provider plugin.
   *
   * @return array
   *   An associative array of argument data, keyed by argument name. The
   *   argument data consists of the config schema information and any default
   *   values supplied by the plugin annotation.
   *
   * @throws \Drupal\Core\Config\Schema\SchemaIncompleteException
   *   Thrown when the config schema for the plugin is missing or doesn't
   *   contain any configurable options.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown when the plugin annotation does not have any arguments, or the
   *   arguments defined in the plugin annotation do not match those defined in
   *   the config schema.
   */
  protected function getPluginArguments(): array {
    $plugin_id = $this->getPluginId();
    $config_schema_definition = $this->getConfigSchemaDefinition();

    if (empty($config_schema_definition['mapping'])) {
      throw new SchemaIncompleteException("The $plugin_id Geocoder provider plugin doesn't have any options defined in its schema definition.");
    }

    if (empty($this->pluginDefinition['arguments'])) {
      throw new InvalidPluginDefinitionException($plugin_id, 'The plugin is configurable but no arguments are defined in its plugin annotation.');
    }

    // Check that the arguments defined in the schema and the plugin annotation
    // match.
    $config_schema_arguments = array_keys($config_schema_definition['mapping']);
    $plugin_annotation_arguments = array_keys($this->pluginDefinition['arguments']);
    if (!empty(array_diff($plugin_annotation_arguments, $config_schema_arguments))) {
      throw new InvalidPluginDefinitionException($plugin_id, 'The arguments defined in the plugin annotation do not match the arguments defined in the config schema.');
    }

    // Enrich the config schema data with the default values from the plugin
    // annotation.
    $plugin_arguments = [];
    foreach ($this->pluginDefinition['arguments'] as $argument => $default_value) {
      $plugin_arguments[$argument] = $config_schema_definition['mapping'][$argument];
      $plugin_arguments[$argument]['default_value'] = $default_value;
    }

    return $plugin_arguments;
  }

  /**
   * Returns the throttle options of the plugin.
   *
   * @return array
   *   An associative array of throttle data, keyed by option name. The
   *   throttle data consists of the config schema information.
   */
  protected function getThrottleOptions() {
    $throttle_definition = $this->typedConfigManager->getDefinition('geocoder_throttle_configuration');
    return $throttle_definition['mapping'];
  }

  /**
   * Returns the config schema definition for the plugin.
   *
   * @return array
   *   The config schema definition.
   *
   * @throws \Drupal\Core\Config\Schema\SchemaIncompleteException
   *   Thrown when the plugin doesn't have a schema defined for its configurable
   *   parameters. These are expected to be present since this plugin implements
   *   \Drupal\Component\Plugin\ConfigurableInterface.
   */
  protected function getConfigSchemaDefinition(): array {
    $plugin_id = $this->getPluginId();
    if ($this->typedConfigManager->hasConfigSchema('geocoder_provider.configuration.' . $plugin_id)) {
      return $this->typedConfigManager->getDefinition('geocoder_provider.configuration.' . $plugin_id);
    }
    throw new SchemaIncompleteException("The $plugin_id Geocoder provider plugin is configurable but doesn't have a schema definition for its configuration.");
  }

}
