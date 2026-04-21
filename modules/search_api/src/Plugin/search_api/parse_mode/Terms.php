<?php

namespace Drupal\search_api\Plugin\search_api\parse_mode;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiParseMode;
use Drupal\search_api\ParseMode\ParseModePluginBase;

/**
 * Represents a parse mode that parses the input into multiple words.
 */
#[SearchApiParseMode(
  id: 'terms',
  label: new TranslatableMarkup('Multiple words'),
  description: new TranslatableMarkup('The query is interpreted as multiple keywords separated by spaces. Keywords containing spaces may be "quoted". Quoted keywords must still be separated by spaces. Keywords can be negated by prepending a minus sign (-) to them.'),
)]
class Terms extends ParseModePluginBase {

  /**
   * {@inheritdoc}
   */
  public function parseInput($keys) {
    $ret = [
      '#conjunction' => $this->getConjunction(),
    ];

    if (!Unicode::validateUtf8($keys)) {
      return $ret;
    }
    // Split the keys into tokens. Any whitespace is considered as a delimiter
    // for tokens. This covers ASCII white spaces as well as multi-byte "spaces"
    // which for example are common in Japanese.
    $tokens = preg_split('/\s+/u', $keys) ?: [];
    $quoted = FALSE;
    $negated = FALSE;
    $phrase_contents = [];

    foreach ($tokens as $token) {
      // Ignore empty tokens. (Also helps keep the following code simpler.)
      if ($token === '') {
        continue;
      }

      // Check for negation.
      if ($token[0] === '-' && !$quoted) {
        $token = ltrim($token, '-');
        // If token is empty after trimming, ignore it.
        if ($token === '') {
          continue;
        }
        $negated = TRUE;
      }

      // Depending on whether we are currently in a quoted phrase, or maybe just
      // starting one, act accordingly.
      if ($quoted) {
        if (str_ends_with($token, '"')) {
          $token = substr($token, 0, -1);
          $phrase_contents[] = trim($token);
          $phrase_contents = array_filter($phrase_contents, 'strlen');
          $phrase_contents = implode(' ', $phrase_contents);
          if ($phrase_contents !== '') {
            $ret[] = $phrase_contents;
          }
          $quoted = FALSE;
        }
        else {
          $phrase_contents[] = trim($token);
          continue;
        }
      }
      elseif ($token[0] === '"') {
        $len = strlen($token);
        if ($len > 1 && $token[$len - 1] === '"') {
          $ret[] = substr($token, 1, -1);
        }
        else {
          $phrase_contents = [trim(substr($token, 1))];
          $quoted = TRUE;
          continue;
        }
      }
      else {
        $ret[] = $token;
      }

      // If negation was set, change the last added keyword to be negated.
      if ($negated) {
        $i = count($ret) - 2;
        $ret[$i] = [
          '#negation' => TRUE,
          '#conjunction' => 'AND',
          $ret[$i],
        ];
        $negated = FALSE;
      }
    }

    // Take care of any quoted phrase missing its closing quotation mark.
    if ($quoted) {
      $phrase_contents = implode(' ', array_filter($phrase_contents, 'strlen'));
      if ($phrase_contents !== '') {
        $ret[] = $phrase_contents;
      }
    }

    return $ret;
  }

}
