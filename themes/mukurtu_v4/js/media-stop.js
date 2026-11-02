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
   * Iframes with no rendered box are not reloaded: the hidden
   * .media-asset--glightbox-inline copies that GLightbox clones from are
   * display: none and never play, so reloading them would only add requests.
   * Hidden native players are still paused, since they can play sound
   * without a box. An iframe without a src is also skipped: Klaro holds
   * unconsented embeds in data-src until the viewer consents.
   *
   * @param {Element} root
   *   The element whose media should stop.
   */
  Drupal.mukurtuStopMedia = (root) => {
    if (!root) return;
    root.querySelectorAll('video, audio, iframe').forEach((el) => {
      if (el.tagName === 'IFRAME') {
        if (!el.getClientRects().length) return;
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
