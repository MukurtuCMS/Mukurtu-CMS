<?php

/**
 * @file
 * Contains \SearchApiSubContext.
 */

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Gherkin\Node\FeatureNode;
use Drupal\DrupalExtension\Context\DrupalSubContextBase;

/**
 * Integrates Search API with the Behat Drupal Extension.
 *
 * @see https://behat-drupal-extension.readthedocs.io/en/v4.0.1/subcontexts.html
 */
class SearchApiSubContext extends DrupalSubContextBase {

  /**
   * Cached tags lists of features, keyed by feature hash.
   *
   * @var string[][]
   *
   * @see \SearchApiSubContext::getTagsFromFeature()
   */
  protected static $tags;

  /**
   * Triggers all queued indexing operations for this page request.
   *
   * This can be used in Behat tests to get items to be indexed immediately,
   * which would otherwise only happen at the end of the test run.
   *
   * Use the tag "@search_api" for tests that require this behavior. Example:
   *
   * @code
   * @search_api
   * Scenario: Article overview should only show published articles
   *   Given I am not logged in
   *   And "article" content:
   *     | title             | status |
   *     | Article published | 1      |
   *     | Article draft     | 0      |
   *   When I am on "/article-overview"
   *   Then I should see the text "Article published"
   *   And I should not see the text "Article draft"
   * @endcode
   *
   * @AfterStep
   */
  public function indexEntities(AfterStepScope $event) {
    $tags = static::getTagsFromFeature($event->getFeature());
    if (!in_array('search_api', $tags)) {
      return;
    }

    \Drupal::getContainer()->get('search_api.post_request_indexing')
      ->destruct();
  }

  /**
   * Extracts tags from a feature.
   *
   * @param \Behat\Gherkin\Node\FeatureNode $feature
   *   Feature node from which to extract the tags.
   *
   * @return string[]
   *   The tags list of the feature.
   */
  public static function getTagsFromFeature(FeatureNode $feature): array {
    $feature_hash = md5($feature->getFile());
    if (!isset(static::$tags[$feature_hash])) {
      $tags = [];
      foreach ($feature->getScenarios() as $scenario) {
        $scenario_tags = $scenario->getTags();
        $tags = array_unique(array_merge($tags, $scenario_tags), SORT_REGULAR);
      }
      static::$tags[$feature_hash] = $tags;
    }
    return static::$tags[$feature_hash];
  }

}
