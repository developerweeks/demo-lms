/**
 * @file
 * Contains the functionality for posts feed.
 */

(function ($) {

  /**
   * Ajax request to check if new posts were created.
   */
  Drupal.behaviors.checkNewPosts = {
    attach: function (context) {
      let $link = $('#opigno-new-posts-link', context);
      let $url = $link.attr('data-opigno-social-check-posts-url');

      if (typeof $url === 'undefined') {
        return;
      }

      let intervalId = null;
      // Send the ajax request every minute to check if new posts were created.
      intervalId = setInterval(function () {
        let $wrapper = $('.btn-new-post__wrapper', context);
        if ($wrapper.hasClass('hidden')) {
          $.ajax({
            type: 'POST',
            url: $url,
            dataType: "json",
            async: true,
            success: function (data, xhr, textStatus) {
              if (data.newPosts === true) {
                $wrapper.removeClass('hidden');
              }
            },
            complete: function (xhr, textStatus) {
              if (xhr.status != 200 || textStatus == 'parsererror') {
                $wrapper.addClass('hidden');
                clearInterval(intervalId);
              }
            }
          })
        }
      }, 60000);
    }
  }

  /**
   * Read more/show less link functionality.
   */
  Drupal.behaviors.readMore = {
    attach: function (context, settings) {
      $('.opigno-read-more', context).on('click', function (e) {
        e.preventDefault();
        let $summary = $(this).closest('.summary');
        let $text = $summary.next('.full-text');
        $summary.hide();
        $text.slideDown(500);
      })

      $('.opigno-show-less', context).on('click', function (e) {
        e.preventDefault();
        let $text = $(this).closest('.full-text');
        let $summary = $text.prev('.summary');
        $text.slideUp(500);
        $summary.slideDown(500);
      })
    }
  };

}(jQuery));
