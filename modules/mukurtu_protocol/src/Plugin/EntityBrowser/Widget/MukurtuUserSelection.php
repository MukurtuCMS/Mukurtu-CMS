<?php

namespace Drupal\mukurtu_protocol\Plugin\EntityBrowser\Widget;

use Drupal\entity_browser\WidgetBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\og\OgRoleInterface;

/**
 * Uses an entity query to provide entity listing in a browser's widget.
 *
 * @EntityBrowserWidget(
 *   id = "mukurtu_user_selection",
 *   label = @Translation("Mukurtu User Selection"),
 *   provider = "mukurtu_protocol",
 *   description = @Translation("Select users for protocols and communities using Mukurtu roles."),
 *   auto_select = TRUE
 * )
 */
class MukurtuUserSelection extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The community or protocol to select users for.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $group;

  /**
   * {@inheritDoc}
   */
  protected function handleWidgetContext($widget_context) {
    parent::handleWidgetContext($widget_context);
    $this->group = $widget_context['group'] ?? NULL;
  }

  /**
   * Builds an EntityQuery to get referenceable communities for protocols.
   *
   * @param string|null $match
   *   (Optional) Text to match the label against. Defaults to NULL.
   * @param string $match_operator
   *   (Optional) The operation the matching should be done with. Defaults
   *   to "CONTAINS".
   * @param mixed $exclude
   *   (Optional) The array of UIDs to exclude from the query. Defaults to
   *   an empty array.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The EntityQuery object with the basic conditions and sorting applied to
   *   it.
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS', $exclude = []) {
    $query = $this->entityTypeManager->getStorage('user')->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('uid', 0, '<>');
    $query->condition('status', 1);
    $query->pager(20);

    if (isset($match)) {
      $conditionGroup = $query->orConditionGroup()
        ->condition('name', $match, $match_operator)
        ->condition('mail', $match, $match_operator);
      $query->condition($conditionGroup);
    }

    // Exclude already selected.
    if (!empty($exclude)) {
      $query->condition('uid', $exclude, 'NOT IN');
    }

    // Get the group object (community/protocol).
    if ($this->group) {
      // If we have a protocol, we want to limit the user selection to
      // the members of the owning community/communities.
      if ($this->group instanceof Protocol) {
        /** @var \Drupal\mukurtu_protocol\Entity\Protocol $group */
        $communities = $this->group->getCommunities();
        $membership_manager = \Drupal::service('og.membership_manager');

        $inCommunity = $query->orConditionGroup();
        // Build a list of UIDs for each owning community.
        foreach ($communities as $community) {
          $communityMembers = [];
          $memberships = $membership_manager->getGroupMembershipsByRoleNames($community, [OgRoleInterface::AUTHENTICATED]);
          foreach ($memberships as $membership) {
            $uid = $membership->getOwnerId();
            $communityMembers[$uid] = $uid;
          }

          // Add to the OR condition.
          $inCommunity->condition('uid', $communityMembers, 'IN');
        }

        // Attach the entire OR condition.
        $query->condition($inCommunity);
      }
    }

    return $query;
  }

  /**
   * Build a label from a user.
   */
  protected function buildUserLabel($user) {
    $name = $user->getAccountName();
    $email = $user->getEmail();
    return "$name ($email)";
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);

    $form['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#default_value' => '',
      '#size' => 60,
      '#maxlength' => 128,
    ];

    $form['search_submit'] = [
      '#type' => 'button',
      '#value' => $this->t('Search'),
    ];

    // Get the already selected users so we can remove them from the query.
    $storage = $form_state->getStorage();
    $selected = $storage['entity_browser']['selected_entities'] ?? [];
    $excludeUIDs = array_map(fn($user) => $user->id(), $selected);

    // Build the query for the user selection.
    $query = $this->buildEntityQuery($form_state->getValue('search'), 'CONTAINS', $excludeUIDs);
    $results = $query->execute();

    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($results);

    foreach ($users as $user) {
      $form['users']['user:' . $user->id()] = [
        '#type' => 'checkbox',
        '#title' => $this->buildUserLabel($user),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    $entities = [];

    $values = $form_state->getValues();
    $userKeys = array_filter(array_keys($values), fn($key) => stripos($key, 'user:') !== FALSE);

    foreach ($userKeys as $userKey) {
      if ($values[$userKey]) {
        list(, $uid) = explode(':', $userKey);
        $entities[] = $this->entityTypeManager->getStorage('user')->load($uid);
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    $entities = $this->prepareEntities($form, $form_state);
    $this->selectEntities($entities, $form_state);
  }

}
