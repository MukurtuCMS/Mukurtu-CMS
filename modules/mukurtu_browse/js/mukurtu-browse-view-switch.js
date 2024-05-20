// This carries over the search parameters in the url 
// when browse is switched from grid/list to map.
// @TODO: make sure this also works in reverse 
//(carry over search terms in url from map to list/grid.)
// (function ($, Drupal) {
//   Drupal.behaviors.mukurtu_browse_view_switch = {
//     attach: function (context, settings) {
//       $('#mukurtu-browse-map', context).once('mukurtu-browse-view-switch').each(function () {
//         $('#mukurtu-browse-map').on('click', function () {
//           this.href = this.href + window.location.search;
//         });
//       });
//     }
//   };
// })(jQuery, Drupal);

((Drupal, once) => {
  "use strict";
   let mapLink;

   function switchToMap(event) {
    // console.log("you've switched to map!!!!!!!!");
    this.href = this.href + window.location.search;
   }

  function init() {
    console.log("you made it to INITTTTT");

    mapLink = document.getElementById("mukurtu-browse-map");
    mapLink.addEventListener("click", switchToMap);
  }

  Drupal.behaviors.mukurtuMapBrowseSwitch = {
    attach(context) {
      once("mapView", "#mukurtu-browse-map", context).forEach(init);
    },
  };

})(Drupal, once);