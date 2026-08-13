(function () {
    'use strict';

    function initPostList() {
        var modalElement = document.getElementById('postViewModal');
        if (!modalElement || !window.bootstrap || !window.bootstrap.Modal) {
            return;
        }

        var modalContent = modalElement.querySelector('[data-post-modal-content]');
        var modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
        var activeRequest = null;
        var sourceCard = null;

        function fadeTargetHighlight() {
            var targetId = window.location.hash.match(/^#post-(\d+)$/);
            if (!targetId) {
                return;
            }

            var targetCard = document.getElementById('post-' + targetId[1]);
            if (!targetCard) {
                return;
            }

            window.setTimeout(function () {
                if (window.location.hash !== targetId[0]) {
                    return;
                }

                targetCard.classList.add('post-card--target-fading');
                window.history.replaceState(
                    window.history.state,
                    '',
                    window.location.pathname + window.location.search
                );
            }, 3000);
        }

        function loadingContent() {
            return '<div class="modal-body text-center text-muted">Загрузка…</div>';
        }

        function errorContent(fallbackUrl) {
            var body = document.createElement('div');
            var link = document.createElement('a');

            body.className = 'modal-body';
            body.append('Не удалось загрузить заметку. ');
            link.href = fallbackUrl;
            link.textContent = 'Открыть отдельную страницу';
            body.append(link, '.');

            modalContent.replaceChildren(body);
        }

        function positionModal() {
            if (!sourceCard) {
                return;
            }

            var cardRect = sourceCard.getBoundingClientRect();
            var minimumTop = 8;
            var maximumTop = Math.max(minimumTop, window.innerHeight * 0.55);
            var modalTop = Math.max(minimumTop, Math.min(cardRect.top, maximumTop));
            modalElement.style.setProperty('--post-modal-top', Math.round(modalTop) + 'px');
        }

        document.addEventListener('click', function (event) {
            var link = event.target.closest('[data-post-modal-url]');
            if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                return;
            }

            event.preventDefault();
            if (activeRequest) {
                activeRequest.abort();
            }

            sourceCard = link.closest('.post-card');
            positionModal();
            activeRequest = new AbortController();
            modalContent.innerHTML = loadingContent();
            modal.show();

            fetch(link.dataset.postModalUrl, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: activeRequest.signal
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Не удалось загрузить заметку.');
                }
                return response.text();
            }).then(function (html) {
                modalContent.innerHTML = html;
                window.requestAnimationFrame(positionModal);
            }).catch(function (error) {
                if (error.name === 'AbortError') {
                    return;
                }
                errorContent(link.href);
            }).finally(function () {
                activeRequest = null;
            });
        });

        modalElement.addEventListener('hidden.bs.modal', function () {
            if (activeRequest) {
                activeRequest.abort();
                activeRequest = null;
            }
            modalContent.innerHTML = loadingContent();
            sourceCard = null;
            modalElement.style.removeProperty('--post-modal-top');
        });

        window.addEventListener('resize', function () {
            if (modalElement.classList.contains('show')) {
                positionModal();
            }
        });

        fadeTargetHighlight();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPostList, {once: true});
    } else {
        initPostList();
    }
}());
