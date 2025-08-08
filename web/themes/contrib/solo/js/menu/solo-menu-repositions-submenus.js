/**
 * @file
 * Solo
 *
 * Filename:     solo-menu-repositions-submenus.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, drupalSettings, once) => {
  'use strict';
  Drupal.behaviors.soloMenuFix = {
    attach: function(context) {
      const breakpoint = Drupal.solo.getBreakpointNumber('mn');
      const windowWidth = window.innerWidth;
      // Quit if the screen is smaller than the breakpoint
      if (windowWidth < breakpoint) {
        return;
      }
      // Function 1: Get full window width
      function getWindowWidth() {
        return window.innerWidth;
      }
      // Function 2: Get full width of the `li`
      function getLiWidth(li) {
        return li.offsetWidth;
      }
      // Function 3: Get X and Y position of the `li`
      function getLiPosition(li) {
        return li.getBoundingClientRect();
      }
      // Function 4: Get full width of the opening submenu `ul`
      function getSubmenuWidth(submenu) {
        return submenu.offsetWidth;
      }
      // Function 5: Get X and Y position of the submenu `ul`
      function getSubmenuPosition(submenu) {
        return submenu.getBoundingClientRect();
      }
      // Function 6: Get space from `li` start to the left window edge
      function getSpaceLeft(liRect) {
        return liRect.left;
      }
      // Function 7: Get space from `li` end to the right window edge
      function getSpaceRight(liRect, windowWidth) {
        return windowWidth - liRect.right;
      }
      // Function to reposition second-level submenus
      function adjustSecondLevelSubmenu(li) {
        const windowWidth = getWindowWidth();
        if (windowWidth < breakpoint) return;
        const submenu = li.querySelector(':scope > .sub__menu');
        if (!submenu) return;
        submenu.style.visibility = 'hidden';
        const liRect = getLiPosition(li);
        const submenuWidth = getSubmenuWidth(submenu);
        const spaceLeft = getSpaceLeft(liRect);
        const spaceRight = getSpaceRight(liRect, windowWidth);
        if (submenuWidth + 30 > spaceRight) {
          submenu.style.left = 'auto';
          submenu.style.right = '0';
        } else if (submenuWidth + 30 > spaceLeft) {
          submenu.style.right = 'auto';
          submenu.style.left = '0';
        } else {
          submenu.style.left = '';
          submenu.style.right = '';
        }
        submenu.style.visibility = 'visible';
      }
      // Function to reposition third-level submenus
      function adjustThirdLevelSubmenu(li) {
        const windowWidth = getWindowWidth();
        if (windowWidth < breakpoint) return;
        const submenu = li.querySelector(':scope > .sub__menu');
        if (!submenu) return;
        const parentLi = li.closest('li.has-sub__menu');
        if (!parentLi) return;
        submenu.style.visibility = 'hidden';
        const parentRect = getLiPosition(parentLi);
        const submenuWidth = getSubmenuWidth(submenu);
        const spaceLeft = getSpaceLeft(parentRect);
        const spaceRight = getSpaceRight(parentRect, windowWidth);
        // NEW: Dynamically get 4th-level submenu width (if present)
        const fourthLevelLi = submenu.querySelector('li.has-sub__menu');
        let fourthLevelWidth = 0;
        if (fourthLevelLi) {
          const fourthSubmenu = fourthLevelLi.querySelector(
            ':scope > .sub__menu');
          if (fourthSubmenu) {
            const originalDisplay = fourthSubmenu.style.display;
            fourthSubmenu.style.visibility = 'hidden';
            fourthSubmenu.style.display = 'block';
            fourthLevelWidth = getSubmenuWidth(fourthSubmenu);
            // Restore only if originally hidden inline
            fourthSubmenu.style.display = originalDisplay;
            fourthSubmenu.style.visibility = '';
          }
        }
        const totalNeededWidth = submenuWidth + fourthLevelWidth + 30;
        if (totalNeededWidth > spaceRight && spaceLeft > spaceRight) {
          submenu.style.left = 'auto';
          submenu.style.right = '100%';
          submenu.dataset.flipped = 'left';
          li.classList.add('submenu-flipped-left');
          li.classList.remove('submenu-flipped-right');
        } else if (submenuWidth + 30 > spaceLeft) {
          submenu.style.right = 'auto';
          submenu.style.left = '100%';
          submenu.dataset.flipped = 'right';
          li.classList.add('submenu-flipped-right');
          li.classList.remove('submenu-flipped-left');
        } else {
          submenu.style.left = '';
          submenu.style.right = '';
        }
        submenu.style.visibility = 'visible';
      }

      function adjustFourthLevelSubmenu(li) {
        const windowWidth = getWindowWidth();
        if (windowWidth < breakpoint) return;
        const submenu = li.querySelector(':scope > .sub__menu');
        if (!submenu) return;
        const parentLi = li.closest('ul.sub__menu').closest(
          'li.has-sub__menu');
        if (!parentLi) return;
        const parentSubmenu = parentLi.querySelector(
          ':scope > .sub__menu');
        if (!parentSubmenu) return;
        submenu.style.visibility = 'hidden';
        // Determine if parent (3rd level) is flipped left
        const isParentFlippedLeft = parentSubmenu.dataset.flipped ===
          'left';
        if (isParentFlippedLeft) {
          submenu.style.left = 'auto';
          submenu.style.right = '100%';
        } else {
          submenu.style.right = 'auto';
          submenu.style.left = '100%';
        }
        submenu.style.visibility = 'visible';
      }
      // Function to apply submenu fix for both hover and click
      function applySubmenuFix(menuSelector, adjustFunction) {
        once('soloMenuFix', document.querySelectorAll(menuSelector,
          context)).forEach((li) => {
          ['mouseenter', 'click'].forEach(eventType => {
            li.addEventListener(eventType, function() {
              adjustFunction(this);
            });
          });
        });
      }
      // Apply fixes to second, third, and fourth-level submenus
      applySubmenuFix(
        '.primary-menu .navigation__primary > li.has-sub__menu',
        adjustSecondLevelSubmenu);
      applySubmenuFix(
        '.primary-menu .navigation__primary > li.has-sub__menu > ul > li.has-sub__menu',
        adjustThirdLevelSubmenu);
      applySubmenuFix(
        '.primary-menu .navigation__primary > li.has-sub__menu > ul > li.has-sub__menu > ul > li.has-sub__menu',
        adjustFourthLevelSubmenu);
      // Function to reapply fixes on window resize
      function handleResize() {
        if (window.innerWidth >= breakpoint) {
          applySubmenuFix(
            '.primary-menu .navigation__primary > li.has-sub__menu',
            adjustSecondLevelSubmenu);
          applySubmenuFix(
            '.primary-menu .navigation__primary > li.has-sub__menu > ul > li.has-sub__menu',
            adjustThirdLevelSubmenu);
          applySubmenuFix(
            '.primary-menu .navigation__primary > li.has-sub__menu > ul > li.has-sub__menu > ul > li.has-sub__menu',
            adjustFourthLevelSubmenu);
        }
      }
      // Listen for window resize and reapply menu fix
      window.addEventListener('resize', handleResize);
    }
  };
})(Drupal, drupalSettings, once);
