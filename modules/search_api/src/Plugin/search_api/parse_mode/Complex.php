<?php

namespace Drupal\search_api\Plugin\search_api\parse_mode;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiParseMode;
use Drupal\search_api\ParseMode\ParseModePluginBase;

/**
 * Represents a parse mode that handles complex search queries.
 */
#[SearchApiParseMode(
  id: 'complex',
  label: new TranslatableMarkup('Keywords with operators'),
  description: new TranslatableMarkup('Supports complex queries with AND, OR, NOT operators, parenthetical grouping and quoted phrases.'),
)]
class Complex extends ParseModePluginBase {

  /**
   * Current tokens being parsed.
   *
   * @var list<array{'type': string, 'value': string}>
   */
  protected array $tokens = [];

  /**
   * Current position in the tokens array.
   */
  protected int $position = 0;

  /**
   * {@inheritdoc}
   */
  public function parseInput($keys): array|string|null {
    if (!Unicode::validateUtf8($keys)) {
      return NULL;
    }

    // Tokenize the input while preserving operators and parentheses.
    $tokens = $this->tokenizeInput($keys);

    if (!$tokens) {
      return NULL;
    }

    // Parse the tokenized input into a structured query.
    $parsed = $this->parseTokens($tokens);

    // If $parsed is a string, convert to array format with conjunction.
    if (is_string($parsed)) {
      return $this->convertStringToConjunctionArray($parsed);
    }
    return isset($parsed[0]) ? $parsed : NULL;
  }

  /**
   * Tokenizes the input string into an array of tokens.
   *
   * @param string $input
   *   The input string to tokenize.
   *
   * @return array
   *   Array of tokens including terms, operators, and parentheses.
   */
  protected function tokenizeInput(string $input): array {
    $tokens = [];
    $length = mb_strlen($input);
    $i = 0;

    while ($i < $length) {
      $char = mb_substr($input, $i, 1);

      // Skip whitespace using a Unicode-aware pattern.
      if (preg_match('/\s/u', $char)) {
        $i++;
        continue;
      }

      // Handle quoted phrases.
      if ($char === '"') {
        $quote_end = $this->findClosingQuote($input, $i);
        if ($quote_end !== FALSE) {
          $phrase = mb_substr($input, $i + 1, $quote_end - $i - 1);
          $tokens[] = ['type' => 'phrase', 'value' => $phrase];
          $i = $quote_end + 1;
        }
        else {
          // Unclosed quote - treat as regular term.
          $tokens[] = ['type' => 'term', 'value' => '"'];
          $i++;
        }
        continue;
      }

      // Handle parentheses.
      if ($char === '(') {
        $tokens[] = ['type' => 'open_paren', 'value' => '('];
        $i++;
        continue;
      }

      if ($char === ')') {
        $tokens[] = ['type' => 'close_paren', 'value' => ')'];
        $i++;
        continue;
      }

      // Handle regular terms and operators.
      $term_end = $this->findTermEnd($input, $i);
      $term = mb_substr($input, $i, $term_end - $i);

      // Check if it's an operator - ONLY match uppercase operators.
      if (in_array($term, ['AND', 'OR', 'NOT'], TRUE)) {
        $tokens[] = ['type' => 'operator', 'value' => $term];
      }
      else {
        $tokens[] = ['type' => 'term', 'value' => $term];
      }

      $i = $term_end;
    }

    return $tokens;
  }

  /**
   * Finds the closing quote for a quoted phrase.
   *
   * @param string $input
   *   The input string.
   * @param int $start
   *   The position of the opening quote.
   *
   * @return int|false
   *   The position of the closing quote, or FALSE if not found.
   */
  protected function findClosingQuote(string $input, int $start): bool|int {
    $length = mb_strlen($input);
    for ($i = $start + 1; $i < $length; $i++) {
      $char = mb_substr($input, $i, 1);
      if ($char === '"') {
        return $i;
      }
    }
    return FALSE;
  }

  /**
   * Finds the end of a term.
   *
   * @param string $input
   *   The input string.
   * @param int $start
   *   The starting position.
   *
   * @return int
   *   The end position of the term.
   */
  protected function findTermEnd(string $input, int $start): int {
    $length = mb_strlen($input);
    for ($i = $start; $i < $length; $i++) {
      $char = mb_substr($input, $i, 1);
      if (preg_match('/[\s()"]/u', $char)) {
        return $i;
      }
    }
    return $length;
  }

  /**
   * Parses an array of tokens into a structured query.
   *
   * @param array $tokens
   *   Array of tokens to parse.
   *
   * @return array|string
   *   Structured query array.
   */
  protected function parseTokens(array $tokens): array|string {
    $this->tokens = $tokens;
    $this->position = 0;

    return $this->parseExpression();
  }

  /**
   * Parses a boolean expression.
   *
   * @return array|string
   *   Parsed expression array.
   */
  protected function parseExpression(): array|string {
    $terms = [];
    $terms[] = $this->parseTerm();

    while ($this->position < count($this->tokens)) {
      $token = $this->tokens[$this->position];

      if ($token['type'] === 'operator' && in_array($token['value'], ['AND', 'OR'])) {
        $operator = $token['value'];
        $this->position++;
        $right = $this->parseTerm();

        // If we have multiple terms collected without explicit operators,
        // combine them first using the default conjunction.
        if (count($terms) > 1) {
          $left = [
            '#conjunction' => $this->getConjunction(),
          ];
          foreach ($terms as $index => $term) {
            $left[] = $term;
          }
          $terms = [$left];
        }

        // Create a new expression with the explicit operator.
        $combined = [
          '#conjunction' => $operator,
          $terms[0],
          $right,
        ];
        $terms = [$combined];
      }
      elseif ($token['type'] === 'close_paren') {
        // Stop parsing when we hit a closing parenthesis.
        break;
      }
      else {
        // Check if there's another term without an explicit operator.
        $next_term = $this->parseTerm();
        if ($next_term !== '') {
          $terms[] = $next_term;
        }
        else {
          break;
        }
      }
    }

    // If we have multiple terms without explicit operators, combine them
    // using the default conjunction, but force AND if any term contains
    // negation.
    if (count($terms) > 1) {
      // Check if any term contains negation - if so, force AND conjunction
      $conjunction = $this->containsNegation($terms) ? 'AND' : $this->getConjunction();

      $result = [
        '#conjunction' => $conjunction,
      ];
      foreach ($terms as $index => $term) {
        $result[$index] = $term;
      }
      return $result;
    }

    return $terms[0] ?? '';
  }

  /**
   * Checks if any of the terms contains negation.
   *
   * @param array $terms
   *   Array of terms to check.
   *
   * @return bool
   *   TRUE if any term contains negation, FALSE otherwise.
   */
  protected function containsNegation(array $terms): bool {
    foreach ($terms as $term) {
      if (is_array($term) && isset($term['#negation']) && $term['#negation']) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Parses a single term or grouped expression.
   *
   * @return array|string
   *   Parsed term or expression.
   */
  protected function parseTerm(): array|string {
    if ($this->position >= count($this->tokens)) {
      return '';
    }

    $token = $this->tokens[$this->position++];

    // Handle NOT operator.
    if ($token['type'] === 'operator' && $token['value'] === 'NOT') {
      $term = $this->parseTerm();
      return [
        '#negation' => TRUE,
        '#conjunction' => 'AND',
        $term,
      ];
    }

    // Handle parenthetical grouping.
    if ($token['type'] === 'open_paren') {
      $expression = $this->parseExpression();

      // Skip closing parenthesis.
      if ($this->position < count($this->tokens) &&
        $this->tokens[$this->position]['type'] === 'close_paren') {
        $this->position++;
      }

      return $expression;
    }

    // Handle regular terms and phrases.
    if ($token['type'] === 'term' || $token['type'] === 'phrase') {
      return $token['value'];
    }
    // Skip unexpected tokens.
    return '';
  }

  /**
   * Converts a non-empty string to an array with the default conjunction.
   *
   * @param string $parsed
   *   The parsed string.
   *
   * @return array|null
   *   The structured parsed array, or NULL if $parsed was empty.
   */
  protected function convertStringToConjunctionArray(string $parsed): ?array {
    if ($parsed === '') {
      return NULL;
    }

    return [
      '#conjunction' => $this->getConjunction(),
      $parsed,
    ];
  }

}
