/**
 * @file
 * Solo
 *
 * Filename:     solo-menu-mobile.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, drupalSettings, once) => {
  'use strict';
  let currentWidth;
  let currentLayout = Drupal.solo.getLayout();
  let previousLayout = currentLayout;
  const brNum = Drupal.solo.getBreakpointNumber('mn');
  const getCurrentWidth = () => window.innerWidth || document.documentElement
    .clientWidth || document.body.clientWidth;
  // Function to update tabindex of first-level menu items
  const updateFirstLevelTabindex = (menuElement, tabindexValue) => {
    const firstLevelItems = menuElement.querySelectorAll(
      'ul.navigation__responsive > li.nav__menubar-item > a, ul.navigation__responsive > li.nav__menubar-item > button'
      );
    firstLevelItems.forEach(item => {
      item.setAttribute('tabindex', tabindexValue);
    });
  };
  const updateAriaHidden = (menuElement, hiddenValue) => {
    menuElement.setAttribute('aria-hidden', hiddenValue);
  };
  const openMobileMenu = navTagId => {
    const navigationMenubarClass = Drupal.solo.getNavigationMenubarClass(
      navTagId);
    const subMenuClasses = Drupal.solo.getSubMenuClasses(navTagId);
    subMenuClasses?.forEach((subMenu) => {
      Drupal.solo.hideSubMenus(subMenu);
      Drupal.solo.revertIcons(navTagId);
    });
    Drupal.solo.slideDown(navigationMenubarClass);
    const menuElement = document.getElementById(navTagId);
    if (menuElement) {
      updateAriaHidden(menuElement, 'false');
      updateFirstLevelTabindex(menuElement, '0');
    }
  };
  const closeMobileMenu = navTagId => {
    const navigationMenubarClass = Drupal.solo.getNavigationMenubarClass(
      navTagId);
    const subMenuClasses = Drupal.solo.getSubMenuClasses(navTagId);
    subMenuClasses?.forEach((subMenu) => {
      Drupal.solo.hideSubMenus(subMenu);
      Drupal.solo.revertIcons(navTagId);
    });
    Drupal.solo.slideUp(navigationMenubarClass, 400);
    const menuElement = document.getElementById(navTagId);
    if (menuElement) {
      updateAriaHidden(menuElement, 'true');
      updateFirstLevelTabindex(menuElement, '-1');
    }
  };
  const getMobileNavType = (hamburgerIcon) => {
    const hamburgerIconChild = hamburgerIcon;
    const navTagId = hamburgerIcon.closest('nav').id;
    const menuElement = document.getElementById(navTagId);
    return [hamburgerIconChild, navTagId, menuElement];
  };
  const hamburgerIconIsClicked = (hamburgerIcon) => {
    const [hamburgerIconChild, navTagId] = getMobileNavType(hamburgerIcon);
    if (!hamburgerIcon.classList.contains('toggled')) {
      hamburgerIconChild.setAttribute('aria-expanded', 'true');
      hamburgerIcon.classList.add('toggled');
      openMobileMenu(navTagId);
    } else {
      hamburgerIconChild.setAttribute('aria-expanded', 'false');
      hamburgerIcon.classList.remove('toggled');
      closeMobileMenu(navTagId);
    }
  };
  const addAriaControlToButton = (hamburgerIcon) => {
    const [hamburgerIconChild, navTagId] = getMobileNavType(hamburgerIcon);
    const ariaControl = document.querySelector(
      `#${navTagId} .navigation__responsive`)?.getAttribute('id');
    if (currentWidth <= brNum) {
      hamburgerIconChild.setAttribute('aria-controls', ariaControl);
    } else {
      hamburgerIconChild.removeAttribute('aria-controls');
    }
  };
  const updateHamburgerTabindex = (hamburgerIcons) => {
    currentWidth = getCurrentWidth();
    hamburgerIcons.forEach(button => {
      button.setAttribute('tabindex', currentWidth <= brNum ? '0' :
        '-1');
    });
  };
  const updateTabindexForLargeScreens = () => {
    if (currentWidth > brNum) {
      const menuElement = document.querySelector('.navigation__responsive');
      if (menuElement) {
        updateFirstLevelTabindex(menuElement, '0');
        updateAriaHidden(menuElement, 'false');
      }
    }
  };
  const closeOnResize = (hamburgerIcon) => {
    const [hamburgerIconChild, navTagId] = getMobileNavType(hamburgerIcon);
    hamburgerIconChild.setAttribute('aria-expanded', 'false');
    hamburgerIcon.classList.remove('toggled');
    closeMobileMenu(navTagId);
  };
  const resetMenusOnResize = (hamburgerIcons) => {
    hamburgerIcons?.forEach((hamburgerIcon) => {
      closeOnResize(hamburgerIcon);
    });
  };

  function processHamburgerIcons(hamburgerIcons) {
    hamburgerIcons.forEach((hamburgerIcon) => {
      addAriaControlToButton(hamburgerIcon);
    });
  }

  function initHamburgerMenu(hamburgerIcons) {
    hamburgerIcons.forEach((hamburgerIcon) => {
      hamburgerIcon.addEventListener('click', () => {
        Drupal.solo.clickedHandler(() => {
          hamburgerIconIsClicked(hamburgerIcon);
        });
      });
    });
    updateHamburgerTabindex(hamburgerIcons);
    updateTabindexForLargeScreens();
    processHamburgerIcons(hamburgerIcons);
    window.addEventListener('resize', () => {
      currentWidth = getCurrentWidth();
      const isSmallScreen = currentWidth <= brNum;
      currentLayout = Drupal.solo.getLayout();
      if (isSmallScreen && previousLayout !== currentLayout) {
        resetMenusOnResize(hamburgerIcons);
        previousLayout = currentLayout;
      }
      processHamburgerIcons(hamburgerIcons);
      updateHamburgerTabindex(hamburgerIcons);
      updateTabindexForLargeScreens();
    });
  }
  Drupal.behaviors.mobileMenu = {
    attach: function(context) {
      // Try once via Drupal context
      const hamburgerIcons = once('soloHamburgerInit',
        '.mobile-nav button', context);
      if (hamburgerIcons.length > 0) {
        initHamburgerMenu(hamburgerIcons);
      } else {
        // Retry after full page load in case the menu was injected late (e.g. Admin Toolbar)
        window.addEventListener('load', () => {
          const fallbackIcons = once('soloHamburgerLateInit',
            '.mobile-nav button');
          if (fallbackIcons.length > 0) {
            initHamburgerMenu(fallbackIcons);
          }
        });
      }
    }
  };
})(Drupal, drupalSettings, once);
