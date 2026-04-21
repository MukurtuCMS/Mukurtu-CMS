<?php

namespace Drupal\facets\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetInterface;
use Drupal\facets\FacetSource\FacetSourcePluginManager;
use Drupal\facets\FacetSource\SearchApiFacetSourceInterface;
use Drupal\facets\Processor\ProcessorPluginManager;
use Drupal\views\Plugin\views\display\Block;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for creating and editing facets.
 */
class FacetSettingsForm extends EntityForm {

  /**
   * The plugin manager for facet sources.
   *
   * @var \Drupal\facets\FacetSource\FacetSourcePluginManager
   */
  protected $facetSourcePluginManager;

  /**
   * The plugin manager for processors.
   *
   * @var \Drupal\facets\Processor\ProcessorPluginManager
   */
  protected $processorPluginManager;

  /**
   * The url generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Constructs a FacetForm object.
   *
   * @param \Drupal\facets\FacetSource\FacetSourcePluginManager $facet_source_plugin_manager
   *   The plugin manager for facet sources.
   * @param \Drupal\facets\Processor\ProcessorPluginManager $processor_plugin_manager
   *   The plugin manager for processors.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator.
   */
  public function __construct(FacetSourcePluginManager $facet_source_plugin_manager, ProcessorPluginManager $processor_plugin_manager, ModuleHandlerInterface $module_handler, UrlGeneratorInterface $url_generator) {
    $this->facetSourcePluginManager = $facet_source_plugin_manager;
    $this->processorPluginManager = $processor_plugin_manager;
    $this->moduleHandler = $module_handler;
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.facets.facet_source'),
      $container->get('plugin.manager.facets.processor'),
      $container->get('module_handler'),
      $container->get('url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // If the form is being rebuilt, rebuild the entity with the current form
    // values.
    if ($form_state->isRebuilding()) {
      $this->entity = $this->buildEntity($form, $form_state);
    }

    $form = parent::form($form, $form_state);

    // Set the page title according to whether we are creating or editing the
    // facet.
    if ($this->getEntity()->isNew()) {
      $form['#title'] = $this->t('Add facet');
      $form['facets_3_exposed_filters'] = ['#markup' => '<div class="messages messages--warning">' . t('<strong>New in Facets 3: </strong>For new sites it is recommended to use the "Facets Exposed Filters" submodule for Facets on Views. This module uses native Views filters instead, which has many advantages.<br><a target="_blank" href="@documentation_url">Click here</a> for documentation on how to use this new workflow.', ['@documentation_url' => 'https://project.pages.drupalcode.org/facets/exposed_filters']) . '</div>'];
      $form['facets_3_block_support'] = ['#markup' => '<p>' . t('Adding Facets as a block <a target="_blank" href="@documentation_url_facet_blocks">is still supported</a> for backwards compatibility.', ['@documentation_url_facet_blocks' => 'https://project.pages.drupalcode.org/facets/facet_blocks_support']) . '</p>'];
    }
    else {
      $form['#title'] = $this->t('Facet settings for %label facet', ['%label' => $this->getEntity()->label()]);
    }

    $this->buildEntityForm($form, $form_state, $this->getEntity());

    $form['#attached']['library'][] = 'facets/drupal.facets.edit-facet';

    return $form;
  }

  /**
   * Builds the form for editing and creating a facet.
   *
   * @param array $form
   *   The form array for the complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param \Drupal\facets\FacetInterface $facet
   *   The facets facet entity that is being created or edited.
   */
  public function buildEntityForm(array &$form, FormStateInterface $form_state, FacetInterface $facet) {

    $facet_sources = [];
    foreach ($this->facetSourcePluginManager->getDefinitions() as $facet_source_id => $definition) {
      // For now, we hide the facet sources for views display default.
      // They should not be used to attach block facets.
      if (substr($definition["display_id"], 0, 14) == 'views_default:') {
        continue;
      }
      $facet_sources[$definition['id']] = !empty($definition['label']) ? $definition['label'] : $facet_source_id;
    }

    if (count($facet_sources) == 0) {
      $form['#markup'] = $this->t('You currently have no facet sources defined. You should start by adding a facet source before creating facets.');
      return;
    }

    $form['facet_source_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Facet source'),
      '#description' => $this->t('The source where this facet can find its fields.'),
      '#options' => $facet_sources,
      '#default_value' => $facet->getFacetSourceId(),
      '#required' => TRUE,
      '#ajax' => [
        'trigger_as' => ['name' => 'facet_source_configure'],
        'callback' => '::buildAjaxFacetSourceConfigForm',
        'wrapper' => 'facets-facet-sources-config-form',
        'method' => 'replaceWith',
        'effect' => 'fade',
      ],
    ];
    $form['facet_source_configs'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'facets-facet-sources-config-form',
      ],
      '#tree' => TRUE,
    ];
    $form['facet_source_configure_button'] = [
      '#type' => 'submit',
      '#name' => 'facet_source_configure',
      '#value' => $this->t('Configure facet source'),
      '#limit_validation_errors' => [['facet_source_id']],
      '#submit' => ['::submitAjaxFacetSourceConfigForm'],
      '#ajax' => [
        'callback' => '::buildAjaxFacetSourceConfigForm',
        'wrapper' => 'facets-facet-sources-config-form',
      ],
      '#attributes' => ['class' => ['js-hide']],
    ];
    $this->buildFacetSourceConfigForm($form, $form_state);

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#description' => $this->t('The administrative name used for this facet.'),
      '#default_value' => $facet->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $facet->id(),
      '#maxlength' => 50,
      '#required' => TRUE,
      '#machine_name' => [
        'exists' => [Facet::class, 'load'],
        'source' => ['name'],
      ],
      '#disabled' => !$facet->isNew(),
    ];
  }

  /**
   * Handles form submissions for the facet source subform.
   */
  public function submitAjaxFacetSourceConfigForm($form, FormStateInterface $form_state) {
    $form_state->setValue('id', NULL);
    $form_state->setRebuild();
  }

  /**
   * Handles changes to the selected facet sources.
   */
  public function buildAjaxFacetSourceConfigForm(array $form, FormStateInterface $form_state) {
    return $form['facet_source_configs'];
  }

  /**
   * Builds the configuration forms for all possible facet sources.
   *
   * @param array $form
   *   An associative array containing the initial structure of the plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   */
  public function buildFacetSourceConfigForm(array &$form, FormStateInterface $form_state) {
    $facet_source_id = $this->getEntity()->getFacetSourceId();

    if (!is_null($facet_source_id) && $facet_source_id !== '') {
      /** @var \Drupal\facets\FacetSource\FacetSourcePluginInterface $facet_source */
      $facet_source = $this->facetSourcePluginManager->createInstance($facet_source_id, ['facet' => $this->getEntity()]);

      if ($config_form = $facet_source->buildConfigurationForm([], $form_state)) {
        $form['facet_source_configs'][$facet_source_id]['#type'] = 'container';
        $form['facet_source_configs'][$facet_source_id]['#attributes'] = ['class' => ['facet-source-field-wrapper']];
        $form['facet_source_configs'][$facet_source_id]['#title'] = $this->t('%plugin settings', ['%plugin' => $facet_source->getPluginDefinition()['label']]);
        $form['facet_source_configs'][$facet_source_id] += $config_form;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $facet_source_id = $form_state->getValue('facet_source_id');
    if (!is_null($facet_source_id) && $facet_source_id !== '') {
      /** @var \Drupal\facets\FacetSource\FacetSourcePluginInterface $facet_source */
      $facet_source = $this->facetSourcePluginManager->createInstance($facet_source_id, ['facet' => $this->getEntity()]);
      $facet_source->validateConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var \Drupal\facets\FacetInterface $facet */
    $facet = $this->getEntity();
    $is_new = $facet->isNew();
    if ($is_new) {
      // On facet creation, enable all locked processors by default, using their
      // default settings.
      $stages = $this->processorPluginManager->getProcessingStages();
      $processors_definitions = $this->processorPluginManager->getDefinitions();

      foreach ($processors_definitions as $processor_id => $processor) {
        $is_locked = isset($processor['locked']) && $processor['locked'] == TRUE;
        $is_default_enabled = isset($processor['default_enabled']) && $processor['default_enabled'] == TRUE;
        if ($is_locked || $is_default_enabled) {
          $weights = [];
          foreach ($stages as $stage_id => $stage) {
            if (isset($processor['stages'][$stage_id])) {
              $weights[$stage_id] = $processor['stages'][$stage_id];
            }
          }
          $facet->addProcessor([
            'processor_id' => $processor_id,
            'weights' => $weights,
            'settings' => [],
          ]);
        }
      }

      // Set a default widget for new facets.
      $facet->setWidget('links');
      $facet->setUrlAlias($form_state->getValue('id'));
      $facet->setWeight(0);

      // Set default empty behaviour.
      $facet->setEmptyBehavior(['behavior' => 'none']);
      $facet->setOnlyVisibleWhenFacetSourceIsVisible(TRUE);
    }

    $facet_source_id = $form_state->getValue('facet_source_id');
    if (!is_null($facet_source_id) && $facet_source_id !== '') {
      /** @var \Drupal\facets\FacetSource\FacetSourcePluginInterface $facet_source */
      $facet_source = $this->facetSourcePluginManager->createInstance($facet_source_id, ['facet' => $this->getEntity()]);
      $facet_source->submitConfigurationForm($form, $form_state);
    }
    $facet->save();

    if ($is_new) {
      if ($this->moduleHandler->moduleExists('block')) {
        $message = $this->t(
          'Facet %name has been created. Go to the <a href=":block_overview">Block overview page</a> to place the new block in the desired region.',
          [
            '%name' => $facet->getName(),
            ':block_overview' => $this->urlGenerator->generateFromRoute('block.admin_display'),
          ]
        );
        $this->messenger()->addMessage($message);
        $form_state->setRedirect('entity.facets_facet.edit_form', ['facets_facet' => $facet->id()]);
      }
    }
    else {
      $this->messenger()->addMessage($this->t('Facet %name has been updated.', ['%name' => $facet->getName()]));
    }

    [$type] = explode(':', $facet_source_id);
    if ($type !== 'search_api') {
      return $facet;
    }

    // Ensure that the caching of the view display is disabled, so the search
    // correctly returns the facets.
    if (isset($facet_source) && $facet_source instanceof SearchApiFacetSourceInterface) {
      $view = $facet_source->getViewsDisplay();
      if ($view !== NULL) {
        if ($view->display_handler instanceof Block) {
          $facet->setOnlyVisibleWhenFacetSourceIsVisible(FALSE);
        }
        $views_cache_type = $view->display_handler->getOption('cache')['type'];
        if ($views_cache_type !== 'search_api_none') {
          $this->messenger()->addMessage($this->t('You may experience issues, because %view use cache. In case you will try to turn set cache plugin to none.', ['%view' => $view->storage->label()]));
        }
      }
    }

    return $facet;
  }

}
