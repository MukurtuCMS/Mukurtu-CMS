<?php

namespace Drupal\layout_builder_restrictions\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenOffCanvasDialogCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\layout_builder\Form\MoveBlockForm as MoveBlockFormCore;

/**
 * Provides a form for moving a block.
 *
 * @phpstan-ignore-line
 * @internal
 *   Form classes are internal.
 */
class MoveBlockForm extends MoveBlockFormCore {

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $to_region = $this->getSelectedRegion($form_state);
    $to_delta = $this->getSelectedDelta($form_state);
    $from_delta = $this->delta;
    // $original_section = $this->sectionStorage->getSection($from_delta);
    // $component = $original_section->getComponent($this->uuid);
    // Retrieve defined Layout Builder Restrictions plugins.
    // @phpstan-ignore-line
    $layout_builder_restrictions_manager = \Drupal::service('plugin.manager.layout_builder_restriction');
    $restriction_definitions = $layout_builder_restrictions_manager->getDefinitions();
    foreach ($restriction_definitions as $restriction_definition) {
      // @todo respect ordering of plugins (see #3045266)
      $plugin_instance = $layout_builder_restrictions_manager->createInstancE($restriction_definition['id']);
      $block_status = $plugin_instance->blockAllowedinContext($this->sectionStorage, $from_delta, $to_delta, $to_region, $this->uuid, NULL);
      if ($block_status !== TRUE) {
        $form_state->setErrorByName('region', $block_status);
      }
    }
  }

  /**
   * Submit form dialog #ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response that display validation error messages or represents a
   *   successful submission.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    if ($form_state->hasAnyErrors()) {
      $build_info = $form_state->getBuildInfo();

      $response = new AjaxResponse();
      $content = "";
      foreach ($form_state->getErrors() as $error) {
        $content .= '<p>' . $error . '</p>';
      }

      $build['error'] = [
        '#markup' => $content,
      ];
      $build['back_button'] = [
        '#type' => 'link',
        '#url' => Url::fromRoute('layout_builder.move_block_form',
          [
            'section_storage_type' => $build_info['args'][0]->getPluginId(),
            'section_storage' => $build_info['args'][0]->getStorageId(),
            'delta' => $build_info['args'][1],
            'region' => $build_info['args'][2],
            'uuid' => $build_info['args'][3],
          ]
        ),
        '#title' => $this->t('Back'),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'dialog',
          'data-dialog-renderer' => 'off_canvas',
        ],
      ];

      $response->addCommand(new OpenOffCanvasDialogCommand("Content cannot be placed.", $build));
    }
    else {
      $response = $this->successfulAjaxSubmit($form, $form_state);
    }
    return $response;
  }

}
