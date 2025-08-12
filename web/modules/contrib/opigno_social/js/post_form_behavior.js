/**
 * @file
 * Contains the post form behaviors.
 */

(function ($) {

  /**
   * Implement interaction between form elements.
   */
  Drupal.behaviors.postFormInteraction = {
    attach: function (context) {
      let $textField = $('.opigno-post-text-field');
      let extraSubmitClass = 'post-form-submit-active';

      $textField.on('input propertychange', function() {
        let $form = $(this).closest('form');
        let $submit = $form.find('.post-form-submit');

        if ($(this).val().length !== 0) {
          $(this).removeClass('error');
          $form.find('.error-msg').remove();
          $submit.addClass(extraSubmitClass);
        }
        else {
          $submit.removeClass(extraSubmitClass);
        }
      });
    }
  }

} (jQuery));
