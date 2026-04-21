<?php

namespace Drupal\twig_tweak_test\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a foo block.
 *
 * @Block(
 *   id = "twig_tweak_test_foo",
 *   admin_label = @Translation("Foo"),
 *   category = @Translation("Twig Tweak")
 * )
 */
final class FooBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['content' => 'Foo'];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResult {
    $result = AccessResult::allowedIf($account->getAccountName() == 'User 1');
    $result->addCacheTags(['tag_from_' . __FUNCTION__]);
    $result->setCacheMaxAge(35);
    $result->cachePerUser();
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#markup' => $this->getConfiguration()['content'],
      '#attributes' => [
        'id' => 'foo',
      ],
      '#cache' => [
        'contexts' => ['url'],
        'tags' => ['tag_from_' . __FUNCTION__],
      ],
    ];
  }

}
