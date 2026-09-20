'use strict';

/**
 * THD Digital Publishing
 *
 * Shared JavaScript for the administration area.
 *
 * Responsibilities:
 * - Confirm destructive actions
 * - Prevent duplicate form submissions
 * - Show loading state during form submission
 * - Display selected file information
 * - Preview selected images when a preview target exists
 *
 * No framework dependency.
 */

(() => {
    /*
    |--------------------------------------------------------------------------
    | DOM helpers
    |--------------------------------------------------------------------------
    */

    const $ = (selector, root = document) => {
        return root.querySelector(selector);
    };

    const $$ = (selector, root = document) => {
        return Array.from(
            root.querySelectorAll(selector)
        );
    };


    /*
    |--------------------------------------------------------------------------
    | Confirm destructive actions
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | <form method="post" data-confirm="Bạn có chắc chắn muốn xóa?">
    |
    */

    const initConfirmations = () => {
        $$('[data-confirm]').forEach((element) => {
            element.addEventListener('submit', (event) => {
                const message = element.dataset.confirm;

                if (!message) {
                    return;
                }

                const confirmed = window.confirm(message);

                if (!confirmed) {
                    event.preventDefault();
                }
            });

            /*
             * Also support buttons/links carrying data-confirm.
             */
            if (
                element.tagName !== 'FORM'
                && element.tagName !== 'INPUT'
                && element.tagName !== 'TEXTAREA'
                && element.tagName !== 'SELECT'
            ) {
                element.addEventListener('click', (event) => {
                    const message = element.dataset.confirm;

                    if (!message) {
                        return;
                    }

                    const confirmed = window.confirm(message);

                    if (!confirmed) {
                        event.preventDefault();
                    }
                });
            }
        });
    };


    /*
    |--------------------------------------------------------------------------
    | Form submission state
    |--------------------------------------------------------------------------
    */

    const setSubmitLoadingState = (form) => {
        const submitButtons = $$(
            'button[type="submit"], input[type="submit"]',
            form
        );

        submitButtons.forEach((button) => {
            if (button.disabled) {
                return;
            }

            if (!button.dataset.originalText) {
                button.dataset.originalText =
                    button.textContent;
            }

            button.disabled = true;
            button.setAttribute(
                'aria-disabled',
                'true'
            );

            button.classList.add('is-loading');

            if (button.tagName === 'BUTTON') {
                button.textContent = 'Đang xử lý...';
            } else {
                button.value = 'Đang xử lý...';
            }
        });
    };


    const initFormSubmissionProtection = () => {
        $$('form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                /*
                 * The submit event occurs after native browser
                 * constraint validation has passed.
                 */
                if (form.dataset.submitting === '1') {
                    event.preventDefault();
                    return;
                }

                form.dataset.submitting = '1';

                setSubmitLoadingState(form);
            });
        });
    };


    /*
    |--------------------------------------------------------------------------
    | File information
    |--------------------------------------------------------------------------
    */

    const formatFileSize = (bytes) => {
        if (!Number.isFinite(bytes) || bytes < 0) {
            return '';
        }

        if (bytes < 1024) {
            return `${bytes} B`;
        }

        if (bytes < 1024 * 1024) {
            return `${(bytes / 1024).toFixed(1)} KB`;
        }

        if (bytes < 1024 * 1024 * 1024) {
            return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
        }

        return `${(
            bytes /
            (1024 * 1024 * 1024)
        ).toFixed(1)} GB`;
    };


    const getFileInputPreviewTarget = (input) => {
        const targetSelector =
            input.dataset.filePreview;

        if (!targetSelector) {
            return null;
        }

        /*
         * Allow:
         *
         * data-file-preview="#preview"
         */
        try {
            return $(targetSelector);
        } catch {
            return null;
        }
    };


    const getFileInfoTarget = (input) => {
        const targetSelector =
            input.dataset.fileInfo;

        if (!targetSelector) {
            return null;
        }

        try {
            return $(targetSelector);
        } catch {
            return null;
        }
    };


    /*
    |--------------------------------------------------------------------------
    | Image preview
    |--------------------------------------------------------------------------
    */

    const clearImagePreview = (previewTarget) => {
        if (!previewTarget) {
            return;
        }

        if (previewTarget.tagName === 'IMG') {
            previewTarget.removeAttribute('src');
            previewTarget.hidden = true;
            return;
        }

        previewTarget.textContent = '';
        previewTarget.hidden = true;
    };


    const showImagePreview = (
        input,
        previewTarget,
        file
    ) => {
        if (!previewTarget || !file) {
            return;
        }

        if (!file.type.startsWith('image/')) {
            clearImagePreview(previewTarget);
            return;
        }

        const objectUrl = URL.createObjectURL(file);

        if (previewTarget.tagName === 'IMG') {
            previewTarget.src = objectUrl;
            previewTarget.hidden = false;

            /*
             * Release the object URL after the image has loaded.
             */
            previewTarget.addEventListener(
                'load',
                () => {
                    URL.revokeObjectURL(objectUrl);
                },
                {
                    once: true,
                }
            );

            return;
        }

        previewTarget.textContent = file.name;
        previewTarget.hidden = false;

        URL.revokeObjectURL(objectUrl);
    };


    /*
    |--------------------------------------------------------------------------
    | File input handling
    |--------------------------------------------------------------------------
    */

    const updateFileInput = (input) => {
        const files = input.files;

        const previewTarget =
            getFileInputPreviewTarget(input);

        const infoTarget =
            getFileInfoTarget(input);

        if (!files || files.length === 0) {
            clearImagePreview(previewTarget);

            if (infoTarget) {
                infoTarget.textContent = '';
                infoTarget.hidden = true;
            }

            return;
        }

        const file = files[0];

        if (infoTarget) {
            const size = formatFileSize(file.size);

            infoTarget.textContent =
                size !== ''
                    ? `${file.name} — ${size}`
                    : file.name;

            infoTarget.hidden = false;
        }

        showImagePreview(
            input,
            previewTarget,
            file
        );
    };


    const initFileInputs = () => {
        $$('input[type="file"]').forEach((input) => {
            input.addEventListener('change', () => {
                updateFileInput(input);
            });
        });
    };


    /*
    |--------------------------------------------------------------------------
    | Keyboard accessibility
    |--------------------------------------------------------------------------
    */

    const initEscapeHandling = () => {
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            /*
             * Do not interfere with browser-native dialogs
             * or form controls.
             */
        });
    };


    /*
    |--------------------------------------------------------------------------
    | Initialization
    |--------------------------------------------------------------------------
    */

    const init = () => {
        initConfirmations();
        initFormSubmissionProtection();
        initFileInputs();
        initEscapeHandling();
    };


    if (
        document.readyState === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            init,
            {
                once: true,
            }
        );
    } else {
        init();
    }
})();