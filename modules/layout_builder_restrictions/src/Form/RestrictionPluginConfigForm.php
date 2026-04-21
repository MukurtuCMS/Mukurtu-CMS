<?php

namespace Drupal\layout_builder_restrictions\Form;

use Drupal\Core\Config\ConfigManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder_restrictions\Plugin\LayoutBuilderRestrictionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The form for the restriction plugin.
 */
class RestrictionPluginConfigForm extends ConfigFormBase {

  /**
   * The UI for managing Layout Builder Restrictions Plugins.
   *
   * @var \Drupal\layout_builder_restrictions\Plugin\LayoutBuilderRestrictionManager
   */
  protected $pluginManagerLayoutBuilderRestriction;
  /**
   * Drupal\Core\Config\ConfigManager definition.
   *
   * @var \Drupal\Core\Config\ConfigManager
   */
  protected $configManager;

  /**
   * Constructs a new RestrictionPluginConfigForm object.
   */
  public function __construct(
    LayoutBuilderRestrictionManager $plugin_manager_layout_builder_restriction,
    ConfigManager $config_manager,
  ) {
    $this->pluginManagerLayoutBuilderRestriction = $plugin_manager_layout_builder_restriction;
    $this->configManager = $config_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.layout_builder_restriction'),
      $container->get('config.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'restriction_plugin_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $plugin_list = $this->pluginManagerLayoutBuilderRestriction->getSortedPlugins(TRUE);

    $form['plugin-table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Plugin'),
        $this->t('ID'),
        $this->t('Enabled'),
        $this->t('Weight'),
      ],
      '#empty' => $this->t('There are no restriction plugins defined.'),
      // TableDrag: Each array value is a list of callback arguments for
      // drupal_add_tabledrag().
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'plugin-table-order-weight',
        ],
      ],
      '#prefix' => '<p>Set the order of Layout Builder Restriction plugin execution, and enable or disable as needed.</p>',
    ];
    // Build the table rows and columns.
    // The first nested level in the render array forms the table row,
    // on which you likely want to set #attributes and #weight.
    foreach ($plugin_list as $id => $data) {
      // TableDrag: Mark the table row as draggable.
      $form['plugin-table'][$id]['#attributes']['class'][] = 'draggable';
      // Sort the row according to its existing/configured weight.
      $form['plugin-table'][$id]['#weight'] = $data['weight'];
      if ($data['description']) {
        $form['plugin-table'][$id]['title'] = [
          '#markup' => '<strong>' . $data['title'] . '</strong><br>' . $data['description'],
        ];
      }
      else {
        $form['plugin-table'][$id]['title'] = [
          '#markup' => '<strong>' . $data['title'] . '</strong>',
        ];
      }
      $form['plugin-table'][$id]['id'] = [
        '#plain_text' => $id,
      ];
      $form['plugin-table'][$id]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enabled'),
        '#title_display' => 'invisible',
        '#default_value' => $data['enabled'],
        // Classify the weight element for #tabledrag.
        '#attributes' => ['class' => ['plugin-table-enabled']],
      ];
      // TableDrag: Weight column element.
      $form['plugin-table'][$id]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $id]),
        '#title_display' => 'invisible',
        '#default_value' => $data['weight'],
        // Classify the weight element for #tabledrag.
        '#attributes' => ['class' => ['plugin-table-order-weight']],
      ];

    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $data_to_save = [];
    $restriction_definitions = $this->pluginManagerLayoutBuilderRestriction->getDefinitions();
    foreach ($form_state->getValue('plugin-table') as $plugin_id => $vals) {
      // Verify we have a registered plugin key.
      if (isset($restriction_definitions[$plugin_id])) {
        $data_to_save[$plugin_id] = [
          'weight' => (int) $vals['weight'],
          'enabled' => (bool) $vals['enabled'],
        ];
      }
    }
    // Save config.
    $plugin_weighting_config = $this->configFactory()->getEditable('layout_builder_restrictions.plugins');
    $plugin_weighting_config->set('plugin_config', $data_to_save);
    $plugin_weighting_config->save();
  }

}
