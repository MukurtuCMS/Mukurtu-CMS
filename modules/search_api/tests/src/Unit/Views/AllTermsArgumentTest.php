<?php

namespace Drupal\Tests\search_api\Unit\Views;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Plugin\views\argument\SearchApiAllTerms;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use Drupal\search_api\Query\Condition;
use Drupal\search_api\Query\ConditionGroup;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Assert;

/**
 * Tests whether the SearchApiAllTerms argument plugin works correctly.
 *
 * @group search_api
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\views\argument\SearchApiAllTerms
 */
class AllTermsArgumentTest extends UnitTestCase {

  use TaxonomyTestTrait;

  /**
   * The plugin under test.
   *
   * @var \Drupal\search_api\Plugin\views\argument\SearchApiAllTerms
   */
  protected $plugin;

  /**
   * The first condition group set on the query.
   *
   * @var \Drupal\search_api\Query\ConditionGroupInterface|null
   */
  protected $conditionGroup = NULL;

  /**
   * Whether or not the query has been aborted, or the abort message (if given).
   *
   * @var bool|string
   */
  protected $aborted = FALSE;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->setupContainer();

    $this->plugin = new SearchApiAllTerms([], 'search_api_all_terms', [
      'vocabulary_fields' => [
        'voc_a' => [
          'field_voc_a',
        ],
        'voc_b' => [
          'field_voc_b_1',
          'field_voc_b_2',
        ],
      ],
    ]);
    $this->plugin->options['break_phrase'] = TRUE;

    $term_values = [
      1 => [
        'id' => 1,
        'label' => 'Term 1',
        'bundle' => 'voc_a',
      ],
      2 => [
        'id' => 2,
        'label' => 'Term 2',
        'bundle' => 'voc_a',
      ],
      3 => [
        'id' => 3,
        'label' => 'Term 3',
        'bundle' => 'voc_b',
      ],
      4 => [
        'id' => 4,
        'label' => 'Term 4',
        'bundle' => 'voc_c',
      ],
    ];
    $terms = [];
    foreach ($term_values as $tid => $values) {
      $term = $this->createMock(TermInterface::class);
      foreach ($values as $field => $value) {
        $term->method($field)->willReturn($value);
      }
      $terms[$tid] = $term;
    }
    $this->termStorage->method('loadMultiple')
      ->willReturnCallback(function (array $tids) use ($terms): array {
        return array_intersect_key($terms, array_flip($tids));
      });
  }

  /**
   * Tests whether the contextual filter works correctly.
   *
   * @covers ::query
   */
  public function testConditionalFilter() {
    $query = $this->createMock(SearchApiQuery::class);
    $query->method('createAndAddConditionGroup')
      ->willReturnCallback(function (string $conjunction, array $tags) {
        Assert::assertEmpty($tags);
        Assert::assertEmpty($this->conditionGroup);
        return $this->conditionGroup = new ConditionGroup($conjunction);
      });
    $query->method('addConditionGroup')
      ->willThrowException(new \Exception('Unexpected call to addConditionGroup().'));
    $query->method('createConditionGroup')
      ->willReturnCallback(function (string $conjunction, array $tags) {
        Assert::assertEmpty($tags);
        Assert::assertLessThan(3, func_num_args());
        return new ConditionGroup($conjunction);
      });
    $query->method('abort')
      ->willReturnCallback(function ($message = NULL) {
        $this->aborted = TRUE;
        if ($message !== NULL) {
          if ($message instanceof TranslatableMarkup) {
            $message = strtr($message->getUntranslatedString(), $message->getArguments());
          }
          $this->aborted = $message;
        }
      });
    $this->plugin->query = $query;

    $this->executePluginQuery('1,2,3');
    $this->assertFalse($this->aborted);
    $this->assertEquals('AND', $this->conditionGroup->getConjunction());
    $expected = [
      new Condition('field_voc_a', 1),
      new Condition('field_voc_a', 2),
      (new ConditionGroup('OR'))
        ->addCondition('field_voc_b_1', 3)
        ->addCondition('field_voc_b_2', 3),
    ];
    $this->assertEquals($expected, $this->conditionGroup->getConditions());

    $this->executePluginQuery('1+2+3');
    $this->assertFalse($this->aborted);
    $this->assertEquals('OR', $this->conditionGroup->getConjunction());
    $expected = [
      new Condition('field_voc_a', [1, 2], 'IN'),
      new Condition('field_voc_b_1', [3], 'IN'),
      new Condition('field_voc_b_2', [3], 'IN'),
    ];
    $this->assertEquals($expected, $this->conditionGroup->getConditions());

    // Set the filter to negated.
    $this->plugin->options['not'] = TRUE;

    $this->executePluginQuery('1,2,3');
    $this->assertFalse($this->aborted);
    $this->assertEquals('OR', $this->conditionGroup->getConjunction());
    $expected = [
      new Condition('field_voc_a', 1, '<>'),
      new Condition('field_voc_a', 2, '<>'),
      (new ConditionGroup())
        ->addCondition('field_voc_b_1', 3, '<>')
        ->addCondition('field_voc_b_2', 3, '<>'),
    ];
    $this->assertEquals($expected, $this->conditionGroup->getConditions());

    $this->executePluginQuery('1+2+3');
    $this->assertFalse($this->aborted);
    $this->assertEquals('AND', $this->conditionGroup->getConjunction());
    $expected = [
      new Condition('field_voc_a', [1, 2], 'NOT IN'),
      new Condition('field_voc_b_1', [3], 'NOT IN'),
      new Condition('field_voc_b_2', [3], 'NOT IN'),
    ];
    $this->assertEquals($expected, $this->conditionGroup->getConditions());

    // Check various error conditions.
    $this->plugin->options['not'] = FALSE;

    $this->executePluginQuery('1,2,foo');
    $this->assertEquals('Invalid taxonomy term ID given for "All taxonomy term fields" contextual filter.', $this->aborted);

    $this->executePluginQuery('1+2+foo');
    $this->assertFalse($this->aborted);
    $this->assertEquals('OR', $this->conditionGroup->getConjunction());
    $expected = [
      new Condition('field_voc_a', [1, 2], 'IN'),
    ];
    $this->assertEquals($expected, $this->conditionGroup->getConditions());

    $this->executePluginQuery('1,2,4');
    $this->assertEquals('"All taxonomy term fields" contextual filter could not be applied as taxonomy term Term 4 (ID: 4) belongs to vocabulary voc_c, not contained in any indexed fields.', $this->aborted);

    $this->executePluginQuery('1+2+4');
    $this->assertFalse($this->aborted);
    $this->assertEquals('OR', $this->conditionGroup->getConjunction());
    $expected = [
      new Condition('field_voc_a', [1, 2], 'IN'),
    ];
    $this->assertEquals($expected, $this->conditionGroup->getConditions());

    $this->plugin->options['not'] = TRUE;
    $this->executePluginQuery('1+2+4');
    $this->assertEquals('"All taxonomy term fields" contextual filter could not be applied as taxonomy term Term 4 (ID: 4) belongs to vocabulary voc_c, not contained in any indexed fields.', $this->aborted);

    $this->executePluginQuery('1,2,4');
    $this->assertFalse($this->aborted);
    $this->assertEquals('OR', $this->conditionGroup->getConjunction());
    $expected = [
      new Condition('field_voc_a', 1, '<>'),
      new Condition('field_voc_a', 2, '<>'),
    ];
    $this->assertEquals($expected, $this->conditionGroup->getConditions());

    $this->plugin->options['not'] = FALSE;
    $this->plugin->options['break_phrase'] = FALSE;
    $this->executePluginQuery('1,2,3');
    $this->assertEquals('No valid taxonomy term IDs given for "All taxonomy term fields" contextual filter.', $this->aborted);

    $this->plugin->options['not'] = TRUE;
    $this->executePluginQuery('1,2,3');
    $this->assertFalse($this->aborted);
  }

  /**
   * Executes the plugin's query() method, after resetting the original state.
   *
   * @param string $argument
   *   The argument string to set on the plugin.
   */
  protected function executePluginQuery(string $argument) {
    $this->conditionGroup = NULL;
    $this->aborted = FALSE;
    $this->plugin->value = NULL;
    $this->plugin->argument_validated = NULL;
    $this->plugin->argument = $argument;
    $this->plugin->query();
  }

}
