/**
 * @file
 * Carousel display for Featured Content blocks.
 *
 * Splide does the moving. This file is mostly about the parts Splide does not
 * give us for free: translated controls, a pause mechanism that satisfies
 * WCAG 2.2.2, and keeping slides that are off screen out of the tab order.
 */

((Drupal, once) => {

  /**
   * Slides that are off screen must not be focusable.
   *
   * Splide sets aria-hidden on inactive slides, which handles screen readers,
   * but a link inside an aria-hidden slide is still a tab stop. Tabbing then
   * moves focus to something the user cannot see, which fails WCAG 2.4.3.
   * `inert` removes the whole subtree from the tab order and from the
   * accessibility tree in one go.
   */
  function updateInertState(splide) {
    splide.Components.Slides.forEach((slide, index) => {
      slide.slide.inert = index !== splide.index;
    });
  }

  /**
   * Keep the status text and the toggle label in step with the carousel.
   */
  function updateStatus(root, splide) {
    const status = root.querySelector('.featured-carousel__status');
    if (status) {
      status.textContent = Drupal.t('Item @current of @total', {
        '@current': splide.index + 1,
        '@total': splide.length,
      });
    }
  }

  function setToggleLabel(root, paused) {
    const text = root.querySelector('.featured-carousel__toggle-text');
    const button = root.querySelector('.featured-carousel__button--toggle');
    if (!text || !button) {
      return;
    }
    text.textContent = paused ? text.dataset.labelPlay : text.dataset.labelPause;
    // The button's own text is the accessible name, so aria-pressed would be
    // saying the same thing twice. It reports what the control does now.
    button.setAttribute('aria-label', text.textContent);
  }

  function init(root) {
    // Honour a system-level request for less movement. prefers-reduced-motion
    // is a statement about animation, so the carousel still works - it just
    // never starts moving on its own, and the pause button stays hidden
    // because there is nothing to pause.
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const splide = new Splide(root, {
      // 'slide' with rewind, never 'loop'. Looping works by cloning the
      // slides, which puts a second copy of every link in the DOM: the tab
      // order gained a duplicate of each card and screen readers read the set
      // twice. Rewind returns to the first slide from the last without any
      // clones, so what is in the DOM is what the author selected.
      type: 'slide',
      rewind: true,
      perPage: 1,
      pagination: false,
      // Our own buttons, so the labels are translatable.
      arrows: false,
      autoplay: !reduceMotion,
      interval: 6000,
      pauseOnHover: true,
      pauseOnFocus: true,
      // Splide's own keyboard handling is bound to the root rather than the
      // document, so it only fires once focus is inside the carousel.
      keyboard: 'focused',
    });

    // 'moved' rather than 'move': 'move' fires as the transition starts, when
    // is-active has not yet moved to the incoming slide. Combined with mount
    // ordering that left every slide inert on load, including the visible one,
    // so the card's link could not be reached by keyboard until the first
    // navigation.
    splide.on('mounted moved', () => {
      updateInertState(splide);
      updateStatus(root, splide);
    });

    splide.mount();

    const prev = root.querySelector('.featured-carousel__button--prev');
    const next = root.querySelector('.featured-carousel__button--next');
    const toggle = root.querySelector('.featured-carousel__button--toggle');

    if (prev) {
      prev.addEventListener('click', () => splide.go('<'));
    }
    if (next) {
      next.addEventListener('click', () => splide.go('>'));
    }

    if (toggle && !reduceMotion) {
      const autoplay = splide.Components.Autoplay;
      let paused = false;

      toggle.hidden = false;
      setToggleLabel(root, paused);

      toggle.addEventListener('click', () => {
        paused = !paused;
        if (paused) {
          autoplay.pause();
        }
        else {
          autoplay.play();
        }
        setToggleLabel(root, paused);
      });

      // pauseOnHover and pauseOnFocus stop the timer without going through
      // the button, so the label would otherwise claim it is still playing.
      // Only reflect those while the user has not pressed pause themselves.
      splide.on('autoplay:pause', () => {
        if (!paused) {
          setToggleLabel(root, true);
        }
      });
      splide.on('autoplay:play', () => {
        if (!paused) {
          setToggleLabel(root, false);
        }
      });
    }
  }

  Drupal.behaviors.featuredContentCarousel = {
    attach(context) {
      once('featuredContentCarousel', '.featured-carousel', context).forEach(init);
    },
  };
})(Drupal, once);
