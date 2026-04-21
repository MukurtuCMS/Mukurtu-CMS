/**
 * (c) Wong Hoi Sing Edison <hswong3i@pantarei-design.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

(function (factory, window) {
  if (typeof define === "function" && define.amd) {
    define(["leaflet"], factory);
  } else if (typeof exports === "object") {
    module.exports = factory(require("leaflet"));
  }

  if (typeof window !== "undefined" && window.L) {
    window.L.Control.ResetView = factory(L);
  }
}(function (L) {
  ResetView = L.Control.extend({
    options: {
      position: "topleft",
      title: "Reset view",
      latlng: null,
      zoom: null,
    },

    onAdd: function(map) {
      this._map = map;

      this._container = L.DomUtil.create("div", "leaflet-control-resetview leaflet-bar leaflet-control");
      this._link = L.DomUtil.create("a", "leaflet-bar-part leaflet-bar-part-single", this._container);
      this._link.title = this.options.title;
      this._link.href = "#";
      this._link.setAttribute("role", "button");
      this._icon = L.DomUtil.create("span", "leaflet-control-resetview-icon", this._link);

      L.DomEvent.on(this._link, "click", this._resetView, this);

      return this._container;
    },

    onRemove: function(map) {
      L.DomEvent.off(this._link, "click", this._resetView, this);
    },

    _resetView: function(e) {
      // Added by itamair according to this drustack/Leaflet.ResetView issue:
      // https://github.com/drustack/Leaflet.ResetView/issues/1
      if (e) { L.DomEvent.preventDefault(e) }
      this._map.setView(this.options.latlng, this.options.zoom);
    },
  });

  L.control.resetView = function(options) {
    return new ResetView(options);
  };

  return ResetView;
}, window));
