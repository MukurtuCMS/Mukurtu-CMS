<?php

namespace Drupal\layout_builder_restrictions_by_region\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\layout_builder_restrictions\Traits\PluginHelperTrait;
use Drupal\layout_builder_restrictions_by_region\Traits\LayoutBuilderRestrictionsByRegionHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides form for designating allowed blocks.
 */
class AllowedBlocksForm extends FormBase {

  use PluginHelperTrait;
  use LayoutBuilderRestrictionsByRegionHelperTrait;

  /**
   * Request stack that controls the lifecycle of requests.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The layout manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutManager;

  /**
   * Manages entity type plugin definitions.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Creates a private temporary storage for a collection.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $privateTempStoreFactory;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Turns a render array into a HTML string.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * An array of allowed block categories, or empty.
   *
   * @var array
   */
  protected $allowedBlockCategories;

  /**
   * Layout/Region-specific selections, prior to full form submit.
   *
   * @var array
   */
  protected $tempData;

  /**
   * An array of allowlisted blocks, by category.
   *
   * @var array
   */
  protected $allowlistedBlocks;

  /**
   * An array of denylisted blocks, by category.
   *
   * @var array
   */
  protected $denylistedBlocks;

  /**
   * An array of restricted block categories.
   *
   * @var array
   */
  protected $restrictedCategories;

  /**
   * The machine name of the layout plugin.
   *
   * @var string
   */
  protected $layoutPluginId;

  /**
   * The machine name of the region.
   *
   * @var string
   */
  protected $regionId;

  /**
   * The machine name of the static id.
   *
   * @var string
   */
  protected $staticId;

  /**
   * The ModalFormController constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack that controls the lifecycle of requests.
   * @param \Drupal\Core\Block\LayoutPluginManagerInterface $layout_manager
   *   The layout plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   Manages entity type plugin definitions.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $private_temp_store_factory
   *   Creates a private temporary storage for a collection.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   Turns a render array into a HTML string.
   */
  public function __construct(RequestStack $request_stack, LayoutPluginManagerInterface $layout_manager, EntityTypeManager $entity_type_manager, PrivateTempStoreFactory $private_temp_store_factory, MessengerInterface $messenger, Renderer $renderer) {
    $this->requestStack = $request_stack;
    $this->layoutManager = $layout_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->privateTempStoreFactory = $private_temp_store_factory;
    $this->messenger = $messenger;
    $this->renderer = $renderer;

    // Build data for current form.
    $current_request = $this->requestStack->getCurrentRequest();
    $entity_view_display_id = $current_request->query->get('entity_view_display_id');
    $display = $this->entityTypeManager
      ->getStorage('entity_view_display')
      ->load($entity_view_display_id);
    $this->layoutPluginId = $current_request->query->get('layout_plugin');
    $this->regionId = $current_request->query->get('region_id');
    $this->allowedBlockCategories = $display->getThirdPartySetting('layout_builder_restrictions', 'allowed_block_categories', []);
    $third_party_settings = $display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction_by_region', []);
    $this->allowlistedBlocks = (isset($third_party_settings['allowlisted_blocks'][$this->layoutPluginId][$this->regionId])) ? $third_party_settings['allowlisted_blocks'][$this->layoutPluginId][$this->regionId] : [];
    $this->denylistedBlocks = (isset($third_party_settings['denylisted_blocks'][$this->layoutPluginId][$this->regionId])) ? $third_party_settings['denylisted_blocks'][$this->layoutPluginId][$this->regionId] : [];
    $this->restrictedCategories = (isset($third_party_settings['restricted_categories'][$this->layoutPluginId][$this->regionId])) ? $third_party_settings['restricted_categories'][$this->layoutPluginId][$this->regionId] : [];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('plugin.manager.core.layout'),
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
      $container->get('messenger'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_by_region_allowed_blocks';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $current_request = $this->requestStack->getCurrentRequest();
    $static_id = $current_request->query->get('static_id');
    $entity_view_display_id = $current_request->query->get('entity_view_display_id');
    $layout_plugin = $current_request->query->get('layout_plugin');
    $region_id = $current_request->query->get('region_id');
    $display = $this->entityTypeManager
      ->getStorage('entity_view_display')
      ->load($entity_view_display_id);
    // Load tempstore data.
    $tempstore = $this->privateTempStoreFactory;
    $store = $tempstore->get('layout_builder_restrictions_by_region');
    $temp_data = $store->get($static_id . ':' . $layout_plugin . ':' . $region_id);

    $layout_definition = $this->layoutManager->getDefinition($layout_plugin);
    $regions = $layout_definition->getRegions();
    $regions['all_regions'] = [
      'label' => $this->t('All regions'),
    ];
    $region_label = $regions[$region_id]['label']->render();
    $layout_label = $layout_definition->getLabel();

    $form['config_context_markup'] = [
      '#markup' => $this->t('<strong>Layout:</strong> @layout_label<br><strong>Region:</strong> @region_label', [
        '@layout_label' => $layout_label,
        '@region_label' => $region_label,
      ]),
    ];

    foreach ($this->getBlockDefinitions($display) as $category => $data) {
      $title = $data['label'];
      if (!empty($data['translated_label'])) {
        $title = $data['translated_label'];
      }
      $category_form = [
        '#type' => 'fieldset',
        '#title' => $title,
      ];
      $category_form['restriction_behavior'] = [
        '#type' => 'radios',
        '#options' => [
          "all" => $this->t('Allow all existing & new %category blocks.', ['%category' => $data['label']]),
          "restrict_all" => $this->t('Restrict all existing & new %category blocks.', ['%category' => $data['label']]),
          "allowlisted" => $this->t('Allow specific %category blocks:', ['%category' => $data['label']]),
          "denylisted" => $this->t('Restrict specific %category blocks:', ['%category' => $data['label']]),
        ],
        '#parents' => [
          'allowed_blocks',
          $category,
          'restriction',
        ],
      ];
      $category_form['restriction_behavior']['#default_value'] = $this->getCategoryBehavior($category, $temp_data);
      $category_form['allowed_blocks'] = [
        '#type' => 'container',
        '#states' => [
          'invisible' => [
            [':input[name="allowed_blocks[' . $category . '][restriction]"]' => ['value' => "all"]],
            [':input[name="allowed_blocks[' . $category . '][restriction]"]' => ['value' => "restrict_all"]],
          ],
        ],
      ];
      foreach ($data['definitions'] as $block_id => $block) {
        $category_form['allowed_blocks'][$block_id] = [
          '#type' => 'checkbox',
          '#title' => $block['admin_label'],
          '#default_value' => $this->getBlockDefault($block_id, $category, $temp_data),
          '#parents' => [
            'allowed_blocks',
            $category,
            'allowed_blocks',
            $block_id,
          ],
        ];
      }

      if ($category == 'Custom blocks' || $category == 'Content block' || $category == 'Custom block types') {
        $category_form['description'] = [
          '#type' => 'container',
          '#children' => $this->t('<p>In the event both <em>Custom Block Types</em> and <em>Content Blocks</em> restrictions are enabled, <em>Custom Block Types</em> restrictions are disregarded.</p>'),
          '#states' => [
            'visible' => [
              ':input[name="allowed_blocks[' . $category . '][restriction]"]' => ['value' => "restricted"],
            ],
          ],
        ];
      }
      $form['allowed_blocks'][$category] = $category_form;
    }

    $form['static_id'] = [
      '#type' => 'hidden',
      '#value' => $static_id,
    ];

    $form['layout_plugin'] = [
      '#type' => 'hidden',
      '#value' => $layout_plugin,
    ];

    $form['region_id'] = [
      '#type' => 'hidden',
      '#value' => $region_id,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'event' => 'click',
        'url' => Url::fromRoute("layout_builder_restrictions_by_region.{$display->getTargetEntityTypeId()}_allowed_blocks", [
          'static_id' => $static_id,
          'entity_view_display_id' => $entity_view_display_id,
          'layout_plugin' => $layout_plugin,
          'region_id' => $region_id,
        ]),
        'options' => [
          'query' => [
            FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Callback function for AJAX form submission.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $static_id = $values['static_id'];
    $layout_plugin = $values['layout_plugin'];
    $region_id = $values['region_id'];
    $categories = $values['allowed_blocks'];
    $block_restrictions = [];
    if (!empty($categories)) {
      foreach ($categories as $category => $category_setting) {
        $restriction_type = $category_setting['restriction'];
        $block_restrictions[$category]['restriction_type'] = $restriction_type;
        if (in_array($restriction_type, ['allowlisted', 'denylisted'])) {
          foreach ($category_setting['allowed_blocks'] as $block_id => $block_setting) {
            if ($block_setting == '1') {
              // Include only checked blocks.
              $block_restrictions[$category]['restrictions'][$block_id] = $block_setting;
            }
          }
        }
      }
    }

    // Write settings to tempStore.
    $tempstore = $this->privateTempStoreFactory;
    $store = $tempstore->get('layout_builder_restrictions_by_region');
    $store->set($static_id . ':' . $layout_plugin . ':' . $region_id, $block_restrictions);

    $response = new AjaxResponse();

    if ($form_state->getErrors()) {
      // Could there ever be form errors?
      // It's all checkboxes and radio buttons.
    }
    else {
      $command = new CloseModalDialogCommand();
      $response->addCommand($command);

      $this->messenger->addWarning($this->t('There is unsaved Layout Builder Restrictions configuration.'));
      $status_messages = ['#type' => 'status_messages'];
      $messages = $this->renderer->renderRoot($status_messages);
      $messages = '<div id="layout-builder-restrictions-messages">' . $messages . '</div>';
      if (!empty($messages)) {
        $response->addCommand(new ReplaceCommand('#layout-builder-restrictions-messages', $messages));
      }

      $region_status = $this->RegionRestrictionStatusString($layout_plugin, $region_id, $static_id, NULL);
      $response->addCommand(new ReplaceCommand('#restriction-status--' . $layout_plugin . '--' . $region_id . ' .data', '<span class="data">' . $region_status . '</span>'));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

  /**
   * Business logic to set category to 'all', 'allowlisted' or 'denylisted'.
   *
   * @param string $category
   *   The block's category.
   * @param mixed $temp_data
   *   The data stored between AJAX submits or null.
   *
   * @return string
   *   The value 'all', 'allowlisted', 'denylisted', or 'restrict_all'.
   */
  protected function getCategoryBehavior($category, $temp_data) {
    // Check whether this is a newly available category that has been
    // restricted previously.
    $category_is_restricted = (!empty($this->allowedBlockCategories) && !in_array($category, $this->allowedBlockCategories));
    // Attempt to retrieve default value from tempStore, then from config
    // before settings to 'all'.
    if (isset($temp_data) && isset($temp_data[$category]['restriction_type'])) {
      return $temp_data[$category]['restriction_type'];
    }
    else {
      if (isset($this->allowlistedBlocks) && in_array($category, array_keys($this->allowlistedBlocks))) {
        return "allowlisted";
      }
      elseif (isset($this->denylistedBlocks) && in_array($category, array_keys($this->denylistedBlocks))) {
        return "denylisted";
      }
      elseif (in_array($category, $this->restrictedCategories)) {
        return 'restrict_all';
      }
      elseif ($category_is_restricted) {
        // If there is no configuration, but the category hasn't been 'allowed',
        // use 'allowlisted' to preset this as if all blocks were restricted.
        return "restrict_all";
      }
      else {
        return 'all';
      }
    }
  }

  /**
   * Business logic to set category to 'all', 'allowlisted' or 'denylisted'.
   *
   * @param string $block_id
   *   The Drupal block ID.
   * @param string $category
   *   The block's category.
   * @param mixed $temp_data
   *   The data stored between AJAX submits or null.
   *
   * @return bool
   *   Whether or not the block is stored in the restriction type.
   */
  protected function getBlockDefault($block_id, $category, $temp_data) {
    // Attempt to retrieve default value from tempStore, then from config.
    if (!is_null($temp_data)) {
      if (isset($temp_data[$category]['restrictions'])) {
        return in_array($block_id, array_keys($temp_data[$category]['restrictions']));
      }
      else {
        return FALSE;
      }
    }
    else {
      if (isset($this->allowlistedBlocks[$category])) {
        return in_array($block_id, $this->allowlistedBlocks[$category]);
      }
      if (isset($this->denylistedBlocks[$category])) {
        return in_array($block_id, $this->denylistedBlocks[$category]);
      }
      else {
        return FALSE;
      }
    }
    return FALSE;
  }

}
