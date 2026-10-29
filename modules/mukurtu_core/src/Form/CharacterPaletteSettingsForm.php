<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Turns the character palette on and lists its characters (#2560).
 *
 * The palette is off by default. Characters are kept here in groups, often
 * one per language, rather than on Language terms, so deleting a term never
 * takes a community's character list with it.
 *
 * Adding rows and groups needs no JavaScript: every group shows a few empty
 * rows and there is always one empty group at the end. Saving drops whatever
 * was left empty.
 */
class CharacterPaletteSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'mukurtu_core.character_palette';

  /**
   * Empty rows offered under each group's characters.
   */
  const EMPTY_ROWS = 5;

  /**
   * Longest character entry, in characters.
   *
   * Long enough for a base letter with several combining marks and a
   * modifier letter, short enough that nobody pastes a word by mistake.
   */
  const CHARACTER_MAX_LENGTH = 16;

  /**
   * Longest group or character name, in characters.
   */
  const NAME_MAX_LENGTH = 255;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_core_character_palette_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the character palette'),
      '#description' => $this->t('Adds a button beside text fields and search boxes that inserts the characters listed below. Each person can still hide it.'),
      '#default_value' => (bool) $config->get('enabled'),
    ];

    $form['groups'] = ['#tree' => TRUE];
    $groups = $config->get('groups') ?? [];
    // One empty group at the end is how a new group gets added.
    $groups[] = ['label' => '', 'characters' => []];
    foreach (array_values($groups) as $index => $group) {
      $form['groups'][$index] = $this->buildGroup($group, $index === count($groups) - 1);
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Builds one group's fieldset.
   *
   * @param array $group
   *   The group as stored: a label and a list of characters.
   * @param bool $is_new
   *   Whether this is the empty group for adding a new one.
   */
  protected function buildGroup(array $group, bool $is_new): array {
    $label = (string) ($group['label'] ?? '');
    $legend = $is_new ? $this->t('New group') : $label;

    $element = [
      '#type' => 'fieldset',
      '#title' => $legend,
    ];
    $element['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group name'),
      '#description' => $this->t("Shown in the palette's group menu, for example a language name."),
      '#default_value' => $label,
      '#maxlength' => static::NAME_MAX_LENGTH,
    ];

    $element['characters'] = [
      '#type' => 'table',
      '#header' => [$this->t('Character'), $this->t('Name')],
    ];
    $rows = array_values($group['characters'] ?? []);
    $rows = array_merge($rows, array_fill(0, static::EMPTY_ROWS, ['character' => '', 'name' => '']));
    foreach ($rows as $delta => $row) {
      // The column headings label the boxes visually. Screen readers get the
      // row number and the group, since every row repeats the same two
      // headings.
      $args = ['@number' => $delta + 1, '@group' => $legend];
      $element['characters'][$delta]['character'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Character @number in @group', $args),
        '#title_display' => 'invisible',
        '#default_value' => $row['character'] ?? '',
        '#maxlength' => static::CHARACTER_MAX_LENGTH,
        '#size' => 6,
      ];
      $element['characters'][$delta]['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Name for character @number in @group', $args),
        '#title_display' => 'invisible',
        '#default_value' => $row['name'] ?? '',
        '#maxlength' => static::NAME_MAX_LENGTH,
        '#size' => 30,
      ];
    }
    $element['help'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Empty rows are ignored. Save to add more rows.'),
      '#attributes' => ['class' => ['description']],
    ];

    if (!$is_new) {
      $element['delete'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Delete this group'),
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // A group with characters needs a name for the palette's menu. Core's own
    // required-field message, so it is already translated.
    foreach ($form_state->getValue('groups') ?? [] as $index => $group) {
      if (!empty($group['delete']) || trim((string) $group['label']) !== '') {
        continue;
      }
      if (static::cleanCharacters($group['characters'] ?? []) !== []) {
        $element = $form['groups'][$index]['label'];
        $form_state->setError($element, $this->t('@name field is required.', ['@name' => $element['#title']]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $groups = [];
    foreach ($form_state->getValue('groups') ?? [] as $group) {
      if (!empty($group['delete'])) {
        continue;
      }
      $label = trim((string) $group['label']);
      $characters = static::cleanCharacters($group['characters'] ?? []);
      if ($label === '' && $characters === []) {
        continue;
      }
      $groups[] = ['label' => $label, 'characters' => $characters];
    }

    $this->configFactory->getEditable(static::SETTINGS)
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('groups', $groups)
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Drops empty rows and trims what is left.
   *
   * Only surrounding spaces are trimmed. Anything inside a character is part
   * of it as the community writes it, so it is never normalized.
   *
   * @param array $rows
   *   Submitted rows, each with a character and a name.
   *
   * @return array
   *   The rows that have a character, in order.
   */
  public static function cleanCharacters(array $rows): array {
    $characters = [];
    foreach ($rows as $row) {
      $character = trim((string) ($row['character'] ?? ''));
      if ($character === '') {
        continue;
      }
      $characters[] = [
        'character' => $character,
        'name' => trim((string) ($row['name'] ?? '')),
      ];
    }
    return $characters;
  }

}
