<?php

namespace Drupal\mukurtu_community_records\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Configuration form for commmunity records.
 */
class MukurtuCommunityRecordOrderForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'mukurtu_community_records.settings';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_community_records_order_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['helper'] = [
      '#type' => 'item',
      '#description' => $this->t('Choose the ordering for community records. Communities with a lower weight will appear first in the record listing.'),
    ];

    $form['table-row'] = [
      '#type' => 'table',
      '#header' => [
        $this
          ->t('Name'),
        $this
          ->t('Weight'),
      ],
      '#empty' => $this
        ->t('There are no communities.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];

    // Get all the communities. We are skipping the access check here, so
    // we could be leaking community names to admins.
    $query = $this->entityTypeManager->getStorage('community')->getQuery();
    $ids = $query->accessCheck(FALSE)->execute();
    $communities = $this->entityTypeManager->getStorage('community')->loadMultiple($ids);

    // Get the existing community weights.
    $weights = $config->get('community_record_weights') ?? [];

    // Order the communities by weight and check if any unweighted communities
    // are now present.
    $weightedCommunities = [];
    $dupes = [];
    $maxWeight = !empty($weights) ? max($weights) + 1 : 0;
    foreach ($communities as $community) {
      $weight = $weights[$community->id()] ?? $maxWeight++;
      if (isset($weightedCommunities[(string) $weight])) {
        $dupes[$weight] = ($dupes[$weight] ?? 0) + 1;
        $weight = $weight . '.' . $dupes[$weight];
      }
      else {
        $weight = (string) $weight;
      }
      $weightedCommunities[$weight] = $community;
    }

    ksort($weightedCommunities);

    // Build the table.
    foreach ($weightedCommunities as $row) {
      $form['table-row'][$row->id()]['#attributes']['class'][] = 'draggable';
      $form['table-row'][$row->id()]['#weight'] = $weights[$row->id()];
      $form['table-row'][$row->id()]['name'] = [
        '#markup' => $row->getName(),
      ];
      $form['table-row'][$row->id()]['weight'] = [
        '#type' => 'weight',
        '#title' => $this
          ->t('Weight for @title', [
            '@title' => $row->getName(),
          ]),
        '#title_display' => 'invisible',
        '#default_value' => $weights[$row->id()],
        '#attributes' => [
          'class' => [
            'table-sort-weight',
          ],
        ],
      ];
    }
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this
        ->t('Save All Changes'),
    ];
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => 'Cancel',
      '#attributes' => [
        'title' => $this
          ->t('Return to the dashboard'),
      ],
      '#submit' => [
        '::cancel',
      ],
      '#limit_validation_errors' => [],
    ];
    return $form;
  }

  /**
   * Form submission handler for the 'Cancel' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function cancel(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('mukurtu_core.dashboard');
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    // Build the new weights array from the form.
    // The weights array format is community entity ID => weight.
    $weights = [];
    $submission = $form_state->getValue('table-row');
    foreach ($submission as $id => $item) {
      $weights[intval($id)] = $item['weight'];
    }

    // Save the new weights array to config.
    $config->set('community_record_weights', $weights);
    $config->save();

    // Give the user a success message.
    $this->messenger()->addStatus($this->t('The community ordering for community records was saved.'));
  }

}
