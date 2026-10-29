<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * One entry in a language's character palette (#2560).
 *
 * The character is what gets inserted. It can be more than one code point,
 * such as a letter with a combining mark, so it is stored as typed and never
 * normalized. The name is optional and is what a screen reader says for the
 * palette button, since many readers have nothing to say for rare letters.
 */
#[FieldType(
  id: "mukurtu_character",
  label: new TranslatableMarkup("Palette character"),
  description: new TranslatableMarkup("A character for the character palette, with an optional name."),
  default_widget: "mukurtu_character",
  no_ui: TRUE,
)]
class CharacterItem extends FieldItemBase {

  /**
   * Longest character entry, in characters.
   *
   * Long enough for a base letter with several combining marks and a
   * modifier letter, short enough that nobody pastes a word by mistake.
   */
  public const CHARACTER_MAX_LENGTH = 16;

  /**
   * Longest name, in characters.
   */
  public const NAME_MAX_LENGTH = 255;

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'character' => [
          'description' => 'The character or character sequence to insert.',
          'type' => 'varchar',
          'length' => self::CHARACTER_MAX_LENGTH,
        ],
        'name' => [
          'description' => 'An optional name screen readers say for the character.',
          'type' => 'varchar',
          'length' => self::NAME_MAX_LENGTH,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['character'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Character'))
      ->setRequired(TRUE);
    $properties['name'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Name'));
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'character';
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // A name with no character has nothing to insert.
    $character = $this->get('character')->getValue();
    return $character === NULL || trim((string) $character) === '';
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();
    $constraints[] = $this->getTypedDataManager()
      ->getValidationConstraintManager()
      ->create('ComplexData', [
        'character' => ['Length' => ['max' => self::CHARACTER_MAX_LENGTH]],
        'name' => ['Length' => ['max' => self::NAME_MAX_LENGTH]],
      ]);
    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();
    // Only surrounding spaces are trimmed. Anything inside is part of the
    // character as the community writes it.
    $this->character = trim((string) $this->character);
    $name = trim((string) $this->name);
    $this->name = $name === '' ? NULL : $name;
  }

}
