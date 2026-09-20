'use strict';

(() => {

    const config = window.THD_READER;

    if (!config) {
        return;
    }

    const Reader = {

        pdf: null,

        state: {
            /*
             * Lấy trang ban đầu từ URL/PHP:
             * ?page=8 -> currentPage = 8
             */
            currentPage: Math.max(
                1,
                Number(config.currentPage) || 1
            ),

            pageCount: Number(
                config.pageCount
            ) || 0,

            zoom: 1,

            rendering: false,

            initialized: false,

            fullscreen: false
        },

        elements: {},


        /*
        |--------------------------------------------------------------------------
        | INIT
        |--------------------------------------------------------------------------
        */

        async init() {

            this.cacheElements();

            this.bindEvents();

            this.updateUI();

            this.setStatus(
                'Đang tải PDF...'
            );

            try {

                await this.loadPDF();

                this.state.initialized = true;

                this.hideLoading();

                /*
                 * Render đúng trang được truyền từ PHP/URL.
                 */
                await this.renderSpread();

                this.updateUI();

                this.setStatus(
                    'Sẵn sàng'
                );

            } catch (error) {

                console.error(
                    'Reader error:',
                    error
                );

                this.showError(
                    error.message
                    || 'Không thể tải PDF.'
                );
            }
        },


        /*
        |--------------------------------------------------------------------------
        | CACHE
        |--------------------------------------------------------------------------
        */

        cacheElements() {

            this.elements = {

                app:
                    document.querySelector(
                        '[data-reader-app]'
                    ),

                stage:
                    document.querySelector(
                        '[data-reader-stage]'
                    ),

                wrapper:
                    document.querySelector(
                        '[data-flipbook-wrapper]'
                    ),

                flipbook:
                    document.querySelector(
                        '[data-flipbook]'
                    ),

                leftPage:
                    document.querySelector(
                        '[data-page-slot="left"]'
                    ),

                rightPage:
                    document.querySelector(
                        '[data-page-slot="right"]'
                    ),

                leftCanvas:
                    document.querySelector(
                        '[data-page-slot="left"] canvas'
                    ),

                rightCanvas:
                    document.querySelector(
                        '[data-page-slot="right"] canvas'
                    ),

                loading:
                    document.querySelector(
                        '[data-reader-loading]'
                    ),

                error:
                    document.querySelector(
                        '[data-reader-error]'
                    ),

                errorMessage:
                    document.querySelector(
                        '[data-reader-error-message]'
                    ),

                retry:
                    document.querySelector(
                        '[data-reader-retry]'
                    ),

                status:
                    document.querySelector(
                        '[data-reader-status]'
                    ),

                pageInput:
                    document.querySelector(
                        '[data-page-input]'
                    ),

                pageCount:
                    document.querySelector(
                        '[data-page-count]'
                    ),

                first:
                    document.querySelector(
                        '[data-reader-first]'
                    ),

                previous:
                    document.querySelector(
                        '[data-reader-prev]'
                    ),

                next:
                    document.querySelector(
                        '[data-reader-next]'
                    ),

                last:
                    document.querySelector(
                        '[data-reader-last]'
                    ),

                zoomOut:
                    document.querySelector(
                        '[data-zoom-out]'
                    ),

                zoomReset:
                    document.querySelector(
                        '[data-zoom-reset]'
                    ),

                zoomIn:
                    document.querySelector(
                        '[data-zoom-in]'
                    ),

                fitScreen:
                    document.querySelector(
                        '[data-fit-screen]'
                    ),

                fullscreen:
                    document.querySelector(
                        '[data-toggle-fullscreen]'
                    ),

                share:
                    document.querySelector(
                        '[data-reader-share]'
                    ),

                flipLeft:
                    document.querySelector(
                        '[data-flip-left]'
                    ),

                flipRight:
                    document.querySelector(
                        '[data-flip-right]'
                    )
            };
        },


        /*
        |--------------------------------------------------------------------------
        | EVENTS
        |--------------------------------------------------------------------------
        */

        bindEvents() {

            this.elements.first?.addEventListener(
                'click',
                () => this.goFirst()
            );

            this.elements.previous?.addEventListener(
                'click',
                () => this.goPrevious()
            );

            this.elements.next?.addEventListener(
                'click',
                () => this.goNext()
            );

            this.elements.last?.addEventListener(
                'click',
                () => this.goLast()
            );

            this.elements.flipLeft?.addEventListener(
                'click',
                () => this.goPrevious()
            );

            this.elements.flipRight?.addEventListener(
                'click',
                () => this.goNext()
            );

            this.elements.zoomOut?.addEventListener(
                'click',
                () => this.zoomOut()
            );

            this.elements.zoomIn?.addEventListener(
                'click',
                () => this.zoomIn()
            );

            this.elements.zoomReset?.addEventListener(
                'click',
                () => this.resetZoom()
            );

            this.elements.fitScreen?.addEventListener(
                'click',
                () => this.fitScreen()
            );

            this.elements.fullscreen?.addEventListener(
                'click',
                () => this.toggleFullscreen()
            );

            this.elements.retry?.addEventListener(
                'click',
                () => this.retry()
            );

            this.elements.share?.addEventListener(
                'click',
                () => this.share()
            );


            /*
             * Nhập số trang.
             */

            this.elements.pageInput?.addEventListener(
                'keydown',
                event => {

                    if (
                        event.key === 'Enter'
                    ) {

                        event.preventDefault();

                        this.goToInputPage();
                    }
                }
            );


            /*
             * Bàn phím.
             */

            document.addEventListener(
                'keydown',
                event => {

                    if (
                        event.target instanceof HTMLInputElement
                    ) {
                        return;
                    }

                    if (
                        event.key === 'ArrowLeft'
                    ) {

                        event.preventDefault();

                        this.goPrevious();
                    }

                    if (
                        event.key === 'ArrowRight'
                    ) {

                        event.preventDefault();

                        this.goNext();
                    }

                    if (
                        event.key === '+'
                        || event.key === '='
                    ) {

                        event.preventDefault();

                        this.zoomIn();
                    }

                    if (
                        event.key === '-'
                    ) {

                        event.preventDefault();

                        this.zoomOut();
                    }

                    if (
                        event.key === 'Escape'
                        && this.state.fullscreen
                    ) {

                        this.exitFullscreen();
                    }
                }
            );


            /*
             * Resize.
             */

            let resizeTimer = null;

            window.addEventListener(
                'resize',
                () => {

                    clearTimeout(
                        resizeTimer
                    );

                    resizeTimer = setTimeout(
                        () => {

                            if (
                                this.state.initialized
                            ) {

                                this.renderSpread();
                            }

                        },
                        150
                    );
                }
            );


            /*
             * Fullscreen change.
             */

            document.addEventListener(
                'fullscreenchange',
                () => {

                    this.state.fullscreen =
                        !!document.fullscreenElement;

                    this.updateFullscreenButton();
                }
            );


            /*
             * Touch swipe.
             */

            let touchStartX = 0;

            this.elements.wrapper?.addEventListener(
                'touchstart',
                event => {

                    if (
                        event.touches.length !== 1
                    ) {
                        return;
                    }

                    touchStartX =
                        event.touches[0].clientX;
                },
                {
                    passive: true
                }
            );


            this.elements.wrapper?.addEventListener(
                'touchend',
                event => {

                    if (
                        !touchStartX
                    ) {
                        return;
                    }

                    const endX =
                        event.changedTouches[0].clientX;

                    const difference =
                        endX - touchStartX;

                    touchStartX = 0;

                    if (
                        Math.abs(difference) < 50
                    ) {
                        return;
                    }

                    if (
                        difference > 0
                    ) {

                        this.goPrevious();

                    } else {

                        this.goNext();
                    }
                },
                {
                    passive: true
                }
            );
        },


        /*
        |--------------------------------------------------------------------------
        | PDF.JS
        |--------------------------------------------------------------------------
        */

        async loadPDF() {

            if (
                !config.pdfUrl
            ) {

                throw new Error(
                    'Không tìm thấy đường dẫn PDF.'
                );
            }


            /*
             * Import PDF.js trực tiếp từ CDN.
             */

            const pdfjsLib =
                await import(
                    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs'
                );


            pdfjsLib.GlobalWorkerOptions.workerSrc =
                'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.worker.min.mjs';


            this.pdf =
                await pdfjsLib.getDocument(
                    {
                        url: config.pdfUrl,

                        /*
                         * Cho phép PDF.js dùng HTTP Range.
                         */
                        rangeChunkSize:
                            1024 * 1024
                    }
                ).promise;


            this.state.pageCount =
                this.pdf.numPages;


            if (
                this.elements.pageCount
            ) {

                this.elements.pageCount.textContent =
                    String(
                        this.state.pageCount
                    );
            }


            /*
             * Đảm bảo currentPage hợp lệ
             * sau khi biết tổng số trang.
             */

            this.state.currentPage =
                Math.max(
                    1,
                    Math.min(
                        this.state.currentPage,
                        this.state.pageCount
                    )
                );
        },


        /*
        |--------------------------------------------------------------------------
        | RENDER SPREAD
        |--------------------------------------------------------------------------
        */

        async renderSpread(
            animation = false,
            direction = null
        ) {

            if (
                !this.pdf
                || this.state.rendering
            ) {

                return;
            }


            this.state.rendering = true;


            try {

                const current =
                    this.normalizePage(
                        this.state.currentPage
                    );


                /*
                 * Đồng bộ lại currentPage sau normalize.
                 */

                this.state.currentPage =
                    current;


                const isMobile =
                    window.innerWidth < 768;


                /*
                 * Mobile:
                 * chỉ hiển thị 1 trang.
                 *
                 * Desktop:
                 * hiển thị spread 2 trang.
                 */

                let leftPageNumber;
                let rightPageNumber;


                if (isMobile) {

                    leftPageNumber =
                        current;

                    rightPageNumber =
                        null;

                } else {

                    /*
                     * Trang 1 đứng một mình.
                     */

                    if (current === 1) {

                        leftPageNumber = 1;

                        rightPageNumber =
                            this.state.pageCount >= 2
                                ? 2
                                : null;

                    } else {

                        leftPageNumber =
                            current % 2 === 0
                                ? current
                                : current - 1;

                        rightPageNumber =
                            leftPageNumber + 1;

                        if (
                            rightPageNumber >
                            this.state.pageCount
                        ) {

                            rightPageNumber =
                                null;
                        }
                    }
                }


                /*
                 * Animation.
                 */

                if (
                    animation
                    && direction
                ) {

                    await this.animateFlip(
                        direction
                    );
                }


                await Promise.all(
                    [
                        this.renderPageToCanvas(
                            leftPageNumber,
                            this.elements.leftCanvas
                        ),

                        this.renderPageToCanvas(
                            rightPageNumber,
                            this.elements.rightCanvas
                        )
                    ]
                );


                /*
                 * Mobile chỉ dùng left.
                 */

                if (isMobile) {

                    this.elements.leftPage
                        ?.classList.add(
                            'single-page'
                        );

                    this.elements.rightPage
                        ?.classList.add(
                            'page-hidden'
                        );

                } else {

                    this.elements.leftPage
                        ?.classList.remove(
                            'single-page'
                        );

                    this.elements.rightPage
                        ?.classList.remove(
                            'page-hidden'
                        );
                }


                this.updatePageInput();

                this.preload();

            } finally {

                this.state.rendering = false;
            }
        },


        /*
        |--------------------------------------------------------------------------
        | RENDER PAGE
        |--------------------------------------------------------------------------
        */

        async renderPageToCanvas(
            pageNumber,
            canvas
        ) {

            if (!canvas) {
                return;
            }


            const context =
                canvas.getContext(
                    '2d'
                );


            if (!pageNumber) {

                canvas.width = 1;

                canvas.height = 1;

                canvas.style.visibility =
                    'hidden';

                return;
            }


            canvas.style.visibility =
                'visible';


            const page =
                await this.pdf.getPage(
                    pageNumber
                );


            const wrapper =
                this.elements.wrapper;


            const availableWidth =
                Math.max(
                    300,
                    (wrapper?.clientWidth || 1000)
                    / (
                        window.innerWidth < 768
                            ? 1
                            : 2
                    )
                );


            const availableHeight =
                Math.max(
                    400,
                    (wrapper?.clientHeight || 700)
                );


            const baseViewport =
                page.getViewport(
                    {
                        scale: 1
                    }
                );


            const scaleByWidth =
                availableWidth
                / baseViewport.width;


            const scaleByHeight =
                availableHeight
                / baseViewport.height;


            const fitScale =
                Math.min(
                    scaleByWidth,
                    scaleByHeight
                );


            const scale =
                fitScale
                * this.state.zoom;


            const viewport =
                page.getViewport(
                    {
                        scale
                    }
                );


            const devicePixelRatio =
                window.devicePixelRatio || 1;


            canvas.width =
                Math.floor(
                    viewport.width
                    * devicePixelRatio
                );


            canvas.height =
                Math.floor(
                    viewport.height
                    * devicePixelRatio
                );


            canvas.style.width =
                `${viewport.width}px`;


            canvas.style.height =
                `${viewport.height}px`;


            context.setTransform(
                devicePixelRatio,
                0,
                0,
                devicePixelRatio,
                0,
                0
            );


            context.clearRect(
                0,
                0,
                viewport.width,
                viewport.height
            );


            await page.render(
                {
                    canvasContext: context,

                    viewport: viewport
                }
            ).promise;
        },


        /*
        |--------------------------------------------------------------------------
        | FLIP ANIMATION
        |--------------------------------------------------------------------------
        */

        async animateFlip(
            direction
        ) {

            const flipbook =
                this.elements.flipbook;


            if (!flipbook) {
                return;
            }


            flipbook.classList.remove(
                'flip-next',
                'flip-previous'
            );


            /*
             * Force reflow.
             */

            void flipbook.offsetWidth;


            if (
                direction === 'next'
            ) {

                flipbook.classList.add(
                    'flip-next'
                );

            } else {

                flipbook.classList.add(
                    'flip-previous'
                );
            }


            await new Promise(
                resolve =>
                    setTimeout(
                        resolve,
                        350
                    )
            );


            flipbook.classList.remove(
                'flip-next',
                'flip-previous'
            );
        },


        /*
        |--------------------------------------------------------------------------
        | NAVIGATION
        |--------------------------------------------------------------------------
        */

        async goPrevious() {

            if (
                this.state.currentPage <= 1
                || this.state.rendering
            ) {

                return;
            }


            const isMobile =
                window.innerWidth < 768;


            if (isMobile) {

                this.state.currentPage--;

            } else {

                /*
                 * Desktop:
                 * lùi 2 trang.
                 */

                if (
                    this.state.currentPage === 2
                ) {

                    this.state.currentPage = 1;

                } else {

                    this.state.currentPage =
                        Math.max(
                            1,
                            this.state.currentPage - 2
                        );
                }
            }


            await this.renderSpread(
                true,
                'previous'
            );

            this.updateURL();
        },


        async goNext() {

            if (
                this.state.currentPage >=
                this.state.pageCount
                || this.state.rendering
            ) {

                return;
            }


            const isMobile =
                window.innerWidth < 768;


            if (isMobile) {

                this.state.currentPage++;

            } else {

                /*
                 * Desktop:
                 * tiến 2 trang.
                 */

                if (
                    this.state.currentPage === 1
                ) {

                    this.state.currentPage = 2;

                } else {

                    this.state.currentPage =
                        Math.min(
                            this.state.pageCount,
                            this.state.currentPage + 2
                        );
                }
            }


            await this.renderSpread(
                true,
                'next'
            );

            this.updateURL();
        },


        async goFirst() {

            if (
                this.state.rendering
            ) {

                return;
            }


            this.state.currentPage = 1;

            await this.renderSpread();

            this.updateURL();
        },


        async goLast() {

            if (
                this.state.rendering
            ) {

                return;
            }


            this.state.currentPage =
                this.state.pageCount;

            await this.renderSpread();

            this.updateURL();
        },


        async goToPage(
            page
        ) {

            page =
                parseInt(
                    page,
                    10
                );


            if (
                Number.isNaN(page)
            ) {

                return;
            }


            page =
                Math.max(
                    1,
                    Math.min(
                        page,
                        this.state.pageCount
                    )
                );


            if (
                page ===
                this.state.currentPage
            ) {

                return;
            }


            this.state.currentPage =
                page;


            await this.renderSpread();

            this.updateURL();
        },


        goToInputPage() {

            const value =
                this.elements.pageInput?.value;


            this.goToPage(
                value
            );
        },


        /*
        |--------------------------------------------------------------------------
        | ZOOM
        |--------------------------------------------------------------------------
        */

        async zoomIn() {

            this.state.zoom =
                Math.min(
                    2.5,
                    this.state.zoom + 0.1
                );

            await this.renderSpread();

            this.updateZoomUI();
        },


        async zoomOut() {

            this.state.zoom =
                Math.max(
                    0.5,
                    this.state.zoom - 0.1
                );

            await this.renderSpread();

            this.updateZoomUI();
        },


        async resetZoom() {

            this.state.zoom = 1;

            await this.renderSpread();

            this.updateZoomUI();
        },


        async fitScreen() {

            this.state.zoom = 1;

            await this.renderSpread();

            this.updateZoomUI();
        },


        /*
        |--------------------------------------------------------------------------
        | PRELOAD
        |--------------------------------------------------------------------------
        */

        preload() {

            if (!this.pdf) {
                return;
            }


            const pages = [
                this.state.currentPage - 2,
                this.state.currentPage - 1,
                this.state.currentPage + 1,
                this.state.currentPage + 2
            ];


            pages.forEach(
                pageNumber => {

                    if (
                        pageNumber < 1
                        || pageNumber >
                        this.state.pageCount
                    ) {

                        return;
                    }


                    this.pdf.getPage(
                        pageNumber
                    ).catch(
                        () => {}
                    );
                }
            );
        },


        /*
        |--------------------------------------------------------------------------
        | UI
        |--------------------------------------------------------------------------
        */

        updateUI() {

            this.updateNavigation();

            this.updatePageInput();

            this.updateZoomUI();

            this.updateFullscreenButton();
        },


        updateNavigation() {

            const page =
                this.state.currentPage;

            const count =
                this.state.pageCount;


            this.setDisabled(
                this.elements.first,
                page <= 1
            );

            this.setDisabled(
                this.elements.previous,
                page <= 1
            );

            this.setDisabled(
                this.elements.next,
                page >= count
            );

            this.setDisabled(
                this.elements.last,
                page >= count
            );
        },


        updatePageInput() {

            if (
                this.elements.pageInput
            ) {

                this.elements.pageInput.value =
                    String(
                        this.state.currentPage
                    );
            }


            if (
                this.elements.pageCount
            ) {

                this.elements.pageCount.textContent =
                    String(
                        this.state.pageCount
                    );
            }
        },


        updateZoomUI() {

            if (
                this.elements.zoomReset
            ) {

                this.elements.zoomReset.textContent =
                    `${Math.round(
                        this.state.zoom * 100
                    )}%`;
            }
        },


        updateFullscreenButton() {

            if (
                !this.elements.fullscreen
            ) {

                return;
            }


            this.elements.fullscreen.textContent =
                this.state.fullscreen
                    ? '✕'
                    : '⛶';
        },


        setDisabled(
            element,
            disabled
        ) {

            if (!element) {
                return;
            }


            element.disabled =
                disabled;

            element.classList.toggle(
                'is-disabled',
                disabled
            );
        },


        setStatus(
            text
        ) {

            if (
                this.elements.status
            ) {

                this.elements.status.textContent =
                    text;
            }
        },


        /*
        |--------------------------------------------------------------------------
        | LOADING / ERROR
        |--------------------------------------------------------------------------
        */

        hideLoading() {

            if (
                this.elements.loading
            ) {

                this.elements.loading.hidden =
                    true;
            }
        },


        showLoading() {

            if (
                this.elements.loading
            ) {

                this.elements.loading.hidden =
                    false;
            }

            if (
                this.elements.error
            ) {

                this.elements.error.hidden =
                    true;
            }
        },


        showError(
            message
        ) {

            this.hideLoading();


            if (
                this.elements.error
            ) {

                this.elements.error.hidden =
                    false;
            }


            if (
                this.elements.errorMessage
            ) {

                this.elements.errorMessage.textContent =
                    message;
            }
        },


        async retry() {

            this.showLoading();

            this.setStatus(
                'Đang thử lại...'
            );


            try {

                await this.loadPDF();

                this.hideLoading();

                this.elements.error.hidden =
                    true;

                await this.renderSpread();

                this.setStatus(
                    'Sẵn sàng'
                );

            } catch (error) {

                this.showError(
                    error.message
                    || 'Không thể tải PDF.'
                );
            }
        },


        /*
        |--------------------------------------------------------------------------
        | FULLSCREEN
        |--------------------------------------------------------------------------
        */

        async toggleFullscreen() {

            if (
                document.fullscreenElement
            ) {

                await this.exitFullscreen();

                return;
            }


            const element =
                this.elements.app;


            if (
                !element
                || !element.requestFullscreen
            ) {

                return;
            }


            try {

                await element.requestFullscreen();

            } catch (error) {

                console.error(
                    error
                );
            }
        },


        async exitFullscreen() {

            if (
                document.exitFullscreen
            ) {

                await document.exitFullscreen();
            }
        },


        /*
        |--------------------------------------------------------------------------
        | SHARE
        |--------------------------------------------------------------------------
        */

        async share() {

            const url =
                window.location.href;


            try {

                if (
                    navigator.share
                ) {

                    await navigator.share(
                        {
                            title:
                                config.title,

                            url:
                                url
                        }
                    );

                    return;
                }


                await navigator.clipboard.writeText(
                    url
                );


                this.setStatus(
                    'Đã sao chép liên kết.'
                );

            } catch (error) {

                console.error(
                    error
                );
            }
        },


        /*
        |--------------------------------------------------------------------------
        | URL
        |--------------------------------------------------------------------------
        */

        updateURL() {

            const url =
                new URL(
                    window.location.href
                );


            url.searchParams.set(
                'slug',
                config.slug
            );


            url.searchParams.set(
                'page',
                String(
                    this.state.currentPage
                )
            );


            window.history.replaceState(
                {},
                '',
                url.toString()
            );
        },


        /*
        |--------------------------------------------------------------------------
        | HELPERS
        |--------------------------------------------------------------------------
        */

        normalizePage(
            page
        ) {

            page =
                parseInt(
                    page,
                    10
                );


            if (
                Number.isNaN(page)
            ) {

                return 1;
            }


            return Math.max(
                1,
                Math.min(
                    page,
                    this.state.pageCount || 1
                )
            );
        }

    };


    /*
    |--------------------------------------------------------------------------
    | START
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'DOMContentLoaded',
        () => {

            Reader.init();

        }
    );

})();