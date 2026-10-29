<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\FocusFirstCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Turns the character palette on and lists its characters (#2560).
 *
 * The palette is off by default. Characters are kept here in groups, often
 * one per language, rather than on Language terms, so deleting a term never
 * takes a community's character list with it.
 *
 * Every group ends with one empty row and an "Add new row" button, and there
 * is always one empty group at the end for adding a new one. Saving drops
 * whatever was left empty.
 */
class CharacterPaletteSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'mukurtu_core.character_palette';

  /**
   * Empty rows a group starts with, before any are added.
   */
  const EMPTY_ROWS = 1;

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
      $empty_rows = $form_state->get(['empty_rows', $index]) ?? static::EMPTY_ROWS;
      $form['groups'][$index] = $this->buildGroup($group, $index, $empty_rows, $index === count($groups) - 1);
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Builds one group's fieldset.
   *
   * @param array $group
   *   The group as stored: a label and a list of characters.
   * @param int $index
   *   The group's position on the form.
   * @param int $empty_rows
   *   How many empty rows to show after the group's characters.
   * @param bool $is_new
   *   Whether this is the empty group for adding a new one.
   */
  protected function buildGroup(array $group, int $index, int $empty_rows, bool $is_new): array {
    $label = (string) ($group['label'] ?? '');
    $legend = $is_new ? $this->t('New language') : $label;
    $wrapper_id = static::groupWrapperId($index);

    $element = [
      '#type' => 'fieldset',
      '#title' => $legend,
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];
    $element['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Language name'),
      '#description' => $this->t("Shown in the palette's language menu."),
      '#default_value' => $label,
      '#maxlength' => static::NAME_MAX_LENGTH,
    ];

    // Before the table and tied to it, so screen readers hear it on the way
    // in rather than after the Add new row button.
    $help_id = $wrapper_id . '-help';
    $element['help'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Empty rows are ignored.'),
      '#attributes' => ['class' => ['description'], 'id' => $help_id],
    ];
    $element['characters'] = [
      '#type' => 'table',
      '#header' => [$this->t('Character'), $this->t('Name')],
      '#attributes' => ['aria-describedby' => $help_id],
    ];
    $rows = array_values($group['characters'] ?? []);
    $rows = array_merge($rows, array_fill(0, $empty_rows, ['character' => '', 'name' => '']));
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
    $element['add_row'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add new row'),
      // Every group has this button, so screen readers also hear which group
      // it adds to. The visible words start the name (WCAG 2.5.3).
      '#attributes' => ['aria-label' => $this->t('Add new row to @group', ['@group' => $legend])],
      '#name' => 'add_row_' . $index,
      '#group_index' => $index,
      '#submit' => ['::addRow'],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => '::addRowAjax',
        'wrapper' => $wrapper_id,
      ],
    ];

    if (!$is_new) {
      $element['delete'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Delete this language'),
      ];
    }

    return $element;
  }

  /**
   * The id of the element wrapping a group, for AJAX to replace.
   */
  protected static function groupWrapperId(int $index): string {
    return 'mukurtu-character-group-' . $index;
  }

  /**
   * Submit handler for "Add new row": one more empty row for that group.
   *
   * Works without JavaScript too, as a full-page rebuild.
   */
  public function addRow(array &$form, FormStateInterface $form_state): void {
    $index = $form_state->getTriggeringElement()['#group_index'];
    $empty_rows = $form_state->get(['empty_rows', $index]) ?? static::EMPTY_ROWS;
    $form_state->set(['empty_rows', $index], $empty_rows + 1);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for "Add new row".
   *
   * Replaces the group and moves focus to the new row's Character box, so
   * keyboard and screen reader users land where they're about to type.
   */
  public function addRowAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $index = $form_state->getTriggeringElement()['#group_index'];
    $selector = '#' . static::groupWrapperId($index);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand($selector, $form['groups'][$index]));
    $response->addCommand(new FocusFirstCommand($selector . ' tbody tr:last-child'));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // A group with characters needs a name for the palette's menu.
    foreach ($form_state->getValue('groups') ?? [] as $index => $group) {
      if (!empty($group['delete']) || trim((string) $group['label']) !== '') {
        continue;
      }
      if (static::cleanCharacters($group['characters'] ?? []) !== []) {
        $form_state->setError($form['groups'][$index]['label'], $this->t('Language name is required. Enter the language name.'));
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
