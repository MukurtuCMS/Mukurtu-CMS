<?php

namespace Drupal\mukurtu_local_contexts\Hook;

use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for mukurtu_local_contexts forms.
 */
class FormHooks {

  /**
   * Implements hook_form_alter().
   *
   * Attaches cross-field validation to any entity form exposing both the
   * Local Contexts project and label/notice fields, so a user cannot select
   * an entire project and also select individual labels/notices from that
   * same project (see issue #863) — that combination would otherwise render
   * duplicate Local Contexts information on the saved content.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!isset($form['field_local_contexts_projects']) || !isset($form['field_local_contexts_labels_and_notices'])) {
      return;
    }
    $form['#validate'][] = [self::class, 'validateNoProjectLabelOverlap'];
  }

  /**
   * Form validation handler for the Local Contexts project/label fields.
   *
   * Rebuilds the entity from submitted values (same as
   * ContentEntityForm::validateForm() does for its own constraint checks) so
   * both fields are read in their final, massaged shape rather than parsing
   * the widgets' raw submitted structures directly.
   */
  public static function validateNoProjectLabelOverlap(array &$form, FormStateInterface $form_state): void {
    $formObject = $form_state->getFormObject();
    if (!$formObject instanceof ContentEntityFormInterface) {
      return;
    }

    $entity = $formObject->buildEntity($form, $form_state);
    if (!$entity instanceof ContentEntityInterface || !$entity->hasField('field_local_contexts_projects') || !$entity->hasField('field_local_contexts_labels_and_notices')) {
      return;
    }

    $selectedProjectIds = [];
    foreach ($entity->get('field_local_contexts_projects') as $item) {
      if ($item->value !== NULL) {
        $selectedProjectIds[] = $item->value;
      }
    }

    if (empty($selectedProjectIds)) {
      return;
    }

    foreach ($entity->get('field_local_contexts_labels_and_notices') as $item) {
      if ($item->value === NULL) {
        continue;
      }
      [$project_id] = explode(':', $item->value, 2);
      if (in_array($project_id, $selectedProjectIds, TRUE)) {
        $form_state->setErrorByName('field_local_contexts_labels_and_notices', t('Remove individual labels/notices for any project you have already selected in full.'));
        return;
      }
    }
  }

}
