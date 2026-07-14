<?php

namespace Drupal\mukurtu_dictionary\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_dictionary\Entity\WordList;

class MukurtuAddWordToListController extends ControllerBase {
  /**
   * Check access for editing the word list via the edit_index route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\node\NodeInterface $node
   *   The item.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, NodeInterface $node) {
    if ($node->bundle() == 'dictionary_word') {
      if ($this->userCanEditExistingWordLists($node) || $this->entityTypeManager()->getAccessControlHandler('node')->createAccess('word_list', $account)) {
        return AccessResult::allowed();
      }
    }

    return AccessResult::forbidden();
  }

  /**
   *  Check if the user can edit any lists that don't already
   *  contain the word.
   */
  protected function userCanEditExistingWordLists(NodeInterface $node) {
    $validLists = $this->getValidWordLists($node);
    if (!empty($validLists)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Return a list of word lists the word can be added to.
   */
  protected function getValidWordLists(NodeInterface $node) {
    // For the life of me I cannot get <> or NOT IN to work
    // correctly so we are finding all word lists as well
    // as all word lists that contain the item and doing
    // a diff to get the set of word lists that don't
    // contain the item.
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'word_list')
      ->condition(WordList::WORDS_FIELD, $node->id(), '=')
      ->accessCheck(TRUE)
      ->sort('changed', 'DESC');
    $listsThatContainWord = $query->execute();

    $query = \Drupal::entityQuery('node')
      ->condition('type', 'word_list')
      ->accessCheck(TRUE)
      ->sort('changed', 'DESC');
    $allLists = $query->execute();

    $lists = [];
    $listsIds = array_diff($allLists, $listsThatContainWord);
    if (!empty($listsIds)) {
      // This might be too slow for an access check.
      // Might need to push this part to the form.
      $lists = $this->entityTypeManager()->getStorage('node')->loadMultiple($listsIds);
      foreach ($lists as $delta => $list) {
        // Remove word lists the user cannot update.
        if (!$list->access('update')) {
          unset($lists[$delta]);
          continue;
        }
      }
    }

    return $lists;
  }

  /**
   * Add word to word list page.
   */
  public function content(NodeInterface $node) {
    $build = [];

    // Existing word list. Only show the picker if there's actually a word
    // list eligible to add to - it's a required field, so with no eligible
    // word lists it would just be a dead end.
    $hasExistingLists = $this->userCanEditExistingWordLists($node);
    if ($hasExistingLists) {
      $build['existing'] = \Drupal::formBuilder()->getForm('Drupal\mukurtu_dictionary\Form\MukurtuAddWordToListForm', $node);
    }

    // New word list.
    if ($this->entityTypeManager()->getAccessControlHandler('node')->createAccess('word_list')) {
      $newList = Node::create(['type' => 'word_list']);
      $newList->add($node);

      $form = $this->entityTypeManager()->getFormObject('node', 'default')->setEntity($newList);

      $build['new_list'] = [
        '#type' => 'details',
        '#title' => $this->t('Create a new word list'),
        // Open by default when there's no existing word list to pick from,
        // since it's then the only actionable option in the dialog.
        '#open' => !$hasExistingLists,
      ];
      $build['new_list']['form'] = $this->formBuilder()->getForm($form);
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(NodeInterface $node) {
    return $this->t("Add %node to Word List", ['%node' => $node->getTitle()]);
  }

}
