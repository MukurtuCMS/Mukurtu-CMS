/**
 * @file
 * Stop media that is playing in a slide the viewer has moved away from.
 */

((Drupal) => {
  "use strict";

  /**
   * Stops audio, video and embedded players inside an element.
   *
   * Native <audio>/<video> elements are paused. Embedded players (YouTube,
   * Vimeo, SoundCloud, pasted embed code) live in cross-origin iframes that
   * can't be paused without loading each provider's own player API, so the
   * iframe is reloaded instead, which stops it on every provider.
   *
   * Elements with no rendered box are skipped: the hidden
   * .media-asset--glightbox-inline copies that GLightbox clones from are
   * display: none and never play, so reloading them would only add requests.
   * An iframe without a src is also skipped: Klaro holds unconsented embeds
   * in data-src until the viewer consents.
   *
   * @param {Element} root
   *   The element whose media should stop.
   * @param {Element|null} skip
   *   (optional) An element inside root whose media should keep playing.
   */
  Drupal.mukurtuStopMedia = (root, skip = null) => {
    if (!root) return;
    root.querySelectorAll('video, audio, iframe').forEach((el) => {
      if (skip && skip.contains(el)) return;
      if (!el.getClientRects().length) return;

      if (el.tagName === 'IFRAME') {
        const src = el.getAttribute('src');
        if (src && src !== 'about:blank') {
          el.setAttribute('src', src);
        }
      }
      else if (!el.paused) {
        el.pause();
      }
    });
  };

})(Drupal);
