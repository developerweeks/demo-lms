(function ($, Drupal) {
  Drupal.behaviors.opignoCommerce = {
    attach: function (context, settings) {
      $(once('removeLabelClass', '.form-item-payment-method-billing-information-address-0-address-address-line2 label', context))
          .removeClass("visually-hidden");
      $(once('removeLabelClass', '.form-item-payment-information-add-payment-method-billing-information-address-0-address-address-line2 label', context))
          .removeClass("visually-hidden");
      $(once('removeLabelClass', '.form-item-payment-information-billing-information-address-0-address-address-line2 label', context))
          .removeClass("visually-hidden");
    }
  };
}(jQuery, Drupal));
