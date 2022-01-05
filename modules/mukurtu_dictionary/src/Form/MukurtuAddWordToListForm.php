<?php

namespace Drupal\mukurtu_dictionary\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
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
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL, Array $lists = []) {
    if ($node) {
      $form['node'] = [
        '#type' => 'hidden',
        '#value' => $node->id(),
      ];
    }

    if (!empty($lists)) {
      foreach ($lists as $list) {
        $options[$list->id()] = $list->getTitle();
      }

      $form['word_list'] = [
        '#type' => 'select',
        '#title' => $this->t('Select Word List'),
        '#options' => $options,
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add to Word List'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $listId = $form_state->getValue('word_list');
    $nodeId = $form_state->getValue('node');

    /**
     * @var \Drupal\mukurtu_dictionary\Entity\WordListInterface
     */
    $list = \Drupal::entityTypeManager()->getStorage('node')->load($listId);

    /**
     * @var \Drupal\mukurtu_dictionary\Entity\DictionaryWord
     */
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nodeId);

    if ($node && $list && $list->bundle() == 'word_list' && $list->access('update')) {
      // Add the word to the list.
      $list->add($node);

      // Add revision message if supported.
      if ($list instanceof RevisionableInterface) {
        $list->setRevisionLogMessage($this->t("Added @node to the word list.", ['@node' => $node->getTitle()]));
      }

      $form_state->setRedirect('entity.node.canonical', ['node' => $node->id()]);
      try {
        $list->save();
        $this->messenger()->addStatus($this->t("Added %node to %list", ['%node' => $node->getTitle(), '%list' => $list->getTitle()]));
      } catch (Exception $e) {
        $this->messenger()->addError($this->t("Failed to add the word to the word list"));
      }
    }

  }

}
