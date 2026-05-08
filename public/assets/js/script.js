(() => {
    const button = document.querySelector('[data-random-movie]');
    const card = document.querySelector('[data-reveal-card]');
    const titles = document.querySelectorAll('.playful-title');

    const fitTitles = () => {
        titles.forEach((title) => {
            title.style.fontSize = '';
            title.style.setProperty('--title-scale', '1');

            const availableWidth = Math.max(240, title.clientWidth - 72);
            let size = parseFloat(window.getComputedStyle(title).fontSize);
            let scale = Math.min(1, availableWidth / title.scrollWidth);

            if (scale >= 0.54) {
                title.style.setProperty('--title-scale', String(scale));
                return;
            }

            title.style.setProperty('--title-scale', '0.54');
            while (title.scrollWidth * 0.54 > availableWidth && size > 34) {
                size -= 2;
                title.style.fontSize = `${size}px`;
            }

            scale = Math.min(1, availableWidth / title.scrollWidth);
            title.style.setProperty('--title-scale', String(Math.max(0.54, scale)));
        });
    };

    fitTitles();
    window.addEventListener('resize', fitTitles);

    button?.addEventListener('click', () => {
        document.body.classList.add('is-spinning');
        button.classList.add('is-pulling');
        button.disabled = true;
        button.querySelector('span').textContent = 'Spinning...';

        const form = document.createElement('form');
        const token = document.createElement('input');
        const excludedRatingKeys = document.createElement('input');

        form.method = 'post';
        form.action = '/pick';
        token.type = 'hidden';
        token.name = '_csrf_token';
        token.value = button.dataset.csrfToken || '';
        excludedRatingKeys.type = 'hidden';
        excludedRatingKeys.name = 'exclude_rating_keys';
        excludedRatingKeys.value = button.dataset.excludedRatingKeys || '';

        form.append(token, excludedRatingKeys);
        document.body.append(form);
        form.submit();
    });
})();
