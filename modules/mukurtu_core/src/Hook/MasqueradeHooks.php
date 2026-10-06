<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Accessibility fixes for the contrib Masquerade module's UI.
 */
class MasqueradeHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_entity_operation_alter().
   *
   * Names the user in the "Masquerade as" operation's accessible name, the way
   * core's own Edit, Delete and View operations do, so a screen reader can
   * tell which row it belongs to.
   */
  #[Hook('entity_operation_alter')]
  public function entityOperationAlter(array &$operations, EntityInterface $entity): void {
    if ($entity->getEntityTypeId() !== 'user' || !isset($operations['masquerade']['url'])) {
      return;
    }

    $entity = \Drupal::service('mukurtu_core.entity_translation_resolver')->translate($entity);
    $url = $operations['masquerade']['url'];
    $attributes = $url->getOption('attributes') ?: [];
    $attributes['aria-label'] = $this->t('Masquerade as @entity_label', ['@entity_label' => $entity->label()]);
    $url->setOption('attributes', $attributes);
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'masquerade_block_form'.
   *
   * Shows the field's label, since its placeholder disappears once someone
   * starts typing. After a successful switch, goes to the front page and
   * confirms the switch. Otherwise the form reloads /masquerade, which is
   * forbidden while masquerading, and shows an access-denied page instead.
   */
  #[Hook('form_masquerade_block_form_alter')]
  public function formMasqueradeBlockFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['autocomplete']['masquerade_as']['#title_display'] = 'before';
    $form['#submit'][] = [static::class, 'masqueradeBlockFormSubmit'];
  }

  /**
   * Form submission handler for 'masquerade_block_form'.
   *
   * Runs after the form's own submit handler has switched the user.
   */
  public static function masqueradeBlockFormSubmit(array &$form, FormStateInterface $form_state): void {
    if (!\Drupal::service('masquerade')->isMasquerading()) {
      return;
    }

    $target_account = $form_state->getValue('masquerade_target_account');
    \Drupal::messenger()->addStatus(t('You are now masquerading as @user.', [
      '@user' => $target_account->getDisplayName(),
    ]));
    $form_state->setRedirectUrl(Url::fromRoute('<front>'));
  }

}
