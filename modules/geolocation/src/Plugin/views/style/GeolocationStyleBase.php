<?php

namespace Drupal\geolocation\Plugin\views\style;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Geolocation Style Base.
 *
 * @package Drupal\geolocation\Plugin\views\style
 */
abstract class GeolocationStyleBase extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesFields = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowClass = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  /**
   * Data provider base.
   *
   * @var \Drupal\geolocation\DataProviderManager
   */
  protected $dataProviderManager = NULL;

  /**
   * File url generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $data_provider_manager, FileUrlGeneratorInterface $file_url_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->dataProviderManager = $data_provider_manager;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.dataprovider'),
      $container->get('file_url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    if (empty($this->options['geolocation_field'])) {
      \Drupal::messenger()->addMessage('The geolocation based view ' . $this->view->id() . ' views style was called without a geolocation field defined in the views style settings.', 'error');
      return FALSE;
    }

    if (empty($this->view->field[$this->options['geolocation_field']])) {
      \Drupal::messenger()->addMessage('The geolocation based view ' . $this->view->id() . ' views style was called with a non-available geolocation field defined in the views style settings.', 'error');
      return FALSE;
    }

    return parent::render();
  }

  /**
   * Render array from views result row.
   *
   * @param \Drupal\views\ResultRow $row
   *   Result row.
   *
   * @return array
   *   List of location render elements.
   */
  protected function getLocationsFromRow(ResultRow $row) {
    $locations = [];

    $icon_url = NULL;
    if (
      !empty($this->options['icon_field'])
      && $this->options['icon_field'] != 'none'
    ) {
      /** @var \Drupal\views\Plugin\views\field\Field $icon_field_handler */
      $icon_field_handler = $this->view->field[$this->options['icon_field']];
      if (!empty($icon_field_handler)) {
        $image_items = $icon_field_handler->getItems($row);
        if (!empty($image_items[0]['rendered']['#item']->entity)) {
          $file_uri = $image_items[0]['rendered']['#item']->entity->getFileUri();

          $style = NULL;
          if (!empty($image_items[0]['rendered']['#image_style'])) {
            /** @var \Drupal\image\Entity\ImageStyle $style */
            $style = ImageStyle::load($image_items[0]['rendered']['#image_style']);
          }

          if (!empty($style)) {
            $icon_url = $this->fileUrlGenerator->transformRelative($style->buildUrl($file_uri));
          }
          else {
            $icon_url = $this->fileUrlGenerator->generateString($file_uri);
          }
        }
      }
    }
    elseif (!empty($this->options['marker_icon_path'])) {
      $icon_token_uri = $this->viewsTokenReplace($this->options['marker_icon_path'], $this->rowTokens[$row->index]);
      $icon_token_uri = $this->globalTokenReplace($icon_token_uri);
      $icon_token_uri = preg_replace('/\s+/', '', $icon_token_uri);
      $icon_url = $this->fileUrlGenerator->generateString($icon_token_uri);
    }

    try {
      $data_provider = $this->dataProviderManager->createInstance($this->options['data_provider_id'], $this->options['data_provider_settings']);
    }
    catch (\Exception $e) {
      \Drupal::logger('geolocation')->critical('View with non-existing data provider called.');
      return [];
    }

    foreach ($data_provider->getPositionsFromViewsRow($row, $this->view->field[$this->options['geolocation_field']]) as $position) {
      $location = [
        '#type' => 'geolocation_map_location',
        'content' => $this->view->rowPlugin->render($row),
        '#row' => $row,
        '#title' => $this->getTitleField($row) ?? '',
        '#label' => $this->getLabelField($row) ?? '',
        '#coordinates' => $position,
        '#weight' => $row->index,
        '#attributes' => ['data-views-row-index' => $row->index],
      ];

      if (!empty($icon_url)) {
        $location['#icon'] = $icon_url;
      }

      if (!empty($location_id)) {
        $location['#id'] = $location_id;
      }

      if ($this->options['marker_row_number']) {
        $markerOffset = $this->view->pager->getCurrentPage() * $this->view->pager->getItemsPerPage();
        $marker_row_number = (int) $markerOffset + (int) $row->index + 1;
        if (empty($location['#label'])) {
          $location['#label'] = $marker_row_number;
        }
        else {
          $location['#label'] = $location['#label'] . ': ' . $location['#label'];
        }
      }

      $locations[] = $location;
    }

    $locations = array_merge($data_provider->getLocationsFromViewsRow($row, $this->view->field[$this->options['geolocation_field']]), $locations);
    $locations = array_merge($data_provider->getShapesFromViewsRow($row, $this->view->field[$this->options['geolocation_field']]), $locations);

    return $locations;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['geolocation_field'] = ['default' => ''];
    $options['data_provider_id'] = ['default' => 'geolocation_field_provider'];
    $options['data_provider_settings'] = ['default' => []];

    $options['title_field'] = ['default' => ''];
    $options['label_field'] = ['default' => ''];
    $options['icon_field'] = ['default' => ''];

    $options['marker_row_number'] = ['default' => FALSE];
    $options['marker_icon_path'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $labels = $this->displayHandler->getFieldLabels();
    $geo_options = [];
    /** @var \Drupal\geolocation\DataProviderInterface[] $data_providers */
    $data_providers = [];
    $title_options = [];
    $label_options = [];
    $icon_options = [];

    $fields = $this->displayHandler->getHandlers('field');
    /** @var \Drupal\views\Plugin\views\field\FieldPluginBase[] $fields */
    foreach ($fields as $field_name => $field) {
      $data_provider_settings = [];
      if (
        $this->options['geolocation_field'] == $field_name
        && !empty($this->options['data_provider_settings'])
      ) {
        $data_provider_settings = $this->options['data_provider_settings'];
      }
      if ($data_provider = $this->dataProviderManager->getDataProviderByViewsField($field, $data_provider_settings)) {
        $geo_options[$field_name] = $field->adminLabel() . ' (' . $data_provider->getPluginDefinition()['name'] . ')';
        $data_providers[$field_name] = $data_provider;
      }

      if (!empty($field->options['type']) && $field->options['type'] == 'image') {
        $icon_options[$field_name] = $labels[$field_name];
      }

      if (!empty($field->options['type']) && $field->options['type'] == 'string') {
        $title_options[$field_name] = $labels[$field_name];
        $label_options[$field_name] = $labels[$field_name];
      }
    }

    $form['geolocation_field'] = [
      '#title' => $this->t('Geolocation source field'),
      '#type' => 'select',
      '#default_value' => $this->options['geolocation_field'],
      '#description' => $this->t("The source of geodata for each entity."),
      '#options' => $geo_options,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [
          get_class($this->dataProviderManager),
          'addDataProviderSettingsFormAjax',
        ],
        'wrapper' => 'data-provider-settings',
        'effect' => 'fade',
      ],
    ];

    $data_provider = NULL;

    $form_state_data_provider_id = NestedArray::getValue(
      $form_state->getUserInput(),
      ['style_options', 'geolocation_field']
    );
    if (
      !empty($form_state_data_provider_id)
      && !empty($data_providers[$form_state_data_provider_id])
    ) {
      $data_provider = $data_providers[$form_state_data_provider_id];
    }
    elseif (!empty($data_providers[$this->options['geolocation_field']])) {
      $data_provider = $data_providers[$this->options['geolocation_field']];
    }
    elseif (!empty($data_providers[reset($geo_options)])) {
      $data_provider = $data_providers[reset($geo_options)];
    }
    else {
      return;
    }

    $form['data_provider_id'] = [
      '#type' => 'value',
      '#value' => $data_provider->getPluginId(),
    ];

    $form['data_provider_settings'] = $data_provider->getSettingsForm(
      $this->options['data_provider_settings'],
      [
        'style_options',
        'map_provider_settings',
      ]
    );

    $form['data_provider_settings'] = array_replace($form['data_provider_settings'], [
      '#prefix' => '<div id="data-provider-settings">',
      '#suffix' => '</div>',
    ]);

    $form['title_field'] = [
      '#title' => $this->t('Title source field'),
      '#type' => 'select',
      '#default_value' => $this->options['title_field'],
      '#description' => $this->t("The source of the title for each entity. Field type must be 'string'."),
      '#options' => $title_options,
      '#empty_value' => 'none',
    ];

    $form['label_field'] = [
      '#title' => $this->t('Label source field'),
      '#type' => 'select',
      '#default_value' => $this->options['label_field'],
      '#description' => $this->t("The source of the label for each entity. Field type must be 'string'."),
      '#options' => $label_options,
      '#empty_value' => 'none',
    ];

    /*
     * Advanced settings
     */
    $form['advanced_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Advanced settings'),
    ];

    if ($icon_options) {
      $form['icon_field'] = [
        '#group' => 'style_options][advanced_settings',
        '#title' => $this->t('Icon source field'),
        '#type' => 'select',
        '#default_value' => $this->options['icon_field'],
        '#description' => $this->t("Optional image (field) to use as icon."),
        '#options' => $icon_options,
        '#empty_value' => 'none',
        '#process' => [
          ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
          ['\Drupal\Core\Render\Element\Select', 'processSelect'],
        ],
        '#pre_render' => [
          ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
        ],
      ];
    }

    $form['marker_icon_path'] = [
      '#group' => 'style_options][advanced_settings',
      '#type' => 'textfield',
      '#title' => $this->t('Marker icon path'),
      '#description' => $this->t('Set relative or absolute path to custom marker icon. Tokens & Views replacement patterns supported. Empty for default.'),
      '#default_value' => $this->options['marker_icon_path'],
    ];

    $form['marker_row_number'] = [
      '#group' => 'style_options][advanced_settings',
      '#title' => $this->t('Show views result row number in marker'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['marker_row_number'],
    ];
  }

  /**
   * Get title value if present.
   *
   * @param \Drupal\views\ResultRow $row
   *   Result Row.
   *
   * @return string|null
   *   Title.
   */
  public function getTitleField(ResultRow $row): ?string {
    if (
      !empty($this->options['title_field'])
      && $this->options['title_field'] != 'none'
    ) {
      $title_field = $this->options['title_field'];
      if (!empty($this->rendered_fields[$row->index][$title_field])) {
        return PlainTextOutput::renderFromHtml($this->rendered_fields[$row->index][$title_field]);
      }
      elseif (!empty($this->view->field[$title_field])) {
        return PlainTextOutput::renderFromHtml($this->view->field[$title_field]->render($row));
      }
    }

    return NULL;
  }

  /**
   * Get label value if present.
   *
   * @param \Drupal\views\ResultRow $row
   *   Result Row.
   *
   * @return string|null
   *   Label.
   */
  public function getLabelField(ResultRow $row): ?string {
    if (
      !empty($this->options['label_field'])
      && $this->options['label_field'] != 'none'
    ) {
      $label_field = $this->options['label_field'];
      if (!empty($this->rendered_fields[$row->index][$label_field])) {
        return PlainTextOutput::renderFromHtml($this->rendered_fields[$row->index][$label_field]);
      }
      elseif (!empty($this->view->field[$label_field])) {
        return PlainTextOutput::renderFromHtml($this->view->field[$label_field]->render($row));
      }
    }

    return NULL;
  }

}
