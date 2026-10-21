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
    $org = $this->reconcileOrganization($config->get('organization') ?? [], $communities);

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
          // The parent input shares the community cell, so keep it shown.
          'hidden' => FALSE,
        ],
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'field-weight',
        ],
      ]
    ];

    // Build rows, but only if there are communities on the site.
    if ($communities) {
      foreach ($org as $id => $communityOrg) {
        /** @var \Drupal\mukurtu_browse\Entity\CommunityInterface $community */
        $community = $communities[$id];
        $parent = $communityOrg['parent'];
        $weight = $communityOrg['weight'];
        $org[$id]['level'] = $this->getLevel($id, $org);
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
          // A hidden input, not a weight select, so tabledrag can set any
          // community ID as the parent.
          'parent' => [
            '#type' => 'hidden',
            '#default_value' => $parent ?? 0,
            '#parents' => ['communities', $id, 'parent'],
            '#attributes' => ['class' => ['field-parent']],
          ],
        ];

        $form['communities'][$id]['weight'] = [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @title', ['@title' => $community->getName()]),
          '#title_display' => 'invisible',
          '#default_value' => $weight ?? 0,
          '#attributes' => ['class' => ['field-weight']],
        ];
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    return $form;
  }

  /**
   * Matches the saved organization to the communities that exist.
   *
   * Entries for communities that no longer exist are dropped, children of a
   * missing parent move to the root level, and communities missing from the
   * saved organization are added to the end of the root level.
   *
   * @param array $org
   *   The saved organization, keyed by community ID.
   * @param \Drupal\mukurtu_protocol\Entity\CommunityInterface[] $communities
   *   All communities on the site, keyed by ID.
   *
   * @return array
   *   The organization, keyed by community ID, with 'parent' and 'weight'.
   */
  protected function reconcileOrganization(array $org, array $communities): array {
    $org = array_intersect_key($org, $communities);

    $maxWeight = 0;
    foreach ($org as $id => $settings) {
      $parent = intval($settings['parent'] ?? 0);
      if ($parent !== 0 && !isset($org[$parent])) {
        $parent = 0;
      }
      $org[$id] = [
        'parent' => $parent,
        'weight' => intval($settings['weight'] ?? 0),
      ];
      if ($parent === 0) {
        $maxWeight = max($maxWeight, $org[$id]['weight']);
      }
    }

    foreach (array_keys($communities) as $id) {
      if (!isset($org[$id])) {
        $org[$id] = ['parent' => 0, 'weight' => ++$maxWeight];
      }
    }

    return $org;
  }

  /**
   * Gets the indentation level of a community in the organization.
   *
   * @param int|string $id
   *   The community ID.
   * @param array $org
   *   The organization, keyed by community ID.
   *
   * @return int
   *   The number of ancestors, stopping if the parent chain loops.
   */
  protected function getLevel($id, array $org): int {
    $level = 0;
    $seen = [$id => TRUE];
    $parent = $org[$id]['parent'] ?? 0;
    while ($parent && isset($org[$parent]) && !isset($seen[$parent])) {
      $seen[$parent] = TRUE;
      $level++;
      $parent = $org[$parent]['parent'] ?? 0;
    }
    return $level;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::service('config.factory')->getEditable('mukurtu_protocol.community_organization');
    $values = $form_state->getValues();
    $communities = $values['communities'] ?? [];
    foreach ($communities as $id => &$community) {
      $community['parent'] = intval($community['parent']);
      // The parent is a hidden input, so only accept another listed community.
      if ($community['parent'] == $id || !isset($communities[$community['parent']])) {
        $community['parent'] = 0;
      }
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
