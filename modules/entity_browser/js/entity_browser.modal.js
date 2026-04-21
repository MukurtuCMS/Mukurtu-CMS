/**
 * @file entity_browser.modal.js
 *
 * Defines the behavior of the entity browser's modal display.
 */

(function ($, Drupal, drupalSettings, window, document) {

  'use strict';

  Drupal.entityBrowserModal = {};

  Drupal.AjaxCommands.prototype.select_entities = function (ajax, response, status) {
    var uuid = drupalSettings.entity_browser.modal.uuid;

    $(':input[data-uuid="' + uuid + '"]').trigger('entities-selected', [uuid, response.entities])
      .removeClass('entity-browser-processed').unbind('entities-selected');
  };

  /**
   * Registers behaviours related to modal display.
   */
  Drupal.behaviors.entityBrowserModal = {
    attach: function (context) {
      // Object.prototype.entries() isn't available in D9/IE11.
      for (var modalId in drupalSettings.entity_browser.modal) {
        var instance = drupalSettings.entity_browser.modal[modalId]
        var $button = $(':input[data-uuid="' + instance.uuid + '"]', context);

        if ($button.length !== 0 && !$button.hasClass('entity-browser-processed')) {
          for (var jsCallbackKey in instance.js_callbacks) {
            var callback = drupalSettings.entity_browser.modal[modalId].js_callbacks[jsCallbackKey];
            // Get the callback.
            callback = callback.split('.');
            var fn = window;

            for (var j = 0; j < callback.length; j++) {
              fn = fn[callback[j]];
            }

            if (typeof fn === 'function') {
              $button.bind('entities-selected', fn);
            }
          }
          if (instance.auto_open) {
            $button.focus();
            $button.click();
          }
          $button.addClass('entity-browser-processed');
        }
      }
    }
  };

  /**
   * Registers behaviours related to modal open and windows resize for fluid modal.
   */
  Drupal.behaviors.fluidModal = {
    attach: function (context) {
      var $window = $(window);

      // Be sure to run only once per window document.
      if (once('fluid-modal', 'body').length) {
        return;
      }

      // Recalculate dialog size on window resize.
      $window.resize(function (event) {
        Drupal.entityBrowserModal.fluidDialog();
      });

      // Catch dialog if opened within a viewport smaller than the dialog width
      // and recalculate size of all open dialogs.
      $(document).on('dialogopen', '.ui-dialog', function (event, ui) {
        Drupal.entityBrowserModal.fluidDialog();
      });

      // Disable scrolling of the whole browser window to not interfere with the
      // iframe scrollbar.
      $window.on({
        'dialog:aftercreate': function (event, dialog, $element, settings) {
          $('body').css({overflow: 'hidden'});
        },
        'dialog:beforeclose': function (event, dialog, $element) {
          $('body').css({overflow: 'inherit'});
        }
      });
    }
  };

  /**
   * Registers behaviours for adding throbber on modal open.
   */
  Drupal.behaviors.entityBrowserAddThrobber = {
    attach: function (context) {
      if (context === document) {
        $(window).on({
          'dialog:aftercreate': function (event, dialog, $element, settings) {
            if ($element.find('iframe.entity-browser-modal-iframe').length) {
              $element.append(Drupal.theme('ajaxProgressThrobber'));
            }
          }
        });
      }
    }
  };

  /**
   * Recalculates size of the modal.
   */
  Drupal.entityBrowserModal.fluidDialog = function () {

    var $visible = $('.ui-dialog:visible');
    // For each open dialog.
    $visible.each(function () {
      var $this = $(this);
      var dialog = $this.find('.ui-dialog-content').data('ui-dialog');
      // If fluid option == true.
      if (dialog && dialog.options.fluid) {
        var wWidth = $(window).width();
        // Check window width against dialog width.
        if (dialog.options.maxWidth && (wWidth > parseInt(dialog.options.maxWidth) + 50)) {
          dialog.option('width', dialog.options.maxWidth);
        }
        else {
          // If no maxWidth is defined, make it responsive.
          dialog.option('width', '92%');
        }

        var vHeight = $(window).height();
        // Check window width against dialog width.
        if (dialog.options.maxHeight && vHeight > (parseInt(dialog.options.maxHeight) + 50)) {
          dialog.option('height', dialog.options.maxHeight);
        }
        else {
          // If no maxHeight is defined, make it responsive.
          dialog.option('height', .92 * vHeight);

          // Because there is no iframe height 100% in HTML 5, we have to set
          // the height of the iframe as well.
          var contentHeight = $this.find('.ui-dialog-content').height();
          $this.find('iframe').css('height', contentHeight);
        }

        // Reposition dialog.
        dialog.option('position', dialog.options.position);
      }
    });

    /**
     * Close modal popup on escape key press.
     */
    Drupal.behaviors.closeModalOnEscapeKeyPress = {
      attach: function (context) {
        $(document).on('keydown', function (event) {
          if (event.key == 'Escape') {
            $(document).find('.entity-browser-modal-iframe').parents('.ui-dialog').eq(0).find('.ui-dialog-titlebar-close').click();
          }
        });
      }
    };
  };

}(jQuery, Drupal, drupalSettings, window, document));
