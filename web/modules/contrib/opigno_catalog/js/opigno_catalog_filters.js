/**
 * @file
 * opigno_catalog_filters.js
 *
 * Detecting the "back forward" event and applying views filters.
 */

(function ($, Drupal) {
  // Detect back forward event.
  const entries = performance.getEntriesByType("navigation");
  entries.forEach((entry) => {
    if (entry.type === "back_forward") {
      setTimeout(function () {
        const $form = $('#views-exposed-form-opigno-training-catalog-training-catalogue');
        // Better exposed filters
        let $submit = $form.find('[data-bef-auto-submit-click]');
        // Default view filters
        if (!$submit.length) {
          $submit = $form.find('.apply-catalog-filters.button')
        }
        $submit.click();
      }, 100)
    }
  });

}(jQuery, Drupal));
