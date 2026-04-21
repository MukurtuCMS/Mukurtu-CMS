/**
 * @file entity_browser.iframe.js
 *
 * Defines the behavior of the entity browser's iFrame display.
 */

(function ($, Drupal, drupalSettings, once) {

  'use strict';

  /**
   * Registers behaviours related to iFrame display.
   */
  Drupal.behaviors.entityBrowserIFrame = {
    attach: function (context) {
      $(once('iframe-click', '.entity-browser-handle.entity-browser-iframe', context)).on('click', Drupal.entityBrowserIFrame.linkClick);
      $(once('iframe-auto-open', '.entity-browser-handle.entity-browser-iframe', context)).each(function () {
        var uuid = $(this).attr('data-uuid');
        if (drupalSettings.entity_browser.iframe[uuid].auto_open) {
          $(this).click();
        }
      });
    }
  };

  Drupal.entityBrowserIFrame = {};

  /**
   * Handles click on "Select entities" link.
   */
  Drupal.entityBrowserIFrame.linkClick = function () {
    var uuid = $(this).attr('data-uuid');
    var original_path = $(this).attr('data-original-path');
    var iframeSettings = drupalSettings['entity_browser']['iframe'][uuid];
    var iframe = $(
      '<iframe />',
      {
        'src': iframeSettings['src'],
        'width': '100%',
        'height': iframeSettings['height'],
        'data-uuid': uuid,
        'data-original-path': original_path,
        'name': 'entity_browser_iframe_' + iframeSettings['entity_browser_id'],
        'id': 'entity_browser_iframe_' + iframeSettings['entity_browser_id']
      }
    );

    var throbber = $(Drupal.theme('ajaxProgressIndicatorFullscreen'));
    $(this).parent().css('width', iframeSettings['width']);

    // Register callbacks.
    if (drupalSettings.entity_browser.iframe[uuid].js_callbacks || false) {
      Drupal.entityBrowser.registerJsCallbacks(this, drupalSettings.entity_browser.iframe[uuid].js_callbacks, 'entities-selected');
    }

    $(this).parent().append(throbber).append(iframe).trigger('entityBrowserIFrameAppend');
    $(this).hide();
  };

}(jQuery, Drupal, drupalSettings, once));
