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
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL) {
    if ($node) {
      $form['node'] = [
        '#type' => 'hidden',
        '#value' => $node->id(),
      ];
    }

    $form['collection'] = [
      '#type' => 'entity_autocomplete_tagify',
      '#title' => $this->t('Select Collection'),
      '#target_type' => 'node',
      '#required' => TRUE,
      '#cardinality' => -1,
      // 0 = show all eligible collections on click, not just as-you-type
      // matches.
      '#suggestions_dropdown' => 0,
      '#identifier' => 'mukurtu-add-item-to-collection',
      // Tagify copies classes from the original input onto its generated
      // wrapper; the "show all on click" behavior looks up that wrapper by
      // this exact class, so it must be set here too.
      '#attributes' => ['class' => ['mukurtu-add-item-to-collection']],
      '#selection_handler' => 'mukurtu_eligible_container',
      '#selection_settings' => [
        'target_bundles' => ['collection'],
        'mukurtu_containing_field' => MUKURTU_COLLECTION_FIELD_NAME_ITEMS,
        'mukurtu_current_item' => $node?->id(),
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add to Collection'),
    ];

    // This form is only ever shown inside a Gin-styled modal dialog
    // regardless of the active front-end theme, so force tagify's Gin
    // styling rather than relying on theme auto-detection.
    $form['#attached']['library'][] = 'tagify/gin';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // The tagify widget's value is a JSON-encoded array of selected tags.
    $selected = json_decode($form_state->getValue('collection') ?? '', TRUE) ?: [];
    $nodeId = $form_state->getValue('node');

    /**
     * @var \Drupal\node\NodeInterface $node
     */
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nodeId);

    if (!$node) {
      return;
    }

    $form_state->setRedirect('entity.node.canonical', ['node' => $node->id()]);

    foreach ($selected as $item) {
      $collectionId = $item['entity_id'] ?? NULL;

      /**
       * @var \Drupal\mukurtu_collection\Entity\Collection $collection
       */
      $collection = $collectionId ? \Drupal::entityTypeManager()->getStorage('node')->load($collectionId) : NULL;

      if (!$collection || $collection->bundle() != 'collection' || !$collection->access('update')) {
        continue;
      }

      $collection->add($node);

      // Add revision message if supported.
      if ($collection instanceof RevisionableInterface) {
        $collection->setRevisionLogMessage($this->t("Added @node to collection.", ['@node' => $node->getTitle()]));
      }

      try {
        $collection->save();
        $this->messenger()->addStatus($this->t("Added %node to %collection", ['%node' => $node->getTitle(), '%collection' => $collection->getTitle()]));
      } catch (Exception $e) {
        $this->messenger()->addError($this->t("Failed to add the item to the collection"));
      }
    }
  }

}
