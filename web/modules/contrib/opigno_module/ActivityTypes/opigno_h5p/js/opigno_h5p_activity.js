/**
 * @file
 * Activity customization.
 */
(function ($, Drupal) {

  "use strict";

  /**
   * Provide the summary information for the block settings vertical tabs.
   */
  Drupal.behaviors.OpignoH5PActivity = {
    attach: function (context) {
      $(document).ready(function () {
        let startBtn = $('.h5p-container .qs-startbutton');
        if (startBtn.length) {
          startBtn.on('click', function () {
            $(this).parents('.h5p-container').find('.questionset').removeClass('hidden');
          });
        }
      });
    }
  };
})(jQuery, Drupal);
