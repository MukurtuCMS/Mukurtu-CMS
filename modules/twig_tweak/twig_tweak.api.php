<?php

/**
 * @file
 * Hooks specific to the Twig Tweak module.
 */

use Drupal\Component\Utility\Unicode;
use Drupal\node\NodeInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alters Twig Tweak functions.
 *
 * @param \Twig\TwigFunction[] $functions
 *   Twig functions to alter.
 */
function hook_twig_tweak_functions_alter(array &$functions): void {
  // @phpcs:disable
  // A simple way to implement lazy loaded global variables.
  $callback = static fn (string $name): ?string =>
    match ($name) {
      'foo' => 'Foo',
      'bar' => 'Bar',
      default => NULL,
    };
  $functions[] = new TwigFunction('var', $callback);
  // @phpcs:enable
}

/**
 * Alters Twig Tweak filters.
 *
 * @param \Twig\TwigFilter[] $filters
 *   Twig filters to alter.
 */
function hook_twig_tweak_filters_alter(array &$filters): void {
  $filters[] = new TwigFilter('str_pad', 'str_pad');
  $filters[] = new TwigFilter('ucfirst', [Unicode::class, 'ucfirst']);
  $filters[] = new TwigFilter('lcfirst', [Unicode::class, 'lcfirst']);
}

/**
 * Alters Twig Tweak tests.
 *
 * @param \Twig\TwigTest[] $tests
 *   Twig tests to alter.
 */
function hook_twig_tweak_tests_alter(array &$tests): void {
  $callback = static fn (NodeInterface $node): bool =>
    \Drupal::time()->getRequestTime() - $node->getCreatedTime() > 3600 * 24 * 365;
  $tests[] = new TwigTest('outdated', $callback);
}

/**
 * @} End of "addtogroup hooks".
 */
