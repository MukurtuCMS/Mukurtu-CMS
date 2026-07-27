<?php

namespace Drupal\mukurtu_collection\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\og\Og;
use Drupal\user\UserInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Exception;

class PersonalCollectionAddItemForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_collection_add_item_to_personal_collection_form';
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
      '#title' => $this->t('Select Personal Collection'),
      '#target_type' => 'personal_collection',
      '#required' => TRUE,
      '#cardinality' => -1,
      // 0 = show all eligible personal collections on click, not just
      // as-you-type matches.
      '#suggestions_dropdown' => 0,
      '#identifier' => 'mukurtu-add-item-to-personal-collection',
      // Tagify copies classes from the original input onto its generated
      // wrapper; the "show all on click" behavior looks up that wrapper by
      // this exact class, so it must be set here too.
      '#attributes' => ['class' => ['mukurtu-add-item-to-personal-collection']],
      '#selection_handler' => 'mukurtu_eligible_container',
      '#selection_settings' => [
        'mukurtu_containing_field' => 'field_items_in_collection',
        'mukurtu_current_item' => $node?->id(),
        'mukurtu_owned_by_current_user' => TRUE,
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add to Personal Collection'),
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
       * @var \Drupal\mukurtu_collection\Entity\PersonalCollection $collection
       */
      $collection = $collectionId ? \Drupal::entityTypeManager()->getStorage('personal_collection')->load($collectionId) : NULL;

      if (!$collection || !$collection->access('update')) {
        continue;
      }

      $collection->add($node);

      // Add revision message if supported.
      if ($collection instanceof RevisionableInterface) {
        $collection->setRevisionLogMessage($this->t("Added @node to personal collection.", ['@node' => $node->getTitle()]));
      }

      try {
        $collection->save();
        $this->messenger()->addStatus($this->t("Added %node to %collection", ['%node' => $node->getTitle(), '%collection' => $collection->getTitle()]));
      } catch (Exception $e) {
        $this->messenger()->addError($this->t("Failed to add the item to the personal collection"));
      }
    }
  }

}
