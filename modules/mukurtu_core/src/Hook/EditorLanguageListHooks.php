<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Feeds CKEditor's Language dropdown from the site's language vocabulary.
 */
final class EditorLanguageListHooks {

  /**
   * The vocabulary holding the site's languages.
   */
  private const VOCABULARY = 'language';

  /**
   * The field holding a language term's BCP 47 code.
   */
  private const CODE_FIELD = 'field_language_code';

  /**
   * The field marking a language as read right to left.
   */
  private const RTL_FIELD = 'field_language_rtl';

  /**
   * Constructs the hook implementations.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_editor_js_settings_alter().
   *
   * CKEditor 5's Language plugin marks a run of text with a lang attribute,
   * which is what SC 3.1.2 Language of Parts needs for material that changes
   * language mid-field. Mukurtu's does: interlinear translations, and
   * interviews that switch speaker by speaker. A container-level language
   * cannot express that, and marking a whole field with one language marks
   * the rest of it falsely.
   *
   * Core offers three sources for the dropdown and none of them fits. Two
   * are fixed lists, the United Nations' six official languages and Drupal's
   * ~100 predefined ones, and neither holds the Indigenous languages this
   * software exists to serve. The third is the site's interface languages,
   * which would mean installing every content language as an interface
   * language to make it selectable.
   *
   * So the list is replaced here with the site's own language vocabulary,
   * the same terms the dictionary already uses, keeping one place where a
   * community names its languages.
   *
   * Done through this hook rather than by replacing the plugin class.
   * \Drupal\ckeditor5\Plugin\CKEditor5Plugin\Language is marked @internal, so
   * core offers it no backwards-compatibility promise and a subclass could
   * break on any minor release. hook_editor_js_settings_alter() is a
   * documented public API, and the settings path it writes to was confirmed
   * against a running editor.
   *
   * Terms with no code are skipped: an empty lang attribute marks nothing,
   * and the field deliberately allows blank. LanguageCodeHooks explains that
   * trade-off on the term form.
   */
  #[Hook('editor_js_settings_alter')]
  public function editorJsSettingsAlter(array &$settings): void {
    $formats = &$settings['editor']['formats'];
    if (!is_array($formats)) {
      return;
    }

    $list = NULL;
    foreach ($formats as &$format) {
      // Only formats whose toolbar actually offers the dropdown have this
      // key, so the vocabulary is not queried on pages that cannot use it.
      if (!isset($format['editorSettings']['config']['language']['textPartLanguage'])) {
        continue;
      }
      $list ??= $this->languageList();
      if ($list === []) {
        // Leaving core's list in place would offer the UN's six languages as
        // if they were the site's. An empty dropdown says more honestly that
        // no language has a code yet.
        $format['editorSettings']['config']['language']['textPartLanguage'] = [];
        continue;
      }
      $format['editorSettings']['config']['language']['textPartLanguage'] = $list;
    }
  }

  /**
   * Builds the dropdown list from language terms that have a code.
   *
   * @return array
   *   Entries shaped as CKEditor's TextPartLanguage plugin expects.
   */
  private function languageList(): array {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', self::VOCABULARY)
      ->exists(self::CODE_FIELD)
      ->sort('name')
      ->execute();
    if ($ids === []) {
      return [];
    }

    $list = [];
    foreach ($storage->loadMultiple($ids) as $term) {
      $code = trim((string) $term->get(self::CODE_FIELD)->value);
      if ($code === '') {
        continue;
      }
      // The term's own name, not an English reference name: a community
      // calls its language what it calls it.
      $entry = [
        'title' => $term->label(),
        'languageCode' => $code,
      ];
      // CKEditor only wants to hear about right-to-left, and only says so
      // when it is told: a term saved before the direction field existed has
      // no value, and left to right is the safe reading of that silence.
      if ($this->isRtl($term)) {
        $entry['textDirection'] = 'rtl';
      }
      $list[] = $entry;
    }
    return $list;
  }

  /**
   * Whether a language term is marked as read right to left.
   */
  private function isRtl($term): bool {
    if (!$term->hasField(self::RTL_FIELD) || $term->get(self::RTL_FIELD)->isEmpty()) {
      return FALSE;
    }
    return (bool) $term->get(self::RTL_FIELD)->value;
  }

}
