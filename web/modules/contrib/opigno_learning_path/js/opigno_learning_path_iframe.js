/* eslint-disable func-names */

(function (Drupal, $, once) {
  Drupal.behaviors.opignoLearningPathIframe = {
    attach: function (context, settings) {
      var self = this;

      $(once('document', context === document ? 'html' : context)).each(function() {
        $(context).ajaxComplete(function () {
          if (self.inIframe()) {
            parent.iframeFormValues = drupalSettings.formValues;
          }
        });
      })
    },

    inIframe: function () {
      try {
        return window.self !== window.top;
      }
      catch (e) {
        return true;
      }
    },
  };
}(jQuery, Drupal));
