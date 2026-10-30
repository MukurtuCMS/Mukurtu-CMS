<?php

namespace Drupal\mukurtu_dictionary\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_core\Plugin\Action\ContainerMembershipActionBase;
use Drupal\mukurtu_dictionary\Entity\WordList;
use Drupal\node\NodeInterface;

/**
 * VBO for adding dictionary words to word lists.
 */
#[Action(
  id: 'mukurtu_add_to_word_list_action',
  label: new TranslatableMarkup('Add to Word List'),
  type: 'node',
)]
class MukurtuAddToWordListAction extends ContainerMembershipActionBase {

  /**
   * {@inheritdoc}
   */
  protected function containerBundle(): string {
    return 'word_list';
  }

  /**
   * {@inheritdoc}
   */
  protected function itemsField(): string {
    return WordList::WORDS_FIELD;
  }

  /**
   * {@inheritdoc}
   */
  protected function isEligibleItem(NodeInterface $node): bool {
    return $node->bundle() === 'dictionary_word';
  }

  /**
   * {@inheritdoc}
   */
  protected function ineligibleReason(): TranslatableMarkup {
    return $this->t('Only dictionary words can be added to a word list.');
  }

  /**
   * {@inheritdoc}
   */
  protected function selectLabel(): TranslatableMarkup {
    return $this->t('Select Word List');
  }

  /**
   * {@inheritdoc}
   */
  protected function noContainersMessage(): TranslatableMarkup {
    return $this->t('There are no word lists you can add words to.');
  }

  /**
   * {@inheritdoc}
   */
  protected function doneMessage(string $label, string $url): TranslatableMarkup {
    return $this->t('Added to <a href=":url">@list</a>', ['@list' => $label, ':url' => $url]);
  }

  /**
   * {@inheritdoc}
   */
  protected function skippedMessage(string $label, string $url): TranslatableMarkup {
    return $this->t('Already in <a href=":url">@list</a>', ['@list' => $label, ':url' => $url]);
  }

  /**
   * {@inheritdoc}
   */
  protected function failedMessage(string $label): TranslatableMarkup {
    return $this->t("Couldn't add to @list", ['@list' => $label]);
  }

}
