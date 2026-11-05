<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\FocusFirstCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
 *
 * Rows reorder within their group, by dragging or with arrow buttons, and the
 * palette shows them in saved order. The form's weights are never stored:
 * saving writes the list in its new order.
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
   * The language manager.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

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
    $form['#attached']['library'][] = 'mukurtu_core/character_palette_settings';

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the character palette'),
      '#description' => $this->t('Adds a button to the corner of each page that opens the characters listed below, so people can insert them into text fields and search boxes.'),
      '#default_value' => (bool) $config->get('enabled'),
    ];

    $form['groups'] = ['#tree' => TRUE];
    $groups = $config->get('groups') ?? [];
    // One empty group at the end is how a new group gets added.
    $groups[] = ['label' => '', 'characters' => []];
    foreach (array_values($groups) as $index => $group) {
      $empty_rows = $form_state->get(['empty_rows', $index]) ?? static::EMPTY_ROWS;
      // Rebuilds and failed saves keep the order on the page, not the stored
      // one, so unsaved moves survive.
      $submitted = $form_state->getUserInput()['groups'][$index]['characters'] ?? [];
      $form['groups'][$index] = $this->buildGroup($group, $index, $empty_rows, $index === count($groups) - 1, $submitted);
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
   * @param array $submitted
   *   The group's submitted rows, if any, for their order.
   */
  protected function buildGroup(array $group, int $index, int $empty_rows, bool $is_new, array $submitted = []): array {
    $label = (string) ($group['label'] ?? '');
    $legend = $is_new ? $this->t('New language') : $label;
    $wrapper_id = static::groupWrapperId($index);

    $element = [
      '#type' => 'fieldset',
      // A fieldset renders its title as #markup, which keeps harmless tags, so
      // a name typed with angle brackets would not show as typed. The labels
      // below get $legend through t()'s @ placeholders, which escape it.
      '#title' => $is_new ? $legend : Html::escape($label),
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
    // in rather than after the Add new row button. Rare letters and marks
    // can be read as nothing at all, so it also asks for names.
    $help_id = $wrapper_id . '-help';
    $element['help'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['description'], 'id' => $help_id],
      'order' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('The palette shows characters in this order.'),
      ],
      'empty' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Empty rows are ignored.'),
      ],
      'names' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Screen readers read the name instead of the character. Add a name for any character a screen reader might skip or misread, such as ʼ or ʷ.'),
      ],
    ];
    // The Order column is what gets submitted. Dragging and the arrow buttons
    // both set it, and it's the only control left without JavaScript. Its
    // number box has no upper limit, unlike the select a weight element uses.
    $weight_class = 'mukurtu-character-weight-' . $index;
    $element['characters'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Character'),
        $this->t('Name'),
        ['data' => $this->t('Move'), 'class' => ['mukurtu-character-move']],
        ['data' => $this->t('Order'), 'class' => ['mukurtu-character-order']],
      ],
      '#attributes' => [
        'aria-describedby' => $help_id,
        'class' => ['mukurtu-character-table'],
        'data-group' => (string) $legend,
        'id' => 'mukurtu-character-table-' . $index,
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => $weight_class,
        ],
      ],
    ];
    $rows = array_values($group['characters'] ?? []);
    $rows = array_merge($rows, array_fill(0, $empty_rows, ['character' => '', 'name' => '']));
    // Rows keep their stored position as their key, however they're shown,
    // so each one's input stays with it and saving can see where it moved.
    uksort($rows, fn ($a, $b) => static::rowWeight($submitted[$a] ?? []) <=> static::rowWeight($submitted[$b] ?? []));
    $number = 0;
    foreach ($rows as $delta => $row) {
      $number++;
      // Core uses the id to show a row as changed again after Add new row.
      $element['characters'][$delta]['#attributes'] = [
        'class' => ['draggable'],
        'id' => $wrapper_id . '-row-' . $delta,
      ];
      // The column headings label the boxes visually. Screen readers get the
      // row number and the group, since every row repeats the same headings.
      // The arrow buttons' script keeps these numbers right as rows move.
      $args = ['@number' => $number, '@group' => $legend];
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
      // The script adds the arrow buttons here, since they only work with it.
      $element['characters'][$delta]['move'] = [
        '#wrapper_attributes' => ['class' => ['mukurtu-character-move']],
      ];
      $element['characters'][$delta]['weight'] = [
        '#type' => 'number',
        '#title' => $this->t('Order for character @number in @group', $args),
        '#title_display' => 'invisible',
        '#default_value' => $number,
        '#attributes' => ['class' => [$weight_class]],
        '#wrapper_attributes' => ['class' => ['mukurtu-character-order']],
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
   * The sort value of a submitted row.
   *
   * @param array $row
   *   A submitted row, or an empty array for a row that wasn't submitted.
   *
   * @return int
   *   The row's weight. A row without one, such as a new row, sorts last.
   */
  protected static function rowWeight(array $row): int {
    $weight = $row['weight'] ?? NULL;
    return is_numeric($weight) ? (int) $weight : PHP_INT_MAX;
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
   * keyboard and screen reader users land where they're about to type. The
   * selector names the box because the row now starts with a drag handle.
   */
  public function addRowAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $index = $form_state->getTriggeringElement()['#group_index'];
    $selector = '#' . static::groupWrapperId($index);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand($selector, $form['groups'][$index]));
    $response->addCommand(new FocusFirstCommand($selector . ' tbody tr:last-child input[type="text"]'));
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
    $positions = [];
    foreach ($form_state->getValue('groups') ?? [] as $index => $group) {
      if (!empty($group['delete'])) {
        continue;
      }
      $label = trim((string) $group['label']);
      $rows = $group['characters'] ?? [];
      uasort($rows, fn ($a, $b) => static::rowWeight($a) <=> static::rowWeight($b));
      $characters = static::cleanCharacters($rows);
      if ($label === '' && $characters === []) {
        continue;
      }
      $positions[$index] = [
        'group' => count($groups),
        'rows' => array_flip(array_keys($characters)),
      ];
      $groups[] = ['label' => $label, 'characters' => array_values($characters)];
    }

    // Before the save, which discards translations at positions the new list
    // doesn't have.
    $this->moveTranslations($positions);
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('groups', $groups)
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Keeps translated names with their characters.
   *
   * Configuration translations are stored by position in the list, so without
   * this a moved or deleted row would leave a translated name on the wrong
   * character, and a screen reader would read that name out.
   *
   * @param array $positions
   *   Where each kept group went, keyed by its old position: its new position
   *   as 'group', and its rows' new positions keyed by their old ones as
   *   'rows'.
   */
  protected function moveTranslations(array $positions): void {
    if (!$this->languageManager instanceof ConfigurableLanguageManagerInterface) {
      return;
    }
    foreach (array_keys($this->languageManager->getLanguages()) as $langcode) {
      $override = $this->languageManager->getLanguageConfigOverride($langcode, static::SETTINGS);
      $translated = $override->get('groups');
      if (!is_array($translated)) {
        continue;
      }
      $moved = [];
      foreach ($translated as $index => $group) {
        if (!isset($positions[$index])) {
          continue;
        }
        $rows = $group['characters'] ?? [];
        unset($group['characters']);
        foreach ($rows as $delta => $row) {
          if (isset($positions[$index]['rows'][$delta])) {
            $group['characters'][$positions[$index]['rows'][$delta]] = $row;
          }
        }
        if (isset($group['characters'])) {
          ksort($group['characters']);
        }
        if ($group !== []) {
          $moved[$positions[$index]['group']] = $group;
        }
      }
      ksort($moved);
      if ($moved === []) {
        $override->delete();
      }
      else {
        $override->set('groups', $moved)->save();
      }
    }
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
   *   The rows that have a character, in order, with their keys.
   */
  public static function cleanCharacters(array $rows): array {
    $characters = [];
    foreach ($rows as $delta => $row) {
      $character = trim((string) ($row['character'] ?? ''));
      if ($character === '') {
        continue;
      }
      $characters[$delta] = [
        'character' => $character,
        'name' => trim((string) ($row['name'] ?? '')),
      ];
    }
    return $characters;
  }

}
