/**
 * @file
 * Adds toggle functionality to a fixed search block.
 *
 * Filename: solo-fixed-search-block.js
 * Website: https://www.flashwebcenter.com
 * Developer: Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, drupalSettings, once) => {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    // Function to handle focus events
    const handleFocus = (event) => {
      const element = event.target;
      const tagName = element.tagName.toLowerCase();
      const id = element.id ? `#${element.id}` : '';
      const className = element.className ? `.${element.className.split(' ').join('.')}` : '';
    };

    // Add event listener to all focusable elements
    const focusableElements = document.querySelectorAll('a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
    focusableElements.forEach((element) => {
      element.addEventListener('focus', handleFocus);
    });
  });

  const mainSideNav = document.getElementById('primary-sidebar-menu');
  const searchBlock = document.getElementById('fixed-search-block');
  const openSearchButtons = document.querySelectorAll('.search-button-open>button');
  const closeSearchButton = document.querySelector('.search-button-close>button');

  // Function to add a click event listener to a specified element
  const searchBlockCloseOpen = (elements, callback) => {
    elements.forEach(element => {
      if (element) {
        element.addEventListener('click', callback);
      }
    });
  };

  // Function to toggle the aria-expanded attribute for open/close buttons
  // and aria-hidden for search block
  const setAriaAttributes = (isOpen) => {
    openSearchButtons.forEach(btn => btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false'));
    closeSearchButton?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    searchBlock.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
  };

  // Function to set tabindex on buttons and inputs inside the search block
  const setTabindexOnElements = (isOpen) => {
    const elements = searchBlock.querySelectorAll('button, input');
    elements.forEach(element => {
      if (isOpen) {
        element.removeAttribute('tabindex');
      } else {
        element.setAttribute('tabindex', '-1');
      }
    });
  };

  // Function to focus the first input field in the search block
  const focusFirstInput = () => {
    const firstInput = searchBlock.querySelector('input');
    if (firstInput) {
      firstInput.focus();
    }
  };

  // Function to move focus to the close button when tabbing out of the last input
  const trapFocus = (event) => {
    const focusable = Array.from(searchBlock.querySelectorAll('button, input, [tabindex]:not([tabindex="-1"])'))
      .filter(el => !el.disabled && el.offsetParent !== null);

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.key === 'Tab') {
      if (event.shiftKey && event.target === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && event.target === last) {
        event.preventDefault();
        first.focus();
      }
    }
  };

  const searchBlockToggle = (isOpen) => {
    setAriaAttributes(isOpen);
    setTabindexOnElements(isOpen);

    if (isOpen) {
      searchBlock.classList.add('toggled');
      searchBlock.style.transition = 'none';
      searchBlock.style.height = 'auto';
      const height = searchBlock.clientHeight + 'px';
      searchBlock.style.height = '0px';
      searchBlock.offsetHeight; // force reflow
      searchBlock.style.transition = ''; // restore transition

      requestAnimationFrame(() => {
        searchBlock.style.height = height;
      });

      const onOpenEnd = (event) => {
        if (event.propertyName === 'height') {
          searchBlock.style.height = 'auto';
          searchBlock.removeEventListener('transitionend', onOpenEnd);
        }
      };
      searchBlock.addEventListener('transitionend', onOpenEnd);

      if (mainSideNav) {
        Drupal.solo.sideMenubarToggleNav(false);
      }

      focusFirstInput();

    } else {
      searchBlock.style.height = getComputedStyle(searchBlock).height;
      searchBlock.offsetHeight;
      searchBlock.style.height = '0px';

      const onCloseEnd = (event) => {
        if (event.propertyName === 'height') {
          searchBlock.classList.remove('toggled');
          searchBlock.removeEventListener('transitionend', onCloseEnd);
        }
      };
      searchBlock.addEventListener('transitionend', onCloseEnd);
    }
  };

  if (searchBlock) {
    Drupal.solo.searchBlockToggle = searchBlockToggle;

    // Attach these behaviors to the Drupal system
    Drupal.behaviors.soloFixedSearchBlock = {
      attach: function(context, settings) {
        // Close nav button found in page.html.twig in vertical menu region.
        searchBlockCloseOpen([closeSearchButton], () => searchBlockToggle(false));

        // Open nav button found in page.html.twig in header region.
        searchBlockCloseOpen(openSearchButtons, () => searchBlockToggle(true));

        // Add focus trap to the last element to redirect focus to the close button
        searchBlock.addEventListener('keydown', trapFocus);

        // Click anywhere outside the search block to close it.
        document.addEventListener('click', (event) => {
          if (searchBlock && searchBlock.classList.contains('toggled')) {
            // Check if the click is outside the search block
            if (!searchBlock.contains(event.target) && !event.target.closest('.search-button-open')) {
              searchBlockToggle(false);
            }
          }
        });

      }
    };
  }

})(Drupal, drupalSettings, once);
