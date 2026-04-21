<?php

namespace Drupal\Tests\search_api\Unit\Processor;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Plugin\search_api\processor\IgnoreCharacters;
use Drupal\search_api\Query\Condition;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the "Ignore characters" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\IgnoreCharacter
 */
class IgnoreCharacterTest extends UnitTestCase {

  use ProcessorTestTrait;
  use TestItemsTrait;

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->processor = new IgnoreCharacters(['ignorable' => ''], 'ignore_character', []);
  }

  /**
   * Tests preprocessing with different ignorable character sets.
   *
   * @param string $passed_value
   *   The value that should be passed into process().
   * @param string $expected_value
   *   The expected processed value.
   * @param string[] $character_classes
   *   The "ignorable_classes" setting to set on the processor.
   *
   * @dataProvider ignoreCharacterSetsDataProvider
   */
  public function testIgnoreCharacterSets($passed_value, $expected_value, array $character_classes) {
    $this->processor->setConfiguration(['ignorable_classes' => $character_classes]);
    $this->invokeMethod('process', [&$passed_value, 'text']);
    $this->assertEquals($expected_value, $passed_value);
  }

  /**
   * Data provider for testValueConfiguration().
   */
  public static function ignoreCharacterSetsDataProvider() {
    // cspell:disable
    return [
      ['word_s', 'words', ['Pc' => 'Pc']],
      ['word⁔s', 'words', ['Pc' => 'Pc']],

      ['word〜s', 'words', ['Pd' => 'Pd']],
      ['w–ord⸗s', 'words', ['Pd' => 'Pd']],

      ['word⌉s', 'words', ['Pe' => 'Pe']],
      ['word⦊s〕', 'words', ['Pe' => 'Pe']],

      ['word»s', 'words', ['Pf' => 'Pf']],
      ['word⸍s', 'words', ['Pf' => 'Pf']],

      ['word⸂s', 'words', ['Pi' => 'Pi']],
      ['w«ord⸉s', 'words', ['Pi' => 'Pi']],

      ['words%', 'words', ['Po' => 'Po']],
      ['wo*rd/s', 'words', ['Po' => 'Po']],

      ['word༺s', 'words', ['Ps' => 'Ps']],
      ['w❮ord⌈s', 'words', ['Ps' => 'Ps']],

      ['word៛s', 'words', ['Sc' => 'Sc']],
      ['wo₥rd₦s', 'words', ['Sc' => 'Sc']],

      ['w˓ords', 'words', ['Sk' => 'Sk']],
      ['wo˘rd˳s', 'words', ['Sk' => 'Sk']],

      ['word×s', 'words', ['Sm' => 'Sm']],
      ['wo±rd؈s', 'words', ['Sm' => 'Sm']],

      ['wo᧧rds', 'words', ['So' => 'So']],
      ['w᧶ord᧲s', 'words', ['So' => 'So']],

      ["wor\x0Ads", 'words', ['Cc' => 'Cc']],
      ["wo\x0Crds", 'words', ['Cc' => 'Cc']],

      ['word۝s', 'words', ['Cf' => 'Cf']],
      ['wo᠎rd؁s', 'words', ['Cf' => 'Cf']],

      ['words', 'words', ['Co' => 'Co']],
      ['wo󿿽rds', 'words', ['Co' => 'Co']],

      ['wordॊs', 'words', ['Mc' => 'Mc']],
      ['worौdংs', 'words', ['Mc' => 'Mc']],

      ['wo⃞rds', 'words', ['Me' => 'Me']],
      ['wor⃤⃟ds', 'words', ['Me' => 'Me']],

      ['woྰrds', 'words', ['Mn' => 'Mn']],
      ['worྵdྶs', 'words', ['Mn' => 'Mn']],

      ['woྰrds', 'words', ['Mn' => 'Mn', 'Pd' => 'Pd', 'Pe' => 'Pe']],
      ['worྵdྶs', 'words', ['Mn' => 'Mn', 'Pd' => 'Pd', 'Pe' => 'Pe']],
    ];
    // cspell:enable
  }

  /**
   * Tests preprocessing with the "Ignorable characters" setting.
   *
   * @param string $passed_value
   *   The value that should be passed into process().
   * @param string $expected_value
   *   The expected processed value.
   * @param string $ignorable
   *   The "ignorable" setting to set on the processor.
   *
   * @dataProvider ignorableCharactersDataProvider
   */
  public function testIgnorableCharacters($passed_value, $expected_value, $ignorable) {
    $this->processor->setConfiguration(['ignorable' => $ignorable, 'ignorable_classes' => []]);
    $this->invokeMethod('process', [&$passed_value, 'text']);
    $this->assertSame($expected_value, $passed_value);
  }

  /**
   * Provides sets of test parameters for testIgnorableCharacters().
   *
   * @return array
   *   Sets of arguments for testIgnorableCharacters().
   */
  public static function ignorableCharactersDataProvider() {
    return [
      ['abcde', 'ace', '[bd]'],
      [['abcde', 'abcdef'], ['ace', 'ace'], '[bdf]'],
      ["ab.c'de", "a.'de", '[b-c]'],
      ['foo 13$%& (bar)[93]', 'foo $%& (bar)[]', '\d'],
      ["foo.com n'd bar. baz.gv. bla.net.", 'foo.com nd bar baz.gv bla.net', '[\'¿¡!?,:;]|\.(?= |$)'],
    ];
  }

  /**
   * Tests whether "IS NULL" conditions are correctly kept.
   *
   * @see https://www.drupal.org/project/search_api/issues/3212925
   */
  public function testIsNullConditions() {
    $this->setUpMockContainer();
    $index = $this->createMock(IndexInterface::class);
    $index->method('getFields')->willReturn([
      'field' => (new Field($index, 'field'))->setType('string'),
    ]);
    $this->processor->setIndex($index);

    $passed_value = NULL;
    $this->invokeMethod('processConditionValue', [&$passed_value]);
    $this->assertNull($passed_value);

    $condition = new Condition('field', NULL);
    $conditions = [$condition];
    $this->invokeMethod('processConditions', [&$conditions]);
    $this->assertSame([$condition], $conditions);
  }

}
