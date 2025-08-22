/**
 * @file
 * Initialize GLightbox.
 */

/* global GLightbox */

((Drupal, once) => {
  "use strict";

  function initGLightbox() {
    // Collect all media elements and build slides
    const allMediaLinks = document.querySelectorAll('a.media-asset--link');
    const elements = [];
    
    allMediaLinks.forEach(link => {
      const mediaElement = link.closest('.media--audio, .media--external_embed, .media--document, .media--soundcloud');
      
      if (mediaElement) {
        const content = link.querySelector('.media-asset--content');
        if (content) {
          elements.push({
            content: content.innerHTML,
            width: 'auto',
            height: 'auto'
          });
        }
      } else {
        elements.push({
          href: link.getAttribute('href')
        });
      }
    });
    
    const lightbox = new GLightbox({
      elements: elements
    });
    
    // Override click handlers to use our lightbox
    allMediaLinks.forEach((link, index) => {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        lightbox.openAt(index);
      });
    });
  }

  // Drupal behavior
  Drupal.behaviors.mediaAssetGLightbox = {
    attach(context) {
      // Initialize GLightbox globally once
      once("mediaGLightboxGlobal", "body", context).forEach(() => {
        // Small delay to ensure media is fully rendered
        setTimeout(() => {
          initGLightbox();
        }, 100);
      });

    }
  };

})(Drupal, once);
