/**
 * @file
 * Play/pause control for audio media rendered inside featured content cards.
 *
 * Attaches to each .audio-play-button, finds the sibling audio element inside
 * .media__audio-source, removes native controls, and wires play/pause.
 * Only one card may play at a time.
 */
(function (Drupal, once) {
  'use strict';

  let currentAudio = null;
  let currentButton = null;

  function stopCurrent() {
    if (currentAudio) {
      if (currentAudio.isConnected) {
        currentAudio.pause();
      }
      currentAudio = null;
    }
    if (currentButton) {
      currentButton.classList.remove('is-playing');
      currentButton.setAttribute('aria-label',
        Drupal.t('Play @name', { '@name': currentButton.dataset.mediaName || '' }));
      currentButton.setAttribute('aria-pressed', 'false');
      currentButton = null;
    }
  }

  Drupal.behaviors.audioThumbnailPlayer = {
    attach(context) {
      once('audio-thumbnail-player', '.audio-play-button', context)
        .forEach(function (button) {
          const wrapper = button.closest('.media--digital-heritage-sidebar');
          const audioEl = wrapper && wrapper.querySelector('audio');
          if (!audioEl) return;

          // Hand off control to JS; preload nothing until the user requests it.
          audioEl.removeAttribute('controls');
          audioEl.preload = 'none';

          button.addEventListener('click', function (e) {
            e.preventDefault();
            // Stop the click bubbling to the card-link ::after overlay.
            e.stopPropagation();

            if (audioEl.paused) {
              stopCurrent();
              audioEl.play().catch(function () { stopCurrent(); });
              currentAudio = audioEl;
              currentButton = button;
              button.classList.add('is-playing');
              button.setAttribute('aria-label',
                Drupal.t('Pause @name', { '@name': button.dataset.mediaName || '' }));
              button.setAttribute('aria-pressed', 'true');
            } else {
              stopCurrent();
            }
          });

          audioEl.addEventListener('ended', function () {
            if (currentAudio === audioEl) {
              stopCurrent();
            }
          });
        });
    },
  };

})(Drupal, once);
