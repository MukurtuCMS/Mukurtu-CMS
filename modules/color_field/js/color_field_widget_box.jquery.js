/**
 * @file
 * Attaches behaviors for Drupal's color field.
 */

(function ($, Drupal, once) {

    'use strict';

    /**
     * Enables box widget on color elements.
     *
     * @type {Drupal~behavior}
     *
     * @prop {Drupal~behaviorAttach} attach
     *   Attaches a box widget to a color input element.
     */
    Drupal.behaviors.color_field = {
        attach: function (context, settings) {
            $(once('colorField', '.color-field-widget-box-form', context)).each(function (index, element) {
                var $element = $(element);
                var $input = $element.prev().find('input');
                $input.hide();
                var props = settings.color_field.color_field_widget_box.settings[$element.prop('id')];

                $element.empty().addColorPicker({
                    currentColor: $input.val(),
                    colors: props.palette,
                    blotchClass:'color_field_widget_box__square',
                    blotchTransparentClass:'color_field_widget_box__square--transparent',
                    addTransparentBlotch: !props.required,
                    clickCallback: function (color) {
                        $input.val(color).trigger('change');
                    }
                });
            });

        },
    };

})(jQuery, Drupal, once);
