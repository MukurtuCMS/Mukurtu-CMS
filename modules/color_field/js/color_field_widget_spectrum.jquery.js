/**
 * @file
 * Javascript for Color Field.
 */

(function ($, Drupal, once) {

  'use strict';

  // jQuery 4 polyfill for isArray function
  $.isArray = $.isArray || Array.isArray;

  /**
   * Enables spectrum on color elements.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches a spectrum widget to a color input element.
   */
  Drupal.behaviors.color_field_spectrum = {
    attach: function (context, settings) {

      $(once('colorFieldSpectrum', '.js-color-field-widget-spectrum', context)).each(function (index, element) {
        const $element = $(element);
        const $element_color = $element.find('.js-color-field-widget-spectrum__color');
        const $element_opacity = $element.find('.js-color-field-widget-spectrum__opacity');
        const spectrum_settings = settings.color_field.color_field_widget_spectrum[$element.attr('id')];
        $element_opacity.parent().hide();

        $element_color.spectrum({
          showInitial: true,
          preferredFormat: "hex",
          showInput: spectrum_settings.show_input,
          showAlpha: spectrum_settings.show_alpha,
          showPalette: spectrum_settings.show_palette,
          showPaletteOnly: spectrum_settings.show_palette_only,
          palette:  spectrum_settings.palette,
          showButtons: spectrum_settings.show_buttons,
          allowEmpty: spectrum_settings.allow_empty,
          chooseText: spectrum_settings.choose_text,
          cancelText: spectrum_settings.cancel_text,
          appendTo: $element_color.parent(),

          change: function (truecolor) {
            let hexColor = '';
            let opacity = '';

            if (truecolor) {
              hexColor = truecolor.toHexString();
              opacity = Math.round((truecolor._roundA + Number.EPSILON) * 100) / 100;
            }

            $element_color.val(hexColor);
            $element_opacity.val(opacity);
          }

        });

        // Set alpha value on load.
        if (!!spectrum_settings.show_alpha) {
          const truecolor = $element_color.spectrum("get");
          const alpha = $element_opacity.val();
          if (alpha > 0) {
            truecolor.setAlpha(alpha);
            $element_color.spectrum("set", truecolor);
          }
        }

      });
    }
  };

})(jQuery, Drupal, once);
