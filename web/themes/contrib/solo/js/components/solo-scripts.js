/**
 * @file
 * Solo
 *
 * Filename:     solo-scripts.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, drupalSettings, once) => {
  'use strict';
  // Get current width
  const getCurrentWidth = () => window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;

  const checkRegionsWidth = () => {
    const regions = document.querySelectorAll('.region-inner, .copyright-inner, .footer-menu-inner');
    regions.forEach(region => {
      const regionWidth = region.getBoundingClientRect().width;

      // Remove all previous size classes to prevent class duplication
      region.classList.remove('region-xs', 'region-s', 'region-m', 'region-l', 'region-xl', 'region-xxl');

      // Assign new class based on region width
      if (regionWidth <= 320) {
        region.classList.add('region-xs'); // Extra Small Devices
      } else if (regionWidth > 320 && regionWidth <= 576) {
        region.classList.add('region-s'); // Small Devices
      } else if (regionWidth > 576 && regionWidth <= 768) {
        region.classList.add('region-m'); // Medium Devices
      } else if (regionWidth > 768 && regionWidth <= 992) {
        region.classList.add('region-l'); // Large Devices
      } else if (regionWidth > 992 && regionWidth <= 1200) {
        region.classList.add('region-xl'); // Extra Large Devices
      } else if (regionWidth > 1200) {
        region.classList.add('region-xxl'); // Extra Extra Large Devices
      }
    });
  };

  // Add/remove CSS classes according to screen changes.
  const mediaSize = () => {
    const currentWidth = getCurrentWidth();
    const bodyTag = document.body;

    // Remove all previous size classes to prevent class duplication
    bodyTag.classList.remove('small-screen', 'medium-screen', 'large-screen');

    if (currentWidth >= 992) {
      bodyTag.classList.add('large-screen');
    } else if (currentWidth >= 576 && currentWidth < 992) {
      bodyTag.classList.add('medium-screen');
    } else if (currentWidth < 576) {
      bodyTag.classList.add('small-screen');
    }

    checkRegionsWidth();
  };

  Drupal.behaviors.soloTheme = {
    attach: function(context, settings) {
      // Select all spans with the class 'file--mime-application-octet-stream'
      const fileSpans = context.querySelectorAll('.field--type-file span.file');
      fileSpans.forEach(span => {
        const link = span.querySelector('a');
        if (link && span.contains(link)) {
          const url = link.getAttribute('href');
          const urlParts = url.split('.');
          const fileExtension = urlParts[urlParts.length - 1]; // Get the last part as the file extension
          if (fileExtension) {
            span.classList.add(`file--${fileExtension}`); // Add file extension as a class to the span
          }
        }
      });

      // Ensure code only runs once per element
      const footerMenu = context.querySelector('#footer-menu');
      if (footerMenu) {
        const footerFormBg = window.getComputedStyle(footerMenu).backgroundColor;
        const footerFormTxt = window.getComputedStyle(footerMenu).color;
        let footerMenuForm = context.querySelector('#footer-menu form');

        if (footerMenuForm) {
          footerMenuForm.style.background = footerFormBg;
          footerMenuForm.style.color = footerFormTxt;
        }
      }

      // Remove attribute 'open' from details elements in theme settings
      const detailsElements = context.querySelectorAll('#system-theme-settings details');
      detailsElements.forEach(element => {
        element.removeAttribute('open');
      });

      // Filter <img> and <picture> elements inside <a> tags
      let clickableElements = context.querySelectorAll('a > img, a > picture');
      const filteredElements = Array.from(clickableElements).filter(el => {
        // Check if any parent up to the root has specific classes to exclude
        let ancestor = el.parentElement;
        while (ancestor && ancestor !== document.body) {
          if (ancestor.matches('.site-logo, .field--name-user-picture, .field--type-text-long, .field--type-text-with-summary')) {
            return false;
          }
          ancestor = ancestor.parentElement;
        }

        // Exclude elements with "icon" in their class or in their parent <a> element's class
        if (el.classList.contains('icon') || (el.parentElement && el.parentElement.classList.contains('icon'))) {
          return false;
        }

        return true;
      });

      // Apply class to the parent <a> tags
      filteredElements.forEach(el => {
        el.parentElement.classList.add('img--is-clickable');
      });

      // Handle broken images
      const handleBrokenImages = (context) => {
        const images = context.querySelectorAll('img');
        images.forEach(img => {
          img.onerror = function() {
            if (!this.classList.contains('broken-image')) {
              this.classList.add('broken-image');
              const placeholder = document.createElement('div');
              placeholder.className = 'img-placeholder';
              placeholder.innerHTML = 'Image not available';
              this.style.display = 'none';
              this.parentNode.insertBefore(placeholder, this.nextSibling);
            }
          };
          // Force recheck the image load status to trigger the error if the image is broken
          img.src = img.src;
        });
      };

        // Flag to track if a skip link was clicked
        let skipLinkClicked = false;

        // Function to handle skip links
        const handleSkipLinkClick = (skipLinkSelector, targetSelector) => {
          const skipLink = document.querySelector(skipLinkSelector);
          const targetElement = document.querySelector(targetSelector);

          if (skipLink && targetElement) {
            skipLink.addEventListener('click', (event) => {
              event.preventDefault();

              // Set flag to indicate a skip link was clicked
              skipLinkClicked = true;

              // Make the target element focusable temporarily
              targetElement.setAttribute('tabindex', '-1');
              targetElement.focus({ preventScroll: true });
              window.scrollTo({
                top: targetElement.offsetTop,
                behavior: 'smooth'
              });

              setTimeout(() => {
                targetElement.removeAttribute('tabindex');
              }, 500);

              // Remove the fragment identifier from the URL without reloading the page
              history.replaceState(null, '', window.location.pathname);
            });
          }
        };

        // Handle skip links for various sections
        handleSkipLinkClick('.skip-link[href="#header-content"]', '#header-content');
        handleSkipLinkClick('.skip-link[href="#main-navigation-content"]', '#main-navigation-content');
        handleSkipLinkClick('.skip-link[href="#main-content"]', '#main-content');
        handleSkipLinkClick('.skip-link[href="#footer-content"]', '#footer-content');

        // Prevent automatic focus if URL has a fragment and was triggered by a skip link
        if (window.location.hash && skipLinkClicked) {
          const targetElement = document.querySelector(window.location.hash);

          if (targetElement) {
            window.scrollTo({
              top: targetElement.offsetTop,
              behavior: 'smooth'
            });
            targetElement.setAttribute('tabindex', '-1');
            targetElement.focus({ preventScroll: true });
            setTimeout(() => {
              targetElement.removeAttribute('tabindex');
            }, 500);
          }
        }

      // Call the function to handle broken images
      handleBrokenImages(context);

      // Initial media size check and on resize event
      mediaSize();
      let resizeTimeout;
      window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
          mediaSize();
        }, 200);
      });
    }
  };

})(Drupal, drupalSettings, once);
