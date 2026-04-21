<?php

namespace Drupal\facets\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\UrlProcessor\UrlProcessorPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for editing facet sources.
 *
 * Configuration saved trough this form is specific for a facet source and can
 * be used by all facets on this facet source.
 */
class FacetSourceEditForm extends EntityForm {

  /**
   * The plugin manager for URL Processors.
   *
   * @var \Drupal\facets\UrlProcessor\UrlProcessorPluginManager
   */
  protected $urlProcessorPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.facets.url_processor'),
      $container->get('module_handler')
    );
  }

  /**
   * Constructs a FacetSourceEditForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\facets\UrlProcessor\UrlProcessorPluginManager $url_processor_plugin_manager
   *   The url processor plugin manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Drupal's module handler.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, UrlProcessorPluginManager $url_processor_plugin_manager, ModuleHandlerInterface $moduleHandler) {
    $this->urlProcessorPluginManager = $url_processor_plugin_manager;
    $this->setEntityTypeManager($entity_type_manager);
    $this->setModuleHandler($moduleHandler);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'facet_source_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    /** @var \Drupal\facets\FacetSourceInterface $facet_source */
    $facet_source = $this->getEntity();

    $form['#tree'] = TRUE;
    $form['filter_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filter key'),
      '#size' => 20,
      '#maxlength' => 255,
      '#default_value' => $facet_source->getFilterKey(),
      '#description' => $this->t(
        'The key used in the url to identify the facet source.
        When using multiple facet sources you should make sure each facet source has a different filter key.'
      ),
    ];

    $url_processors = [];
    $url_processors_description = [];
    foreach ($this->urlProcessorPluginManager->getDefinitions() as $definition) {
      $url_processors[$definition['id']] = $definition['label'];
      $url_processors_description[] = $definition['description'];
    }
    $form['url_processor'] = [
      '#type' => 'radios',
      '#title' => $this->t('URL Processor'),
      '#options' => $url_processors,
      '#default_value' => $facet_source->getUrlProcessorName(),
      '#description' => $this->t(
        'The URL Processor defines the url structure used for this facet source.') . '<br />- ' . implode('<br>- ', $url_processors_description),
    ];

    $breadcrumb_settings = $facet_source->getBreadcrumbSettings();
    $form['breadcrumb'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Breadcrumb'),
    ];
    $form['breadcrumb']['active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Append active facets to breadcrumb'),
      '#default_value' => $breadcrumb_settings['active'] ?? FALSE,
    ];
    $form['breadcrumb']['before'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show facet label before active facet'),
      '#default_value' => $breadcrumb_settings['before'] ?? TRUE,
      '#states' => [
        'visible' => [
          ':input[name="breadcrumb[active]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['breadcrumb']['group'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Group active items under same crumb (not implemented yet - now always grouping)'),
      '#default_value' => $breadcrumb_settings['group'] ?? FALSE,
      '#states' => [
        'visible' => [
          ':input[name="breadcrumb[active]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // The parent's form build method will add a save button.
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $facet_source = $this->getEntity();
    $this->messenger()->addMessage($this->t('Facet source %name has been saved.', ['%name' => $facet_source->label()]));
    $form_state->setRedirect('entity.facets_facet.collection');
  }

}
