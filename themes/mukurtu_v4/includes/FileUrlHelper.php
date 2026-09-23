<?php

/**
 * @file
 * Static helper for file URL generation that degrades instead of fataling.
 */

use Drupal\Core\File\Exception\InvalidStreamWrapperException;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\file\FileInterface;

/**
 * Builds file URLs, returning NULL where core would throw.
 *
 * File::createFileUrl() throws InvalidStreamWrapperException whenever a file's
 * URI carries a scheme that has no registered stream wrapper. The usual cause
 * is private://: Drupal registers that wrapper only while compiling the service
 * container, gated on the file_private_path setting, so a site that gains the
 * setting after its container was built keeps throwing until caches are
 * rebuilt. Sites migrated from v3 can also carry schemes the destination never
 * registers at all.
 *
 * Unguarded, one such file takes down an entire page render - a single bad
 * thumbnail in a browse listing is enough. Callers that route through here get
 * NULL instead and can fall back to a placeholder or drop the affordance.
 */
class MukurtuV4FileUrl {

  /**
   * Schemes already reported this request, keyed by scheme.
   *
   * A browse listing can hold hundreds of files sharing one broken scheme, and
   * they would otherwise write one identical log row each.
   *
   * @var bool[]
   */
  protected static array $loggedSchemes = [];

  /**
   * Returns a URL for a file, or NULL when one cannot be built.
   *
   * @param \Drupal\file\FileInterface|null $file
   *   The file entity, or NULL. Accepting NULL lets callers pass an unchecked
   *   ->entity reference straight through.
   * @param bool $relative
   *   TRUE for a root-relative URL, FALSE for an absolute one.
   *
   * @return string|null
   *   The URL, or NULL if there is no file or its stream wrapper is missing.
   */
  public static function fromFile(?FileInterface $file, bool $relative = TRUE): ?string {
    if (!$file) {
      return NULL;
    }

    try {
      return $file->createFileUrl($relative);
    }
    catch (InvalidStreamWrapperException $e) {
      static::logUnavailableScheme($file);
      return NULL;
    }
  }

  /**
   * Logs the first file seen for each unregistered scheme this request.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file whose URL could not be built.
   */
  protected static function logUnavailableScheme(FileInterface $file): void {
    $uri = $file->getFileUri() ?? '';
    $scheme = StreamWrapperManager::getScheme($uri) ?: '';

    if (isset(static::$loggedSchemes[$scheme])) {
      return;
    }
    static::$loggedSchemes[$scheme] = TRUE;

    \Drupal::logger('mukurtu_v4')->warning('Could not build a URL for file @fid (@uri): the "@scheme" stream wrapper is not registered, so the file was left out of the page. If the scheme is "private", confirm that file_private_path is set in settings.php and rebuild caches. Further "@scheme" files are not logged for this request.', [
      '@fid' => $file->id() ?? 'unsaved',
      '@uri' => $uri,
      '@scheme' => $scheme,
    ]);
  }

  /**
   * Clears the per-request log dedupe. For tests only.
   *
   * PHPUnit keeps statics alive between test methods in a process, so a test
   * asserting on log volume has to start from a known state.
   */
  public static function resetLoggedSchemes(): void {
    static::$loggedSchemes = [];
  }

}
