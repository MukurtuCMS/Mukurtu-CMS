<?php

namespace Drupal\mukurtu_collection\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\og\Og;
use Drupal\user\UserInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Exception;

class MukurtuAddItemToCollectionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_collection_add_item_to_collection_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL, Array $collections = []) {
    if ($node) {
      $form['node'] = [
        '#type' => 'hidden',
        '#value' => $node->id(),
      ];
    }

    if (!empty($collections)) {
      foreach ($collections as $collection) {
        $options[$collection->id()] = $collection->getTitle();
      }

      $form['collection'] = [
        '#type' => 'select',
        '#title' => $this->t('Select Collection'),
        '#options' => $options,
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add to Collection'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $collectionId = $form_state->getValue('collection');
    $nodeId = $form_state->getValue('node');

    $collection = \Drupal::entityTypeManager()->getStorage('node')->load($collectionId);
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nodeId);

    if ($node && $collection && $collection->bundle() == 'collection' && $collection->access('update')) {
      $items = $collection->get(MUKURTU_COLLECTION_FIELD_NAME_ITEMS)->getValue();
      $items[] = ['target_id' => $nodeId];
      $collection->set(MUKURTU_COLLECTION_FIELD_NAME_ITEMS, $items);

      // Add revision message if supported.
      if ($collection instanceof RevisionableInterface) {
        $collection->setRevisionLogMessage($this->t("Added @node to collection.", ['@node' => $node->getTitle()]));
      }

      $form_state->setRedirect('entity.node.canonical', ['node' => $node->id()]);
      try {
        $collection->save();
        $this->messenger()->addStatus($this->t("Added %node to %collection", ['%node' => $node->getTitle(), '%collection' => $collection->getTitle()]));
      } catch (Exception $e) {
        $this->messenger()->addError($this->t("Failed to add the item to the collection"));
      }
    }

  }

}
