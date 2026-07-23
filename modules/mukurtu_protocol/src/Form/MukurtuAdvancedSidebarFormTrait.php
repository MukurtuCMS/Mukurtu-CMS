<?php

namespace Drupal\mukurtu_protocol\Form;

/**
 * Builds the 'advanced' vertical-tabs sidebar shared by CommunityForm and
 * ProtocolForm, mirroring Drupal core's ContentEntityForm/NodeForm pattern
 * for entity types that don't set show_revision_ui.
 */
trait MukurtuAdvancedSidebarFormTrait {

  /**
   * Creates the 'advanced' vertical-tabs container.
   *
   * Must run BEFORE parent::form() so that the 'path' field widget
   * (\Drupal\path\Plugin\Field\FieldWidget\PathWidget::formElement())
   * sees $form['advanced'] already set and self-wraps into a details pane;
   * doing this after the field widgets are built is too late for that
   * auto-wrap to fire.
   */
  protected function injectAdvancedSidebarContainer(array &$form): void {
    if (!isset($form['advanced'])) {
      $form['advanced'] = [
        '#type' => 'vertical_tabs',
        '#weight' => 99,
      ];
    }
  }

  /**
   * Adds the 'Authoring information' group to the 'advanced' sidebar.
   *
   * Call after parent::form() has built the 'user_id' field widget.
   */
  protected function buildAdvancedSidebarAuthorGroup(array &$form): void {
    $form['author'] = [
      '#type' => 'details',
      '#title' => $this->t('Authoring information'),
      '#group' => 'advanced',
      '#weight' => 90,
      '#optional' => TRUE,
    ];
    if (isset($form['user_id'])) {
      $form['user_id']['#group'] = 'author';
    }
    if (isset($form['created'])) {
      $form['created']['#group'] = 'author';
    }
  }

  /**
   * Creates the 'Revision information' group in the 'advanced' sidebar.
   *
   * Idempotent: safe to call from both form() (for 'revision_log', built by
   * every operation) and buildForm() (for 'new_revision', added only for
   * existing entities), whichever runs first.
   */
  protected function ensureAdvancedSidebarRevisionGroup(array &$form): void {
    if (!isset($form['revision_information'])) {
      $form['revision_information'] = [
        '#type' => 'details',
        '#title' => $this->t('Revision information'),
        '#group' => 'advanced',
        '#weight' => 20,
        '#optional' => TRUE,
      ];
    }
  }

  /**
   * Wraps the 'new_revision' checkbox in the 'Revision information' group.
   *
   * Call from buildForm(), after the checkbox has been added.
   */
  protected function groupAdvancedSidebarRevisionCheckbox(array &$form): void {
    if (!isset($form['new_revision'])) {
      return;
    }
    $this->ensureAdvancedSidebarRevisionGroup($form);
    $form['new_revision']['#group'] = 'revision_information';
  }

  /**
   * Wraps the 'revision_log' field in the 'Revision information' group.
   *
   * EditorialContentEntityBase adds this field via RevisionLogEntityTrait
   * regardless of show_revision_ui, so it appears on both add and edit
   * forms. Call after parent::form() has built the field widget.
   */
  protected function groupAdvancedSidebarRevisionLog(array &$form): void {
    if (!isset($form['revision_log'])) {
      return;
    }
    $this->ensureAdvancedSidebarRevisionGroup($form);
    $form['revision_log']['#group'] = 'revision_information';
  }

  /**
   * Removes the 'status' field's help text.
   *
   * Gin hoists the 'status' checkbox into the sticky header's "Published"
   * toggle on content forms, where the field's full description reads as
   * awkward clutter next to a plain toggle. Node's own 'status' base field
   * has no description for the same reason. Call after parent::form() has
   * built the field widget.
   */
  protected function suppressAdvancedSidebarStatusDescription(array &$form): void {
    unset($form['status']['widget']['value']['#description']);
  }

}
