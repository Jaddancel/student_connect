/*!
* Start Bootstrap - Agency v7.0.4 (https://startbootstrap.com/theme/agency)
* Copyright 2013-2021 Start Bootstrap
* Licensed under MIT (https://github.com/StartBootstrap/startbootstrap-agency/blob/master/LICENSE)
*/
//
// Scripts
//

window.addEventListener('DOMContentLoaded', event => {
    const bootstrap = window.bootstrap;

    // Background slideshow
    var startBackgroundSlideshow = function () {
        const masthead = document.body.querySelector('header.masthead');
        if (!masthead) return;

        const backgroundImages = [
            '/images/landing/BG1.jpg',
            '/images/landing/BG2.jpg',
            '/images/landing/BG3.jpg',
            '/images/landing/BG4.jpg',
            '/images/landing/BG5.jpg',
            '/images/landing/BG6.jpg',
            '/images/landing/BG7.jpg',
            '/images/landing/BG8.jpg',
        ];

        let currentIndex = 0;
        setInterval(function () {
            currentIndex = (currentIndex + 1) % backgroundImages.length;
            masthead.style.backgroundImage = 'linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url("' + backgroundImages[currentIndex] + '")';
        }, 5000);
    };

    startBackgroundSlideshow();

    // Navbar shrink function
    var navbarShrink = function () {
        const navbarCollapsible = document.body.querySelector('#mainNav');
        if (!navbarCollapsible) {
            return;
        }
        if (window.scrollY === 0) {
            navbarCollapsible.classList.remove('navbar-shrink')
        } else {
            navbarCollapsible.classList.add('navbar-shrink')
        }

    };

    // Shrink the navbar 
    navbarShrink();

    // Shrink the navbar when page is scrolled
    document.addEventListener('scroll', navbarShrink);

    // Activate Bootstrap scrollspy on the main nav element
    const mainNav = document.body.querySelector('#mainNav');
    if (mainNav && bootstrap?.ScrollSpy) {
        new bootstrap.ScrollSpy(document.body, {
            target: '#mainNav',
            offset: 74,
        });
    };

    // Collapse responsive navbar when toggler is visible
    const navbarToggler = document.body.querySelector('.navbar-toggler');
    const responsiveNavItems = [].slice.call(
        document.querySelectorAll('#navbarResponsive .nav-link')
    );
    responsiveNavItems.map(function (responsiveNavItem) {
        responsiveNavItem.addEventListener('click', () => {
            if (window.getComputedStyle(navbarToggler).display !== 'none') {
                navbarToggler.click();
            }
        });
    });

});
