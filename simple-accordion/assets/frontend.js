(function () {
    'use strict';

    function closeItem(item) {
        var button = item.querySelector('.simple-accordion__button');
        var panel = item.querySelector('.simple-accordion__panel');
        var icon = item.querySelector('.simple-accordion__icon');

        if (!button || !panel) {
            return;
        }

        item.classList.remove('is-open');
        button.setAttribute('aria-expanded', 'false');
        panel.hidden = true;

        if (icon) {
            icon.textContent = '+';
        }
    }

    function openItem(item) {
        var accordion = item.closest('.simple-accordion');
        var button = item.querySelector('.simple-accordion__button');
        var panel = item.querySelector('.simple-accordion__panel');
        var icon = item.querySelector('.simple-accordion__icon');

        if (!button || !panel) {
            return;
        }

        if (accordion) {
            accordion.querySelectorAll('.simple-accordion__item.is-open').forEach(function (openItemElement) {
                if (openItemElement !== item) {
                    closeItem(openItemElement);
                }
            });
        }

        item.classList.add('is-open');
        button.setAttribute('aria-expanded', 'true');
        panel.hidden = false;

        if (icon) {
            icon.textContent = '−';
        }
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.simple-accordion__button');

        if (!button) {
            return;
        }

        var item = button.closest('.simple-accordion__item');

        if (!item) {
            return;
        }

        if (item.classList.contains('is-open')) {
            closeItem(item);
        } else {
            openItem(item);
        }
    });
}());
