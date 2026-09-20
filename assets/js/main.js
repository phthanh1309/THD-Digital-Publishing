'use strict';

(() => {
    /**
     * THD Digital Publishing
     * Shared public-site JavaScript.
     *
     * Responsibilities:
     * - Mobile navigation
     * - Generic copy/share interactions
     * - Small progressive-enhancement behaviors
     *
     * Reader-specific logic belongs in reader.js.
     */

    const THD = {
        selectors: {
            menuToggle: '[data-menu-toggle]',
            menu: '[data-menu]',
            copyUrl: '[data-copy-url]',
            shareUrl: '[data-share-url]',
        },

        init() {
            this.initMobileMenu();
            this.initCopyButtons();
            this.initShareButtons();
        },

        initMobileMenu() {
            const toggle = document.querySelector(
                this.selectors.menuToggle
            );

            const menu = document.querySelector(
                this.selectors.menu
            );

            if (!toggle || !menu) {
                return;
            }

            const closeMenu = () => {
                toggle.setAttribute('aria-expanded', 'false');
                menu.classList.remove('is-open');
            };

            const openMenu = () => {
                toggle.setAttribute('aria-expanded', 'true');
                menu.classList.add('is-open');
            };

            toggle.addEventListener('click', () => {
                const isOpen =
                    toggle.getAttribute('aria-expanded') === 'true';

                if (isOpen) {
                    closeMenu();
                } else {
                    openMenu();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeMenu();
                }
            });

            document.addEventListener('click', (event) => {
                if (
                    !menu.classList.contains('is-open')
                    || menu.contains(event.target)
                    || toggle.contains(event.target)
                ) {
                    return;
                }

                closeMenu();
            });
        },

        initCopyButtons() {
            const buttons = document.querySelectorAll(
                this.selectors.copyUrl
            );

            if (buttons.length === 0) {
                return;
            }

            buttons.forEach((button) => {
                button.addEventListener('click', async () => {
                    const value =
                        button.dataset.copyUrl
                        || window.location.href;

                    const success = await this.copyText(value);

                    if (success) {
                        this.showTemporaryState(
                            button,
                            'Đã sao chép',
                            'Sao chép liên kết'
                        );
                    }
                });
            });
        },

        initShareButtons() {
            const buttons = document.querySelectorAll(
                this.selectors.shareUrl
            );

            if (buttons.length === 0) {
                return;
            }

            buttons.forEach((button) => {
                button.addEventListener('click', async () => {
                    const url =
                        button.dataset.shareUrl
                        || window.location.href;

                    const title =
                        button.dataset.shareTitle
                        || document.title;

                    const text =
                        button.dataset.shareText
                        || title;

                    await this.share({
                        url,
                        title,
                        text,
                        button,
                    });
                });
            });
        },

        async share({ url, title, text, button }) {
            if (
                navigator.share
                && typeof navigator.share === 'function'
            ) {
                try {
                    await navigator.share({
                        title,
                        text,
                        url,
                    });

                    return true;
                } catch (error) {
                    /*
                     * AbortError means the user closed/cancelled
                     * the native share dialog.
                     *
                     * This is not an application error.
                     */
                    if (error?.name === 'AbortError') {
                        return false;
                    }
                }
            }

            const copied = await this.copyText(url);

            if (copied && button) {
                this.showTemporaryState(
                    button,
                    'Đã sao chép liên kết',
                    'Chia sẻ'
                );
            }

            return copied;
        },

        async copyText(value) {
            if (!value) {
                return false;
            }

            if (
                navigator.clipboard
                && typeof navigator.clipboard.writeText === 'function'
            ) {
                try {
                    await navigator.clipboard.writeText(value);
                    return true;
                } catch (error) {
                    /*
                     * Fall through to the legacy-compatible method.
                     */
                }
            }

            return this.copyTextFallback(value);
        },

        copyTextFallback(value) {
            const textarea = document.createElement('textarea');

            textarea.value = value;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            textarea.style.top = '0';

            document.body.appendChild(textarea);

            textarea.focus();
            textarea.select();

            let copied = false;

            try {
                copied = document.execCommand('copy');
            } catch (error) {
                copied = false;
            }

            document.body.removeChild(textarea);

            return copied;
        },

        showTemporaryState(
            element,
            temporaryText,
            originalText
        ) {
            if (!element) {
                return;
            }

            const originalAriaLabel =
                element.getAttribute('aria-label');

            const originalTitle =
                element.getAttribute('title');

            if (
                'textContent' in element
                && element.children.length === 0
            ) {
                element.textContent = temporaryText;
            }

            if (originalAriaLabel !== null) {
                element.setAttribute(
                    'aria-label',
                    temporaryText
                );
            }

            if (originalTitle !== null) {
                element.setAttribute(
                    'title',
                    temporaryText
                );
            }

            window.setTimeout(() => {
                if (
                    'textContent' in element
                    && element.children.length === 0
                ) {
                    element.textContent = originalText;
                }

                if (originalAriaLabel !== null) {
                    element.setAttribute(
                        'aria-label',
                        originalAriaLabel
                    );
                }

                if (originalTitle !== null) {
                    element.setAttribute(
                        'title',
                        originalTitle
                    );
                }
            }, 1800);
        },
    };

    document.addEventListener('DOMContentLoaded', () => {
        THD.init();
    });

    window.THD = THD;
})();