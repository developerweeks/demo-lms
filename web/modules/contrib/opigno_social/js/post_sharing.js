/**
 * @file
 * Contains the functionality for posts sharing.
 */

(function ($) {

  /**
   * Open the popup with the shareable post content.
   */
  Drupal.behaviors.sharePostContent = {
    attach: function (context, settings) {
      let $url = settings.opignoSocial ? settings.opignoSocial.shareContentUrl : undefined;
      if (typeof $url === 'undefined') {
        return;
      }

      // Call the popup opening when the user is clicking on the line in the
      // "sharable content" block.
      $(once('click', 'a[data-opigno-post-attachment-id]', context)).on('click', function (e) {
        e.preventDefault()

        Drupal.ajax({
          type: 'POST',
          url: $url,
          async: true,
          submit: {
            'type': $(this).attr('data-opigno-attachment-type'),
            'id': $(this).attr('data-opigno-post-attachment-id'),
            'entity_type': $(this).attr('data-opigno-attachment-entity-type'),
            'text': $('#create-post-textfield').val(),
            'bundle': settings.opignoSocial.postBundle ?? 'social',
          },
        }).execute();
      });
    }
  };

}(jQuery));
