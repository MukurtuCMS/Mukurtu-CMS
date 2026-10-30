<?php

namespace Drupal\mukurtu_dictionary\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;

/**
 * VBO for removing dictionary words from word lists.
 */
#[Action(
  id: 'mukurtu_remove_from_word_list_action',
  label: new TranslatableMarkup('Remove from Word List'),
  type: 'node',
)]
class MukurtuRemoveFromWordListAction extends MukurtuAddToWordListAction {

  /**
   * {@inheritdoc}
   */
  protected function removes(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * Anything already in a word list can be taken out of it.
   */
  protected function isEligibleItem(NodeInterface $node): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function noContainersMessage(): TranslatableMarkup {
    return $this->t('There are no word lists you can remove words from.');
  }

  /**
   * {@inheritdoc}
   */
  protected function doneMessage(string $label, string $url): TranslatableMarkup {
    return $this->t('Removed from <a href=":url">@list</a>', ['@list' => $label, ':url' => $url]);
  }

  /**
   * {@inheritdoc}
   */
  protected function skippedMessage(string $label, string $url): TranslatableMarkup {
    return $this->t('Not in <a href=":url">@list</a>', ['@list' => $label, ':url' => $url]);
  }

  /**
   * {@inheritdoc}
   */
  protected function failedMessage(string $label): TranslatableMarkup {
    return $this->t("Couldn't remove from @list", ['@list' => $label]);
  }

}
