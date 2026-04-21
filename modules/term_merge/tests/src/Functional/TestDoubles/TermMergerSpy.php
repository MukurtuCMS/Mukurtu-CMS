<?php

namespace Drupal\Tests\term_merge\Functional\TestDoubles;

use Drupal\taxonomy\TermInterface;

/**
 * A term merge test class that keeps a list of called functions.
 */
class TermMergerSpy extends TermMergerMock {

  /**
   * List of functions called and their arguments.
   *
   * The array key is the function name, the value is an array of arguments.
   *
   * @var array
   */
  private array $functionCalls = [];

  /**
   * {@inheritdoc}
   */
  public function mergeIntoNewTerm(array $terms_to_merge, string $new_term_label): TermInterface {
    $this->functionCalls[__FUNCTION__] = [$terms_to_merge, $new_term_label];
    return parent::mergeIntoNewTerm($terms_to_merge, $new_term_label);
  }

  /**
   * {@inheritdoc}
   */
  public function mergeIntoTerm(array $terms_to_merge, TermInterface $target_term): void {
    $this->functionCalls[__FUNCTION__] = [$terms_to_merge, $target_term];
    parent::mergeIntoTerm($terms_to_merge, $target_term);
  }

  /**
   * Checks a function was called on the object.
   *
   * @param string $function
   *   The name of the function to be checked.
   *
   * @throws \Exception
   *   Thrown when the function was not called.
   */
  public function assertFunctionCalled(string $function): void {
    if (!isset($this->functionCalls[$function])) {
      throw new \Exception("{$function} was not called");
    }
  }

  /**
   * Returns an array of called function names.
   *
   * @return string[]
   *   An array of called function names.
   */
  public function calledFunctions(): array {
    return array_keys($this->functionCalls);
  }

}
