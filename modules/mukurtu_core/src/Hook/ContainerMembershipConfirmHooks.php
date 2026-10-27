<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\mukurtu_core\Plugin\Action\ContainerMembershipActionBase;

/**
 * Names the target on VBO's confirmation step for bulk removals.
 *
 * Remove from Collection and Remove from Word List confirm before running,
 * but VBO's page doesn't say which collection or word list was chosen.
 */
class ContainerMembershipConfirmHooks {

  /**
   * Implements hook_form_FORM_ID_alter() for VBO's confirmation form.
   */
  #[Hook('form_views_bulk_operations_confirm_action_alter')]
  public function confirmActionAlter(array &$form, FormStateInterface $form_state): void {
    $data = $form_state->get('views_bulk_operations') ?? [];
    $manager = \Drupal::service('plugin.manager.views_bulk_operations_action');
    $definition = $manager->getDefinition($data['action_id'] ?? '', FALSE);
    if (!$definition || !is_a($definition['class'], ContainerMembershipActionBase::class, TRUE)) {
      return;
    }

    // With every result selected VBO has no item list; name the target only.
    $items = [];
    if (empty($data['exclude_mode'])) {
      $entity_type_manager = \Drupal::entityTypeManager();
      // A list item is [base field value, langcode, entity type ID, entity ID].
      foreach ($data['list'] ?? [] as $item) {
        [, $langcode, $entity_type_id, $id] = $item + [NULL, NULL, NULL, NULL];
        $entity = $entity_type_id && $id ? $entity_type_manager->getStorage($entity_type_id)->load($id) : NULL;
        if ($entity && $langcode && $entity->hasTranslation($langcode)) {
          $entity = $entity->getTranslation($langcode);
        }
        if ($entity) {
          $items[] = $entity;
        }
      }
    }

    /** @var \Drupal\mukurtu_core\Plugin\Action\ContainerMembershipActionBase $action */
    $action = $manager->createInstance($data['action_id'], $data['configuration'] ?? []);
    $notice = $action->removalNotice($items);
    if ($notice) {
      $form['mukurtu_container_target'] = $notice;
    }
  }

}
