<?php

namespace Drupal\entity_browser\Controllers;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\entity_browser\Ajax\ValueUpdatedCommand;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for entity browser routes.
 */
class EntityBrowserController extends ControllerBase {

  /**
   * Return an Ajax dialog command for editing a referenced entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity being edited.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The currently processing request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An Ajax response with a command for opening or closing the dialog
   *   containing the edit form.
   */
  public function entityBrowserEdit(EntityInterface $entity, Request $request) {

    $trigger_name = $request->request->get('_triggering_element_name');
    $edit_button = (strpos($trigger_name, 'edit_button') !== FALSE);

    if ($edit_button) {
      // Remove posted values from original form to prevent
      // data leakage into this form when the form is of the same bundle.
      $original_request = $request->request;
      $request->request = new InputBag();
    }

    // Use edit form class if it exists, otherwise use default form class.
    $entity_type = $entity->getEntityType();
    if ($entity_type->getFormClass('edit')) {
      $operation = 'edit';
    }
    elseif ($entity_type->getFormClass('default')) {
      $operation = 'default';
    }

    if (!empty($operation)) {
      // Build the entity edit form.
      $form_object = $this->entityTypeManager()->getFormObject($entity->getEntityTypeId(), $operation);
      $form_object->setEntity($entity);
      $form_state = (new FormState())
        ->setFormObject($form_object)
        ->disableRedirect();
      // Building the form also submits.
      $form = $this->formBuilder()->buildForm($form_object, $form_state);
    }

    // Restore original request now that the edit form is built.
    // This fixes a bug where closing modal and re-opening it would
    // cause two modals to open.
    if ($edit_button) {
      $request->request = $original_request;
    }

    // Return a response, depending on whether it's successfully submitted.
    if ($operation && $form_state && !$form_state->isExecuted()) {
      // Return the form as a modal dialog.
      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
      $title = $this->t('Edit @entity', ['@entity' => $entity->label()]);
      $response = (new AjaxResponse())->addCommand(new OpenDialogCommand('#' . $entity->getEntityTypeId() . '-' . $entity->id() . '-edit-dialog', $title, $form, ['modal' => TRUE, 'width' => '92%', 'dialogClass' => 'entity-browser-modal']));
      return $response;
    }
    else {
      // Return command for closing the modal.
      $response = (new AjaxResponse())->addCommand(new CloseDialogCommand('#' . $entity->getEntityTypeId() . '-' . $entity->id() . '-edit-dialog'));
      // Also refresh the widget if "details_id" is provided.
      $details_id = $request->query->get('details_id');
      if (!empty($details_id)) {
        $response->addCommand(new ValueUpdatedCommand($details_id));

        if (empty($operation)) {
          $response->addCommand(new AlertCommand($this->t("An edit form couldn't be found.")));
        }
      }

      return $response;
    }
  }

}
