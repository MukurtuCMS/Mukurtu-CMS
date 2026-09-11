<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Reports whether the command line tools media processing needs are installed.
 *
 * mukurtu_media shells out to three binaries when media is saved, and each call
 * site checks for exit code 127 and returns early if the tool is absent:
 *
 * - pdftotext, in Document::preSave(), extracts PDF text into
 *   field_extracted_text so documents are searchable.
 * - pdftoppm, in Document::generateThumbnail(), renders page one as a PNG
 *   thumbnail.
 * - ffmpeg, in Video::generateThumbnail(), extracts the first frame as a
 *   thumbnail.
 *
 * Every one of those early returns is silent: nothing is shown to the person
 * uploading and nothing is logged. A site missing poppler-utils simply never
 * gets document thumbnails or searchable PDFs, with no indication why. Hence
 * surfacing it on the status report.
 *
 * Severity is Warning rather than Error because media still uploads; only the
 * derived features are lost. Compare PrivateFileSystemRequirements, which is an
 * Error because uploads fail outright.
 */
class MediaToolRequirements {

  use StringTranslationTrait;

  /**
   * The tools, in the order they are reported.
   *
   * Keyed by binary, with the probe argument each call site itself uses, so
   * this check cannot disagree with the code that depends on it.
   */
  protected const TOOLS = [
    'pdftotext' => '-v',
    'pdftoppm' => '--help',
    'ffmpeg' => '-version',
  ];

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    // exec() is what the consuming code uses, so if it is unavailable the
    // features are broken regardless and the honest answer is that this cannot
    // be determined here.
    if (!function_exists('exec') || $this->execIsDisabled()) {
      return [
        'mukurtu_core_media_tools' => [
          'title' => $this->t('Media processing tools'),
          'value' => $this->t('Cannot be checked'),
          'description' => $this->t('PHP is configured without exec(), so the presence of pdftotext, pdftoppm and ffmpeg cannot be determined. Mukurtu needs exec() to generate media thumbnails and to extract text from PDFs, so those features will not work on this server.'),
          'severity' => RequirementSeverity::Warning,
        ],
      ];
    }

    $missing = [];
    foreach (static::TOOLS as $binary => $probe) {
      if (!$this->isInstalled($binary, $probe)) {
        $missing[] = $binary;
      }
    }

    if (empty($missing)) {
      return [
        'mukurtu_core_media_tools' => [
          'title' => $this->t('Media processing tools'),
          'value' => $this->t('Available'),
          'severity' => RequirementSeverity::OK,
        ],
      ];
    }

    return [
      'mukurtu_core_media_tools' => [
        'title' => $this->t('Media processing tools'),
        'value' => $this->t('@tools not found', ['@tools' => implode(', ', $missing)]),
        'description' => [
          '#theme' => 'item_list',
          '#prefix' => '<p>' . $this->t('Mukurtu runs these command line tools on the web server when media is uploaded. When one is missing the feature it supports fails silently, with nothing shown to the person uploading and nothing written to the log.') . '</p>',
          '#items' => [
            $this->t('pdftotext (poppler-utils): extracts text from PDFs so documents can be found by search.'),
            $this->t('pdftoppm (poppler-utils): renders the first page of a PDF as its thumbnail.'),
            $this->t('ffmpeg (ffmpeg): extracts the first frame of a video as its thumbnail.'),
          ],
        ],
        'severity' => RequirementSeverity::Warning,
      ],
    ];
  }

  /**
   * Whether a binary responds rather than reporting "command not found".
   */
  protected function isInstalled(string $binary, string $probe): bool {
    $output = [];
    $result_code = -1;
    exec(escapeshellcmd($binary) . ' ' . $probe . ' 2>/dev/null', $output, $result_code);

    // 127 is the shell's "command not found". Any other code means the binary
    // ran, which is all the consuming code requires of it.
    return $result_code !== 127;
  }

  /**
   * Whether exec() is present but disabled via disable_functions.
   */
  protected function execIsDisabled(): bool {
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

    return in_array('exec', $disabled, TRUE);
  }

}
