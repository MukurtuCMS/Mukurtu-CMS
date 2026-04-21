/**
 * @file
 * Javascript for the Geolocation address integration.
 */

/**
 * @typedef {Object} DrupalAjaxSettings
 *
 * @property {Object} extraData
 * @property {String} _drupal_ajax
 * @property {String} _triggering_element_name
 */

/**
 * @typedef {Object} AddressIntegrationSettings

 * @property {String} geocoder
 * @property {Object} settings
 * @property {String} address_field
 * @property {String} direction
 * @property {String} sync_mode
 */

/**
 * @typedef {Object} GeolocationAddress
 *
 * @property {String} country
 * @property {String} countryCode
 * @property {String} organization
 * @property {String} addressLine1
 * @property {String} addressLine2
 * @property {String} locality
 * @property {String} dependentLocality
 * @property {String} administrativeArea
 */

(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.geolocationAddressMapWidgetPendingActions = {
    attach: function () {
      $(document).ajaxComplete(function (event, xhr, settings) {
        if (
          typeof settings.extraData === 'undefined'
          || typeof settings.extraData._drupal_ajax === 'undefined'
          || typeof settings.extraData._triggering_element_name === 'undefined'
        ) {
          return;
        }

        $.each(Drupal.geolocation.widgets, function (index, widget) {
          if (typeof widget.settings.address_field === 'undefined') {
            return;
          }

          if (settings.extraData._triggering_element_name.slice(-14) !== '[country_code]') {
            widget.addressTriggerCalled = false;
          }

          if (settings.extraData._triggering_element_name.slice((widget.settings.address_field.length + 9) * -1) !== widget.settings.address_field + '_add_more') {
            widget.addressAddMoreCalled = false;
          }

          var currentPendingAddresses = widget.pendingAddedAddressInputs;
          for (var addressInputIndex = 0; addressInputIndex < currentPendingAddresses.length; addressInputIndex++) {
            var addressInputData = currentPendingAddresses[addressInputIndex];
            if (typeof addressInputData === 'undefined') {
              continue;
            }
            if (typeof addressInputData.delta === 'undefined') {
              continue;
            }

            var addressInput = widget.getAddressByDelta(addressInputData.delta);
            if (addressInput) {
              if (addressInput.find('.country').val() === addressInputData.address.countryCode) {
                widget.setAddress(addressInputData.address, addressInputData.delta);
                widget.pendingAddedAddressInputs.splice(addressInputIndex, 1);
                addressInputIndex--;
              }
              else {
                addressInput.find('.country').val(addressInputData.address.countryCode).trigger('change');
                this.addressTriggerCalled = true;
              }
            }
            else {
              widget.addNewAddressInput();
            }
          }
        });
      });
    }
  };

  Drupal.behaviors.geolocationAddressMapWidget = {
    /**
     * @param context
     * @param {Object} drupalSettings
     * @param {Object} drupalSettings.geolocation
     * @param {Object} drupalSettings.geolocation.addressIntegration
     */
    attach: function (context, drupalSettings) {
      /**
       * @param {String} sourceFieldName
       * @param {AddressIntegrationSettings} addressIntegrationSettings
       */
      $.each(drupalSettings.geolocation.addressIntegration, function (sourceFieldName, addressIntegrationSettings) {
        var addressWidgetWrapper = $('.field--name-' + addressIntegrationSettings.address_field.replace(/_/g, '-'), context);
        if (addressWidgetWrapper.length === 0) {
          return;
        }

        var geolocationWidgetWrapper = $('.field--name-' + sourceFieldName.replace(/_/g, '-'), context);
        if (geolocationWidgetWrapper.length === 0) {
          return;
        }

        var widget = Drupal.geolocation.widget.getWidgetById(geolocationWidgetWrapper.attr('id').toString());
        if (!widget) {
          return;
        }

        if (typeof widget.addressEnabled === 'undefined') {
          var elements = [];
          $.each(addressIntegrationSettings.ignore, function (key, value) {
              if (!value) {
                elements.push(key);
              }
            }
          );
          widget = $.extend(widget, {
            addressEnabled: true,
            settings: addressIntegrationSettings,
            addressAddMoreCalled: false,
            addressTriggerCalled: false,
            addressChangedEventPaused: false,
            pendingAddedAddressInputs: [],
            addressInputToString: function (addressInput) {
              addressInput = $(addressInput);
              var addressString = '';
              $.each(
                elements,
                function (index, property) {
                  if (addressInput.find('.' + property).length) {
                    if (addressInput.find('.' + property).val().trim().length) {
                      if (addressString.length > 0) {
                        addressString = addressString + ', ';
                      }
                      addressString = addressString + addressInput.find('.' + property).val().trim();
                    }
                  }
                }
              );

              if (!addressString) {
                return false;
              }

              if (addressInput.find('.country.form-select').length) {
                addressString = addressString + ', ' + addressInput.find('.country.form-select').val();
              }
              return addressString;
            },
            addressToCoordinates: function (address) {
              return $.getJSON(
                Drupal.url('geolocation/address/geocoder/geocode'),
                {
                  geocoder: this.settings.geocoder,
                  geocoder_settings: this.settings.settings,
                  field_name: sourceFieldName,
                  address: address
                }
              );
            },
            coordinatesToAddress: function (latitude, longitude) {
              return $.getJSON(
                Drupal.url('geolocation/address/geocoder/reverse'),
                {
                  geocoder: this.settings.geocoder,
                  geocoder_settings: this.settings.settings,
                  field_name: sourceFieldName,
                  latitude: latitude,
                  longitude: longitude
                }
              );
            },
            getAllAddressInputs: function () {
              return addressWidgetWrapper.find("[data-drupal-selector^='edit-'][data-drupal-selector*='" + this.settings.address_field.replace(/_/g, '-') + "'] [data-drupal-selector$='-address']");
            },
            addNewAddressInput: function () {
              if (this.addressAddMoreCalled) {
                return false;
              }

              if (this.addressTriggerCalled) {
                return false;
              }

              var button = addressWidgetWrapper.find("[data-drupal-selector^='edit-" + this.settings.address_field.replace(/_/g, '-') + "'] [data-drupal-selector$='-add-more']");
              if (button.length) {
                button.trigger("mousedown");
                this.addressAddMoreCalled = true;
              }
            },
            getAddressByDelta: function (delta) {
              delta = parseInt(delta) || 0;
              var inputs = this.getAllAddressInputs();
              if (inputs.length <= delta) {
                return null;
              }
              var input = inputs.eq(delta);
              if (input.length) {
                return input;
              }
              return null;
            },
            setAddress: function (address, delta) {
              var that = this;
              if (typeof delta === 'undefined') {
                delta = this.getNextDelta();
              }

              if (
                typeof delta === 'undefined'
                || delta === false
              ) {
                console.error(location, Drupal.t('Could not determine delta for new address input.'));
                return null;
              }

              var addressInput = this.getAddressByDelta(delta);
              if (addressInput) {
                if (addressInput.find('.country').val() === address.countryCode) {
                  widget.addressChangedEventPaused = true;
                  addressInput.find('.organization').val(address.organization);
                  addressInput.find('.address-line1').val(address.addressLine1);
                  addressInput.find('.address-line2').val(address.addressLine2);
                  addressInput.find('.locality').val(address.locality);
                  addressInput.find('.dependent-locality').val(address.dependentLocality);

                  var administrativeAreaInput = addressInput.find('.administrative-area');
                  if (administrativeAreaInput) {
                    if (administrativeAreaInput.prop('tagName') === 'INPUT') {
                      administrativeAreaInput.val(address.administrativeArea);
                    }
                    else if (administrativeAreaInput.prop('tagName') === 'SELECT') {
                      administrativeAreaInput.val(address.administrativeArea);
                    }
                  }
                  addressInput.find('.postal-code').val(address.postalCode);
                  widget.addressChangedEventPaused = false;
                  this.addressTriggerCalled = false;
                }
                else {
                  $.each(this.pendingAddedAddressInputs, function (index, item) {
                    if (item.delta === delta) {
                      that.pendingAddedAddressInputs.splice(index, 1);
                    }
                  });
                  this.pendingAddedAddressInputs.push({
                    delta: delta,
                    address: address
                  });

                  if (
                      this.addressAddMoreCalled
                      || this.addressTriggerCalled
                  ) {
                    return false;
                  }
                  widget.addressChangedEventPaused = true;
                  addressInput.find('.country').val(address.countryCode).trigger('change');
                  widget.addressChangedEventPaused = false;
                  this.addressTriggerCalled = true;
                }
              }
              else if (
                  address.countryCode
                  && address.countryCode.length > 0
              ) {
                $.each(this.pendingAddedAddressInputs, function (index, item) {
                  if (item.delta === delta) {
                    that.pendingAddedAddressInputs.splice(index, 1);
                  }
                });
                this.pendingAddedAddressInputs.push({
                  delta: delta,
                  address: address
                });
                this.addNewAddressInput();
              }
              else {

              }

              return delta;
            },
            removeAddress: function (delta) {
              var addressInput = this.getAddressByDelta(delta);
              if (addressInput) {
                widget.addressChangedEventPaused = true;
                addressInput.find('select, input:not([type="hidden"], [name$="[given_name]"], [name$="[family_name]"])').val('');
                widget.addressChangedEventPaused = false;
              }
            }
          });

          var table = $('table.field-multiple-table', addressWidgetWrapper);

          if (table.length) {
            var tableDrag = Drupal.tableDrag[table.attr('id')];

            if (tableDrag) {
              tableDrag.row.prototype.onSwap = function () {
                $.each(widget.getAllAddressInputs(), function (delta, address) {
                  widget.addressToCoordinates(widget.addressInputToString(address)).then(function (location) {
                    widget.locationAlteredCallback('address-changed', location, delta);
                  });
                });
              };
            }
          }

          if (
              addressIntegrationSettings.sync_mode === 'auto'
              && addressIntegrationSettings.direction !== 'one_way'
          ) {
            widget.addLocationAlteredCallback(function (location, delta, identifier) {
              if (identifier === 'address-changed') {
                return;
              }
              widget.removeAddress(delta);
              widget.coordinatesToAddress(location.lat, location.lng).then(function (address) {
                if (!address) {
                  widget.removeInput(delta);
                  widget.removeMarker(delta);
                  var addressWarning = Drupal.t('Address could not be geocoded, location won\'t be set.');
                  if (typeof Drupal.messages !== 'undefined') {
                    var messages = new Drupal.messages();
                    messages.add(addressWarning);
                  }
                  else {
                    alert(addressWarning);
                  }
                  return;
                }
                widget.setAddress(address, delta);
              });
            });
          }
          else {
            var pullButton = geolocationWidgetWrapper.find('button.address-button.address-button-pull');
            if (pullButton.length === 1) {
              pullButton.click(function (e) {
                e.preventDefault();
                widget.getAllAddressInputs().each(function (delta, addressInput) {
                  widget.removeInput(delta);
                  var address = widget.addressInputToString(addressInput);
                  widget.addressToCoordinates(address).then(function (location) {
                    if (Object.keys(location).length === 0) {
                      widget.removeMarker(delta);
                      return;
                    }
                    widget.locationAlteredCallback('address-changed', location, delta);
                  });
                });
              });
            }

            var pushButton = geolocationWidgetWrapper.find('button.address-button.address-button-push');
            if (pushButton.length === 1) {
              pushButton.click(function (e) {
                e.preventDefault();
                widget.getAllInputs().each(function (delta, input) {
                  var coordinates = widget.getCoordinatesByInput(input);
                  if (!coordinates) {
                    return;
                  }
                  widget.coordinatesToAddress(coordinates.lat, coordinates.lng).then(function (address) {
                    widget.setAddress(address, delta);
                  });
                });
              })
            }
          }
        }

        if (addressIntegrationSettings.sync_mode === 'auto') {
          $.each(widget.getAllAddressInputs(), function (delta, addressElement) {
            addressElement = $(addressElement);

            var elements = [
              'country',
              'organization',
              'address-line1',
              'address-line2',
              'locality',
              'administrative-area',
              'postal-code'
            ];

            var element = null;
            $.each(elements, function (index, className) {
              element = addressElement.find('.' + className);
              $(once('geolocation-address-listener', element)).change(function () {
                if (widget.addressChangedEventPaused) {
                  return;
                }

                var address = widget.getAddressByDelta(delta);
                var addressString = widget.addressInputToString(address);
                if (!addressString) {
                  widget.removeAddress(delta);
                  widget.removeInput(delta);
                  widget.removeMarker(delta);
                  return;
                }
                widget.addressToCoordinates(addressString).then(function (location) {
                  widget.locationAlteredCallback('address-changed', location, delta);
                });
              });
            });
          });
        }
      });
    }
  };

})(jQuery, Drupal);
