/**
 * @file
 * Contains the functionality for user statistics block.
 */

(function ($) {

  /**
   * Refresh the user stats block.
   */
  Drupal.behaviors.refreshUserStatsBlock = {
    attach: function (context, settings) {
      var $select = $(once('select','.profile-info #filterRange', context));
      $select.on('change', function (e) {
        e.preventDefault();

        Drupal.ajax({
          type: 'POST',
          url: settings.dashboard.userStatsBlockUrl,
          async: false,
          submit: {
            'days': $select.val(),
            'uid': settings.dashboard.userId,
          },
        }).execute();
      });
    }
  };

} (jQuery));
