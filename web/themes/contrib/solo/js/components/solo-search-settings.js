/**
 * @file
 * Contains utility functions for Solo module.
 *
 * Filename: solo-search-settings.js
 * Website: https://www.flashwebcenter.com
 * Developer: Alaa Haddad https://www.alaahaddad.com.
 */

((Drupal, once) => {
  'use strict';

  Drupal.behaviors.soloSearchBlock = {
    attach: function (context) {
      // Fetch content types from drupalSettings.
      const contentTypes = drupalSettings?.solo?.searchContentTypes;
      // console.log(contentTypes);
      // Validate contentTypes: Ensure it's an object and not empty.
      if (contentTypes && Object.keys(contentTypes).length > 0) {
        // Select the search block form using `once` to avoid duplicate bindings.
        const searchBlockForms = once('solo-search-block', '.solo-search-block-form', context);

        searchBlockForms.forEach((searchBlockForm) => {
          searchBlockForm.addEventListener('submit', function (event) {
            event.preventDefault();

            // Get the search input value.
            const searchInput = searchBlockForm.querySelector('input[name="keys"]').value;

            // Initialize URL with the search term.
            let actionUrl = `/search/node?keys=${encodeURIComponent(searchInput)}`;

            // Add content types as filters.
            Object.entries(contentTypes).forEach(([key, value], index) => {
              const filterKey = `f[${index}]`;
              const filterValue = `type:${value}`;
              actionUrl += `&${encodeURIComponent(filterKey)}=${encodeURIComponent(filterValue)}`;
            });

            // Ensure 'advanced-form=1' is appended only once.
            if (!actionUrl.includes('advanced-form=1')) {
              actionUrl += `&advanced-form=1`;
            }

            // Redirect to the new URL.
            window.location.href = actionUrl;
          });
        });
      }
    },
  };
})(Drupal, once);

