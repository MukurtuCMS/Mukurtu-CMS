<?php

namespace Drupal\entity_browser\Plugin\EntityBrowser\Widget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_browser\WidgetBase;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds an upload field browser's widget.
 *
 * @EntityBrowserWidget(
 *   id = "upload",
 *   label = @Translation("Upload"),
 *   description = @Translation("Adds an upload field browser's widget."),
 *   auto_select = FALSE
 * )
 */
class Upload extends WidgetBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->moduleHandler = $container->get('module_handler');
    $instance->token = $container->get('token');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array_merge(parent::defaultConfiguration(), [
      'submit_text' => $this->t('Select files'),
      'upload_location' => 'public://',
      'multiple' => TRUE,
      'extensions' => 'jpg jpeg gif png txt doc xls pdf ppt pps odt ods odp',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);
    $field_cardinality = $form_state->get(['entity_browser', 'validators', 'cardinality', 'cardinality']);
    $upload_validators = $form_state->has(['entity_browser', 'widget_context', 'upload_validators']) ? $form_state->get(['entity_browser', 'widget_context', 'upload_validators']) : [];
    $form['upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Choose a file'),
      '#title_display' => 'invisible',
      '#upload_location' => $this->token->replace($this->configuration['upload_location']),
      // Multiple uploads will only be accepted if the source field allows
      // more than one value.
      '#multiple' => $field_cardinality != 1 && $this->configuration['multiple'],
      '#upload_validators' => array_merge([
        'FileExtension' => ['extensions' => $this->configuration['extensions']],
      ], $upload_validators),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    $files = [];
    foreach ($form_state->getValue(['upload'], []) as $fid) {
      $files[] = $this->entityTypeManager->getStorage('file')->load($fid);
    }
    return $files;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    if (!empty($form_state->getTriggeringElement()['#eb_widget_main_submit'])) {
      $files = $this->prepareEntities($form, $form_state);
      array_walk(
        $files,
        function (FileInterface $file) {
          $file->setPermanent();
          $file->save();
        }
      );
      $this->selectEntities($files, $form_state);
      $this->clearFormValues($element, $form_state);
    }
  }

  /**
   * Clear values from upload form element.
   *
   * @param array $element
   *   Upload form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   */
  protected function clearFormValues(array &$element, FormStateInterface $form_state) {
    // We propagated entities to the other parts of the system. We can now remove
    // them from our values.
    $form_state->setValueForElement($element['upload']['fids'], '');
    NestedArray::setValue($form_state->getUserInput(), $element['upload']['fids']['#parents'], '');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['upload_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Upload location'),
      '#default_value' => $this->configuration['upload_location'],
    ];
    $form['multiple'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Accept multiple files'),
      '#default_value' => $this->configuration['multiple'],
      '#description' => $this->t('Multiple uploads will only be accepted if the source field allows more than one value.'),
    ];
    $form['extensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed file extensions'),
      '#description' => $this->t('Separate extensions with a space or comma and do not include the leading dot.'),
      '#default_value' => $this->configuration['extensions'],
      '#element_validate' => [[static::class, 'validateExtensions']],
      '#required' => TRUE,
    ];

    if ($this->moduleHandler->moduleExists('token')) {
      $form['token_help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['file'],
      ];
      $form['upload_location']['#description'] = $this->t('You can use tokens in the upload location.');
    }

    return $form;
  }

  /**
   * Validates a list of file extensions.
   *
   * @See \Drupal\file\Plugin\Field\FieldType\FileItem::validateExtensions
   */
  public static function validateExtensions($element, FormStateInterface $form_state) {
    if (!empty($element['#value'])) {
      $extensions = preg_replace('/([, ]+\.?)/', ' ', trim(strtolower($element['#value'])));
      $extensions = array_filter(explode(' ', $extensions));
      $extensions = implode(' ', array_unique($extensions));
      if (!preg_match('/^([a-z0-9]+([.][a-z0-9])* ?)+$/', $extensions)) {
        $form_state->setError($element, t('The list of allowed extensions is not valid, be sure to exclude leading dots and to separate extensions with a comma or space.'));
      }
      else {
        $form_state->setValueForElement($element, $extensions);
      }
    }
  }

}
