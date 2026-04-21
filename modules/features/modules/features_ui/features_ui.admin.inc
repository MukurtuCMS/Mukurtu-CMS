<?php

/**
 * @file
 * Administration functions for features.module.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for package assignment configuration form.
 *
 * @param array $variables
 *   An associative array containing:
 *   - form: A render element representing the form.
 */
function template_preprocess_features_assignment_configure_form(array &$variables) {
  $form =& $variables['form'];

  $header = [
    t('Assignment method'),
    t('Description'),
    t('Enabled'),
    t('Weight'),
  ];

  // If there is at least one operation enabled, show the operation column.
  if ($form['#show_operations']) {
    $header[] = t('Operations');
  }

  $table = [
    '#type' => 'table',
    '#weight' => 5,
    '#header' => $header,
    '#attributes' => ['id' => 'features-assignment-methods'],
    '#tabledrag' => [
      [
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'assignment-method-weight',
      ],
    ],
  ];

  foreach ($form['title'] as $id => $element) {
    // Do not take form control structures.
    if (is_array($element) && Element::child($id)) {
      $table[$id]['#attributes']['class'][] = 'draggable';
      $table[$id]['#weight'] = $element['#weight'];

      $table[$id]['title'] = [
        '#prefix' => '<strong>',
        $form['title'][$id],
        '#suffix' => '</strong>',
      ];
      $table[$id]['description'] = $form['description'][$id];
      $table[$id]['enabled'] = $form['enabled'][$id];
      $table[$id]['weight'] = $form['weight'][$id];
      if ($form['#show_operations']) {
        $table[$id]['operation'] = $form['operation'][$id];
      }
      // Unset to prevent rendering along with children.
      unset($form['title'][$id]);
      unset($form['description'][$id]);
      unset($form['enabled'][$id]);
      unset($form['weight'][$id]);
      unset($form['operation'][$id]);
    }
  }

  // For some reason, the #weight is not being handled by drupal_render.
  // So we remove the actions and then put them back into the form after the
  // table.
  $actions = $form['actions'];
  unset($form['actions']);
  $form['table'] = $table;
  $form['actions'] = $actions;
}
