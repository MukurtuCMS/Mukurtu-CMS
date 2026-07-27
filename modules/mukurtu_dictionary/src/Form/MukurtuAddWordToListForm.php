<?php

namespace Drupal\mukurtu_dictionary\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_dictionary\Entity\WordList;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Exception;

class MukurtuAddWordToListForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_dictionary_add_word_to_list_form';
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

    $form['word_list'] = [
      '#type' => 'entity_autocomplete_tagify',
      '#title' => $this->t('Select Word List'),
      '#target_type' => 'node',
      '#required' => TRUE,
      '#cardinality' => -1,
      // 0 = show all eligible word lists on click, not just as-you-type
      // matches.
      '#suggestions_dropdown' => 0,
      '#identifier' => 'mukurtu-add-word-to-list',
      // Tagify copies classes from the original input onto its generated
      // wrapper; the "show all on click" behavior looks up that wrapper by
      // this exact class, so it must be set here too.
      '#attributes' => ['class' => ['mukurtu-add-word-to-list']],
      '#selection_handler' => 'mukurtu_eligible_container',
      '#selection_settings' => [
        'target_bundles' => ['word_list'],
        'mukurtu_containing_field' => WordList::WORDS_FIELD,
        'mukurtu_current_item' => $node?->id(),
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add to Word List'),
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
    $selected = json_decode($form_state->getValue('word_list') ?? '', TRUE) ?: [];
    $nodeId = $form_state->getValue('node');

    /**
     * @var \Drupal\mukurtu_dictionary\Entity\DictionaryWord
     */
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nodeId);

    if (!$node) {
      return;
    }

    $form_state->setRedirect('entity.node.canonical', ['node' => $node->id()]);

    foreach ($selected as $item) {
      $listId = $item['entity_id'] ?? NULL;

      /**
       * @var \Drupal\mukurtu_dictionary\Entity\WordListInterface
       */
      $list = $listId ? \Drupal::entityTypeManager()->getStorage('node')->load($listId) : NULL;

      if (!$list || $list->bundle() != 'word_list' || !$list->access('update')) {
        continue;
      }

      // Add the word to the list.
      $list->add($node);

      // Add revision message if supported.
      if ($list instanceof RevisionableInterface) {
        $list->setRevisionLogMessage($this->t("Added @node to the word list.", ['@node' => $node->getTitle()]));
      }

      try {
        $list->save();
        $this->messenger()->addStatus($this->t("Added %node to %list", ['%node' => $node->getTitle(), '%list' => $list->getTitle()]));
      } catch (Exception $e) {
        $this->messenger()->addError($this->t("Failed to add the word to the word list"));
      }
    }
  }

}
