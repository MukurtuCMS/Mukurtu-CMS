<?php

namespace Drupal\mukurtu_multipage_items\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the multipage item entity edit forms.
 */
class MultipageItemForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New multipage item %label has been created.', $message_arguments));
      $this->logger('mukurtu_multipage_items')->notice('Created new multipage item %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The multipage item %label has been updated.', $message_arguments));
      $this->logger('mukurtu_multipage_items')->notice('Updated new multipage item %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.multipage_item.canonical', ['multipage_item' => $entity->id()]);
  }

}
