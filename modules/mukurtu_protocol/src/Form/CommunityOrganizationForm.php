<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class CommunityOrganizationForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'community_organization_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $communities = \Drupal::entityTypeManager()->getStorage('community')->loadMultiple();
    $config = $this->config('mukurtu_protocol.community_organization');
    $org = $config->get('organization');

    $form['description'] = [
      '#type' => 'item',
      '#description' => $this->t('Drag and indent the communities in the order you would like them to be shown on the site. This only changes site structure and does not impact community or cultural protocol memberships.'),
    ];

    $form['communities'] = [
      '#type' => 'table',
      '#caption' => $this->t('Site Community Organization'),
      '#header' => [
        $this->t('Community'),
        $this->t('Weight'),
        $this->t('Parent'),
      ],
      '#empty' => $this->t('No communities have been created on this site.'),
      '#tableselect' => FALSE,
      '#tabledrag' => [
        [
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'field-parent',
          'subgroup' => 'field-parent',
          'source' => 'community-id',
          'hidden' => TRUE,
        ],
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'field-weight',
        ],
      ]
    ];

    // Build rows.
    foreach ($org as $id => $communityOrg) {
      /** @var \Drupal\mukurtu_browse\Entity\CommunityInterface $community */
      $community = $communities[$id];
      $parent = $org[$community->id()]['parent'] ?? 0;
      $weight = $org[$community->id()]['weight'] ?? 0;
      $org[$id]['level'] = $parent == 0 ? 0 : ($org[$parent]['level'] + 1 ?? 0);
      $form['communities'][$id]['#weight'] = $weight;
      $form['communities'][$id]['#attributes']['class'][] = 'draggable';

      $form['communities'][$id]['label'] = [
        [
          '#theme' => 'indentation',
          '#size' => $org[$id]['level'],
        ],
        [
          '#plain_text' => $community->getName(),
        ],
        [
          '#type' => 'hidden',
          '#title_display' => 'invisible',
          '#value' => $community->id(),
          '#attributes' => ['class' => ['community-id']],
        ],
      ];

      $form['communities'][$id]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $community->getName()]),
        '#title_display' => 'invisible',
        '#default_value' => $weight ?? 0,
        '#attributes' => ['class' => ['field-weight']],
      ];

      $form['communities'][$id]['parent'] = [
        '#type' => 'weight',
        '#title' => $this->t('Parent for @title', ['@title' => $community->getName()]),
        '#title_display' => 'invisible',
        '#default_value' => $parent ?? 0,
        '#attributes' => ['class' => ['field-parent']],
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo We need validation.
    $config = \Drupal::service('config.factory')->getEditable('mukurtu_protocol.community_organization');
    $values = $form_state->getValues();
    $communities = $values['communities'] ?? [];
    foreach ($communities as &$community) {
      $community['parent'] = intval($community['parent']);
      $community['weight'] = intval($community['weight']);
      if (isset($community['label'])) {
        unset($community['label']);
      }
    }
    $config->set('organization', $communities)->save();

    // Invalidate cache tags to recalculate parent/child community fields.
    $ids = array_keys($communities);
    $tags = array_map(fn($id) => "community:$id", $ids);
    $tags[] = 'community_list';
    Cache::invalidateTags($tags);
  }

}
