<?php

namespace Drupal\geolocation\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\filter\Entity\FilterFormat;

/**
 * Plugin implementation of the 'geolocation_token' formatter.
 *
 * @FieldFormatter(
 *   id = "geolocation_token",
 *   module = "geolocation",
 *   label = @Translation("Geolocation tokenized text"),
 *   field_types = {
 *     "geolocation"
 *   }
 * )
 */
class GeolocationTokenFormatter extends FormatterBase {

  /**
   * Data Provider.
   *
   * @var \Drupal\geolocation\DataProviderInterface
   */
  protected $dataProvider = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->dataProvider = \Drupal::service('plugin.manager.geolocation.dataprovider')->getDataProviderByFieldDefinition($field_definition);
    if (empty($this->dataProvider)) {
      throw new \Exception('Geolocation data provider not found');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = [];
    $settings['tokenized_text'] = [
      'value' => '',
      'format' => filter_default_format(),
    ];
    $settings += parent::defaultSettings();

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings();

    $element['tokenized_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Tokenized text'),
      '#description' => $this->t('Enter any text or HTML to be shown for each value. Tokens will be replaced as available. The "token" module greatly expands the number of available tokens as well as provides a comfortable token browser.'),
    ];
    if (!empty($settings['tokenized_text']['value'])) {
      $element['tokenized_text']['#default_value'] = $settings['tokenized_text']['value'];
    }

    if (!empty($settings['info_text']['format'])) {
      $element['tokenized_text']['#format'] = $settings['tokenized_text']['format'];
    }

    $element['token_help'] = $this->dataProvider->getTokenHelp();

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getSettings();

    $summary = [];
    if (
      !empty($settings['tokenized_text']['value'])
      && !empty($settings['tokenized_text']['format'])
    ) {
      $summary[] = $this->t('Tokenized Text: %text', [
        '%text' => Unicode::truncate(
          check_markup($settings['tokenized_text']['value'], $settings['tokenized_text']['format']),
          100,
          TRUE,
          TRUE
        ),
      ]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $token_context = [
      $this->fieldDefinition->getTargetEntityTypeId() => $items->getEntity(),
    ];

    $elements = [];
    foreach ($items as $delta => $item) {
      $token_context['geolocation_current_item'] = $item;

      $tokenized_text = $this->getSetting('tokenized_text');

      if (
        !empty($tokenized_text['value'])
        && !empty($tokenized_text['format'])
      ) {
        $elements[$delta] = [
          '#type' => 'processed_text',
          '#text' => $this->dataProvider->replaceFieldItemTokens($tokenized_text['value'], $item),
          '#format' => $tokenized_text['format'],
        ];
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    $settings = $this->getSettings();
    if (!empty($settings['tokenized_text']['format'])) {
      $filter_format = FilterFormat::load($settings['tokenized_text']['format']);
      if ($filter_format) {
        $dependencies['config'][] = $filter_format->getConfigDependencyName();
      }
    }
    return $dependencies;
  }

}
