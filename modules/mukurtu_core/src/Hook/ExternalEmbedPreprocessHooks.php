<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for gating the External Embed media type's raw
 * pasted HTML/iframe embed code behind Klaro consent.
 *
 * Klaro's own hook_preprocess_field() (klaro_preprocess_field() in
 * klaro.module) only rewrites raw HTML markup when a field's formatter
 * machine name is "html", which comes from the separate contrib HTML Field
 * Formatter module. Mukurtu's External Embed media type
 * (field_media_external_embed, a text_long base field defined in
 * \Drupal\mukurtu_media\Entity\ExternalEmbed) is rendered with core's plain
 * text_default formatter instead, so Klaro never sees it and arbitrary
 * editor-pasted embed codes (iframes, scripts, etc., from any domain)
 * render live regardless of consent. This mirrors klaro_preprocess_field()'s
 * "html" branch exactly, reusing the same KlaroHelper::processHtml() DOM
 * scan Klaro already ships for exactly this purpose; it only gates domains
 * that already have (or get) a klaro_app entity defined, same as every
 * other embed type.
 */
final class ExternalEmbedPreprocessHooks {

  /**
   * Implements hook_preprocess_field().
   */
  #[Hook('preprocess_field')]
  public function preprocessField(array &$variables): void {
    if ($variables['field_name'] !== 'field_media_external_embed' || !\Drupal::hasService('klaro.helper')) {
      return;
    }

    /** @var \Drupal\klaro\Utility\KlaroHelper $helper */
    $helper = \Drupal::service('klaro.helper');

    // Mirror klaro_preprocess_field()'s early exit exactly.
    if (!$helper->hasAccess()
      || $helper->onDisabledUri()
      || !$helper->getSettings()->get('auto_decorate_preprocess_field')
      || !$helper->consentManagementRequired()) {
      return;
    }

    $renderer = \Drupal::service('renderer');
    foreach ($variables['items'] as $i => $item) {
      // text_default's processed_text render element isn't pre-rendered to
      // #children yet at this stage (unlike the formatters Klaro's own
      // "html" branch targets), so render it explicitly first.
      // renderInIsolation() (not render()) is required here: it opens its
      // own render context, so it can be called reentrantly from inside
      // this already-active hook_preprocess_field() call without the
      // "render context is empty" exception plain render() throws.
      $html = (string) $renderer->renderInIsolation($item['content']);
      // Replace the whole content element with just #children (not
      // #markup): #children is trusted, already-rendered output that the
      // renderer prints as-is, whereas #markup would be re-run through the
      // renderer's default XSS filter (which strips <iframe> tags
      // entirely).
      $variables['items'][$i]['content'] = ['#children' => $helper->processHtml($html)];
    }
  }

}
