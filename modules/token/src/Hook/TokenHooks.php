<?php

namespace Drupal\token\Hook;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\token\TreeBuilderInterface;

/**
 * Hook implementations for token.
 */
final class TokenHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly Token $token,
    protected readonly TreeBuilderInterface $treeBuilder,
    protected readonly RendererInterface $renderer,
  ) {
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    if ($route_name == 'help.page.token') {
      $token_tree = $this->treeBuilder->buildAllRenderable([
        'click_insert' => FALSE,
        'show_restricted' => TRUE,
        'show_nested' => FALSE,
      ]);
      $output = '<h3>' . $this->t('About') . '</h3>';
      $output .= '<p>' . $this->t('The <a href=":project">Token</a> module provides a user interface for the site token system. It also adds some additional tokens that are used extensively during site development. Tokens are specially formatted chunks of text that serve as placeholders for a dynamically generated value. For more information, covering both the token system and the additional tools provided by the Token module, see the <a href=":online">online documentation</a>.', [
        ':online' => 'https://www.drupal.org/documentation/modules/token',
        ':project' => 'https://www.drupal.org/project/token',
      ]) . '</p>';
      $output .= '<h3>' . $this->t('Uses') . '</h3>';
      $output .= '<p>' . $this->t('Your website uses a shared token system for exposing and using placeholder tokens and their appropriate replacement values. This allows for any module to provide placeholder tokens for strings without having to reinvent the wheel. It also ensures consistency in the syntax used for tokens, making the system as a whole easier for end users to use.') . '</p>';
      $output .= '<dl>';
      $output .= '<dt>' . $this->t('The list of the currently available tokens on this site are shown below.') . '</dt>';
      $output .= '<dd>' . $this->renderer->render($token_tree) . '</dd>';
      $output .= '</dl>';
      return $output;
    }
  }

  /**
   * Implements hook_block_view_alter().
   */
  #[Hook('block_view_alter')]
  public function blockViewAlter(&$build, BlockPluginInterface $block) {
    if (isset($build['#configuration'])) {
      $label = $build['#configuration']['label'];
      if ($label != '<none>') {
        // The label is automatically escaped, avoid escaping it twice.
        // @todo https://www.drupal.org/node/2580723 will add a method or option
        //   to the token API to do this, use that when available.
        $bubbleable_metadata = BubbleableMetadata::createFromRenderArray($build);
        $build['#configuration']['label'] = PlainTextOutput::renderFromHtml($this->token->replace($label, [], [], $bubbleable_metadata));
        $bubbleable_metadata->applyTo($build);
      }
    }
  }

}
