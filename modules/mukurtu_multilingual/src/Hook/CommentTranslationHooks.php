<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multilingual\Hook;

use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Restores comment statistics when building a translation form.
 *
 * mukurtu_multilingual_install() deliberately marks every bundle's 'comment'
 * field non-translatable (comment stats shouldn't diverge per language), but
 * that exposes a Drupal core interaction: CommentWidget::massageFormValues()
 * always resets last_comment_timestamp/last_comment_uid/comment_count/etc.
 * to defaults on every submit ("we don't want to have them in form"),
 * expecting comment.module's own save-time bookkeeping to restore the real
 * values afterward. That restoration happens on ->save(), but
 * ContentEntityForm::validateForm() calls ->validate() on the built entity
 * *before* ->save() ever runs. Content moderation forces
 * EntityUntranslatableFieldsConstraintValidator's strict per-field check on
 * for nearly every Mukurtu bundle, so on a translation-add/edit form for any
 * content with an existing comment, the zeroed-out statistics differ from
 * the stored original and the save is rejected with "Non-translatable
 * fields can only be changed when updating the original language" - even
 * though nothing about commenting changed.
 */
class CommentTranslationHooks {

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof ContentEntityFormInterface) {
      return;
    }
    $entity = $form_object->getEntity();
    if (!$entity->hasField('comment') || $entity->isDefaultTranslation()) {
      return;
    }
    $form['#entity_builders'][] = [static::class, 'restoreCommentStatistics'];
  }

  /**
   * Entity builder: restores the 'comment' field from stored data.
   *
   * Can't read the correct value off $entity->getUntranslated() here: since
   * 'comment' is non-translatable, the translation object and its
   * untranslated counterpart share the same underlying field storage, so by
   * the time entity builders run (after widgets have already massaged the
   * submitted values), that shared value has already been zeroed too.
   * Loading the stored revision directly sidesteps that and also matches
   * exactly what EntityUntranslatableFieldsConstraintValidator itself
   * compares against.
   */
  public static function restoreCommentStatistics(string $entity_type_id, ContentEntityInterface $entity, array &$form, FormStateInterface $form_state): void {
    if ($entity->isDefaultTranslation() || !$entity->hasField('comment') || $entity->isNew()) {
      return;
    }
    $stored = \Drupal::entityTypeManager()
      ->getStorage($entity_type_id)
      ->loadRevision($entity->getLoadedRevisionId());
    if ($stored) {
      $entity->set('comment', $stored->get('comment')->getValue());
    }
  }

}
