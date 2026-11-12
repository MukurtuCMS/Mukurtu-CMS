/**
 * @file
 * Uploads the files picked for a multi-file field one request at a time.
 *
 * Core sends every file picked for a field in a single request, so the
 * server's upload limit applies to all of them together, and one request
 * over that limit loses the whole batch. Fields with the
 * js-mukurtu-sequential-upload class instead upload each file in its own
 * request, so the limit applies to each file.
 *
 * Each response replaces the field, along with any error message about the
 * file it sent. The messages are moved above the field, so an error about one
 * file is still there after the files after it upload.
 */

(($, Drupal, once) => {
  const FIELD = '.js-form-managed-file.js-mukurtu-sequential-upload';
  const UPLOAD_BUTTON = '.js-form-submit[data-drupal-selector$="upload-button"]';

  /**
   * The upload a queue is waiting to send, if any.
   *
   * @type {{parents: (string|null), done: function(XMLHttpRequest)}|null}
   */
  let pending = null;

  /**
   * Files still to upload, keyed by the file input's name.
   *
   * The response replaces the input, and the new one has the same name. Its
   * data-drupal-selector and ID change on every upload.
   *
   * @type {Map<string, object>}
   */
  const queues = new Map();

  /**
   * Finds a field's current file input.
   *
   * @param {string} key
   *   The input's name.
   *
   * @return {HTMLInputElement|null}
   *   The input, or null if it's gone.
   */
  function findInput(key) {
    return document.querySelector(
      `${FIELD} input[type="file"][name="${CSS.escape(key)}"]`,
    );
  }

  /**
   * Finds or adds the area above a field that keeps its upload messages.
   *
   * It sits outside the field's AJAX wrapper, so responses don't replace it.
   *
   * @param {HTMLInputElement} input
   *   The field's file input.
   * @param {string} key
   *   The input's name.
   *
   * @return {HTMLElement|null}
   *   The area, or null if the field has no AJAX wrapper.
   */
  function messageArea(input, key) {
    const existing = document.querySelector(
      `[data-mukurtu-upload-messages="${CSS.escape(key)}"]`,
    );
    if (existing) {
      return existing;
    }
    const wrapper = input.closest('[id^="ajax-wrapper"]');
    if (!wrapper) {
      return null;
    }
    const area = document.createElement('div');
    area.setAttribute('data-mukurtu-upload-messages', key);
    wrapper.before(area);
    return area;
  }

  /**
   * Reads the element_parents query parameter from an AJAX URL.
   *
   * Every upload request for a field has the same element_parents, and
   * requests from other fields and forms don't.
   *
   * @param {string} url
   *   The URL.
   *
   * @return {string|null}
   *   The value, or null if there isn't one.
   */
  function elementParents(url) {
    try {
      return new URL(url, window.location.href).searchParams.get(
        'element_parents',
      );
    } catch (e) {
      return null;
    }
  }

  /**
   * Announces how the uploads went, and forgets the queue.
   *
   * @param {object} queue
   *   The finished queue.
   */
  function finish(queue) {
    queues.delete(queue.key);
    if (queue.failed) {
      Drupal.announce(
        Drupal.t(
          '@failed of @total files could not be uploaded. Check the error messages for details.',
          { '@failed': queue.failed, '@total': queue.total },
        ),
        'assertive',
      );
      return;
    }
    Drupal.announce(
      Drupal.formatPlural(
        queue.total,
        'Finished uploading 1 file.',
        'Finished uploading @count files.',
      ),
    );
  }

  /**
   * Checks whether a request came back as a readable AJAX response.
   *
   * @param {XMLHttpRequest} xhr
   *   The finished request.
   *
   * @return {boolean}
   *   TRUE if the server answered with a list of AJAX commands.
   */
  function answered(xhr) {
    if (xhr.status < 200 || xhr.status >= 300) {
      return false;
    }
    try {
      return Array.isArray(JSON.parse(xhr.responseText));
    } catch (e) {
      return false;
    }
  }

  /**
   * Keeps the messages from the last upload, and notes whether it failed.
   *
   * @param {object} queue
   *   The queue that sent the upload.
   * @param {XMLHttpRequest} xhr
   *   The finished request.
   */
  function afterUpload(queue, xhr) {
    const input = findInput(queue.key);
    const wrapper = input ? input.closest('[id^="ajax-wrapper"]') : null;
    const area = input ? messageArea(input, queue.key) : null;
    let failed = !input || !answered(xhr);
    if (wrapper) {
      wrapper.querySelectorAll('[data-drupal-messages]').forEach((messages) => {
        if (messages.querySelector('.messages--error, [role="alert"]')) {
          failed = true;
        }
        if (area) {
          // Screen readers already announced these alerts when they appeared,
          // so moving them shouldn't announce them again. Without the
          // attribute, Drupal's own message handling no longer treats the
          // moved copy as a place to put new messages.
          messages
            .querySelectorAll('[role="alert"]')
            .forEach((alert) => alert.removeAttribute('role'));
          messages.removeAttribute('data-drupal-messages');
          area.append(messages);
        }
      });
    }
    if (failed) {
      queue.failed += 1;
    }
  }

  /**
   * Uploads the next file in a queue, or finishes it.
   *
   * @param {object} queue
   *   The queue.
   */
  function next(queue) {
    const input = findInput(queue.key);
    const field = input ? input.closest('.js-form-managed-file') : null;
    const button = field ? field.querySelector(UPLOAD_BUTTON) : null;
    if (!queue.files.length || !button) {
      queue.failed += queue.files.length;
      finish(queue);
      return;
    }

    const file = queue.files.shift();
    queue.current += 1;
    const transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;

    const ajax = Drupal.ajax.instances.find(
      (instance) => instance && instance.element === button,
    );
    pending = {
      parents: ajax ? elementParents(ajax.url) : null,
      done: (xhr) => {
        afterUpload(queue, xhr);
        next(queue);
      },
    };

    Drupal.announce(
      Drupal.t('Uploading file @current of @total: @filename.', {
        '@current': queue.current,
        '@total': queue.total,
        '@filename': file.name,
      }),
    );
    $(button).trigger('mousedown');
  }

  /**
   * Starts uploading files one at a time, or adds them to a running queue.
   *
   * @param {HTMLInputElement} input
   *   The field's file input.
   * @param {File[]} files
   *   The files to upload.
   */
  function start(input, files) {
    const key = input.name;
    const running = queues.get(key);
    if (running) {
      running.files.push(...files);
      running.total += files.length;
      return;
    }
    const area = messageArea(input, key);
    if (area) {
      area.replaceChildren();
    }
    const queue = { key, files, total: files.length, current: 0, failed: 0 };
    queues.set(key, queue);
    next(queue);
  }

  /**
   * Takes several picked files away from core's single-request upload.
   *
   * Listens in the capture phase on the document, so it runs before core's
   * change handlers on the input. Core still handles a single file.
   *
   * @param {Event} event
   *   The change event.
   */
  function onChange(event) {
    const input = event.target;
    if (
      !(input instanceof HTMLInputElement) ||
      input.type !== 'file' ||
      !input.closest(FIELD) ||
      !input.files ||
      input.files.length < 2 ||
      typeof DataTransfer === 'undefined' ||
      !input.name
    ) {
      return;
    }
    event.stopImmediatePropagation();
    start(input, Array.from(input.files));
  }

  /**
   * Watches for the end of the request a queue is waiting on.
   *
   * Listens on the browser's own request rather than jQuery's ajaxComplete,
   * which never fires when Drupal can't read the response: its error handler
   * throws first, and the queue would wait forever.
   *
   * @param {object} options
   *   The jQuery AJAX options for a request about to be sent.
   */
  function watchRequest(options) {
    if (
      !pending ||
      (pending.parents !== null &&
        elementParents(options.url) !== pending.parents)
    ) {
      return;
    }
    const { done } = pending;
    pending = null;
    const createXhr = options.xhr;
    options.xhr = () => {
      const xhr = createXhr();
      // loadend comes after jQuery and Drupal have handled the response, so
      // the field has been replaced by then. The timeout lets the response's
      // behaviors finish attaching to the new field.
      xhr.addEventListener('loadend', () => setTimeout(() => done(xhr), 0));
      return xhr;
    };
  }

  /**
   * Uploads files one at a time in fields that ask for it.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.mukurtuSequentialUpload = {
    attach(context) {
      once('mukurtu-sequential-upload', 'html', context).forEach(() => {
        document.addEventListener('change', onChange, true);
        $.ajaxPrefilter(watchRequest);
      });
    },
  };
})(jQuery, Drupal, once);
