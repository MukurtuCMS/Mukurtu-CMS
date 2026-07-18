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
   * Wraps the 'new_revision' checkbox in a 'Revision information' group.
   *
   * Call from buildForm(), after the checkbox has been added.
   */
  protected function groupAdvancedSidebarRevisionCheckbox(array &$form): void {
    if (!isset($form['new_revision'])) {
      return;
    }
    $form['revision_information'] = [
      '#type' => 'details',
      '#title' => $this->t('Revision information'),
      '#group' => 'advanced',
      '#weight' => 20,
      '#optional' => TRUE,
    ];
    $form['new_revision']['#group'] = 'revision_information';
  }

}
