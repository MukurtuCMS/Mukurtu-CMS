<?php

namespace Drupal\token_module_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Utility\Token;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for token_module_test.
 */
final class TokenModuleTestHooks {

  public function __construct(
    protected readonly Token $token,
    protected readonly StateInterface $state,
  ) {

  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments() {
    if ($debug = $this->state->get('token_page_tokens', [])) {
      $debug += [
        'tokens' => [],
        'data' => [],
        'options' => [],
      ];
      foreach (array_keys($debug['tokens']) as $token) {
        $debug['values'][$token] = $this->token->replace($token, $debug['data'], $debug['options']);
      }
      $this->state->set('token_page_tokens', $debug);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_presave for Node entities.
   */
  #[Hook('node_presave')]
  public function nodePresave(NodeInterface $node): void {
    // Transform tokens in the body.
    // @see \Drupal\token\Tests\TokenMenuTest::testMenuTokens()
    if ($node->hasField('body') && $node->get('body')->value) {
      $node->body->value = $this->token
        ->replace($node->body->value, ['node' => $node]);
    }
  }

}
