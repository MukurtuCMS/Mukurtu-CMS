<?php

declare(strict_types=1);

namespace Drupal\mukurtu_protocol\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\mukurtu_protocol\Plugin\Action\ProtocolChangeActionBase;

/**
 * Warns on VBO's confirmation step before bulk protocol changes.
 *
 * The protocol actions always use the confirmation step (see their
 * confirm_form_route_name). This adds the list of items where the editor or
 * other members would lose access, so the editor can cancel first.
 */
class BulkProtocolConfirmHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_form_FORM_ID_alter() for VBO's confirmation form.
   */
  #[Hook('form_views_bulk_operations_confirm_action_alter')]
  public function confirmActionAlter(array &$form, FormStateInterface $form_state): void {
    $data = $form_state->get('views_bulk_operations') ?? [];
    $manager = \Drupal::service('plugin.manager.views_bulk_operations_action');
    $definition = $manager->getDefinition($data['action_id'] ?? '', FALSE);
    if (!$definition || !is_a($definition['class'], ProtocolChangeActionBase::class, TRUE)) {
      return;
    }

    // "Select all" leaves VBO without a list of items, so the change can't
    // be checked for lockouts before it runs.
    if (!empty($data['exclude_mode'])) {
      $form['mukurtu_protocol_select_all'] = $this->warning([
        '#markup' => $this->t("Protocol changes can't be applied to every item in the view at once. Go back and select the items to change."),
      ]);
      $form['actions']['submit']['#access'] = FALSE;
      return;
    }

    /** @var \Drupal\mukurtu_protocol\Plugin\Action\ProtocolChangeActionBase $action */
    $action = $manager->createInstance($data['action_id'], $data['configuration'] ?? []);
    $account = \Drupal::currentUser();
    $entity_type_manager = \Drupal::entityTypeManager();

    $notices = [];
    // A list item is [base field value, langcode, entity type ID, entity ID].
    foreach ($data['list'] ?? [] as $item) {
      [, $langcode, $entity_type_id, $id] = $item + [NULL, NULL, NULL, NULL];
      $entity = $entity_type_id && $id ? $entity_type_manager->getStorage($entity_type_id)->load($id) : NULL;
      if ($entity && $langcode && $entity->hasTranslation($langcode)) {
        $entity = $entity->getTranslation($langcode);
      }
      if (!$entity instanceof CulturalProtocolControlledInterface || !$action->access($entity, $account)) {
        continue;
      }
      foreach ($action->lossNotices($entity, $account) as $notice) {
        // Translation rows of one item give the same notice.
        $notices[(string) $notice] = $notice;
      }
    }

    if ($notices) {
      $form['mukurtu_protocol_loss'] = $this->warning([
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('Some people will lose access'),
        ],
        'list' => [
          '#theme' => 'item_list',
          '#items' => array_values($notices),
        ],
      ]);
    }
  }

  /**
   * Wraps content in a warning box shown above the item list.
   */
  protected function warning(array $content): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['messages', 'messages--warning']],
      '#weight' => -10,
    ] + $content;
  }

}
