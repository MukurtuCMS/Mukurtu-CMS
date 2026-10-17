<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\TermInterface;

/**
 * Hook implementations for the language vocabulary's language code.
 *
 * Dictionary output uses the code as an HTML lang attribute (#2328), so it
 * has to be shaped like a BCP 47 tag.
 */
final class LanguageCodeHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_entity_bundle_field_info_alter().
   */
  #[Hook('entity_bundle_field_info_alter')]
  public function entityBundleFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type, $bundle): void {
    if ($entity_type->id() === 'taxonomy_term' && $bundle === 'language' && isset($fields['field_language_code'])) {
      $fields['field_language_code']->addPropertyConstraints('value', ['MukurtuLanguageTag' => []]);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for taxonomy_term.
   *
   * Text fields keep whatever spaces were typed, and a stray one would end
   * up inside the lang attribute.
   */
  #[Hook('taxonomy_term_presave')]
  public function taxonomyTermPresave(TermInterface $term): void {
    if ($term->bundle() === 'language' && $term->hasField('field_language_code') && !$term->get('field_language_code')->isEmpty()) {
      $code = trim((string) $term->get('field_language_code')->value);
      $term->set('field_language_code', $code === '' ? NULL : $code);
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for taxonomy_term_language_form.
   *
   * Says what a missing code costs, next to the field where that is decided.
   *
   * Digital heritage's field_language has auto_create TRUE, so a term made
   * by typing a new language into that form arrives with no code and the
   * person who made it never saw this field. Text marked with such a term is
   * then announced with the page's language rules, which is the thing SC
   * 3.1.2 is about (#2354).
   *
   * Deliberately help text rather than a message, and deliberately only on
   * an existing term. The field's own description ends "Leave blank if
   * you're not sure", which is advice this codebase means: a community may
   * not want to assert a code for their language, and a wrong code is worse
   * than none. So this explains the consequence where the choice is made and
   * says nothing at all when someone decides blank is right. A new term has
   * no code yet by definition, so saying it there would be noise.
   */
  #[Hook('form_taxonomy_term_language_form_alter')]
  public function formTaxonomyTermLanguageFormAlter(array &$form, FormStateInterface $form_state): void {
    $term = $form_state->getFormObject()->getEntity();
    if (!$term instanceof TermInterface || $term->isNew()) {
      return;
    }
    if (!$term->hasField('field_language_code') || !$term->get('field_language_code')->isEmpty()) {
      return;
    }

    // The widget nests the textfield one level down; bail rather than guess
    // if a different widget is configured for this field.
    if (!isset($form['field_language_code']['widget'][0]['value']['#description'])) {
      return;
    }

    $element = &$form['field_language_code']['widget'][0]['value'];
    $element['#description'] = [
      'notice' => [
        '#markup' => '<strong>' . $this->t('This language has no code yet, so screen readers announce content marked with it using the page language instead.') . '</strong>',
        '#suffix' => '<br />',
      ],
      'original' => is_array($element['#description'])
        ? $element['#description']
        : ['#markup' => $element['#description']],
    ];
  }

}
