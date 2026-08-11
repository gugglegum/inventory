(function(window, document) {
    'use strict';

    /*
     * API contract:
     * - create session: POST repoId/context -> token + upload_url;
     * - upload: multipart file/last_modified/position -> id, thumbnail_url,
     *   preview_url (or open_url), delete_url;
     * - removing a temporary card: DELETE delete_url.
     * The parent form submits only the token, immutable revision and ordered
     * manifest written to the three data-photo-editor-* hidden fields.
     */

    var editors = [];
    var activeEditor = null;
    var pasteListenerRegistered = false;

    function arrayFrom(value) {
        return Array.prototype.slice.call(value || []);
    }

    function jsonResponse(response) {
        return response.text().then(function(text) {
            var data = {};

            if (text !== '') {
                try {
                    data = JSON.parse(text);
                } catch (error) {
                    data = {};
                }
            }

            if (!response.ok) {
                throw new Error(responseError(data, response.statusText));
            }

            return data;
        });
    }

    function firstValidationError(errors) {
        var keys;
        var index;
        var message;

        if (typeof errors === 'string') {
            return errors;
        }

        if (!errors || typeof errors !== 'object') {
            return '';
        }

        if (typeof errors.message === 'string' && errors.message !== '') {
            return errors.message;
        }

        keys = Object.keys(errors);
        for (index = 0; index < keys.length; index += 1) {
            message = firstValidationError(errors[keys[index]]);
            if (message !== '') {
                return message;
            }
        }

        return '';
    }

    function responseError(data, fallback) {
        return firstValidationError(data.errors)
            || String(data.message || data.error || '')
            || firstValidationError(data)
            || String(fallback || 'Не удалось выполнить запрос.');
    }

    function csrf() {
        var param = document.querySelector('meta[name="csrf-param"]');
        var token = document.querySelector('meta[name="csrf-token"]');

        return {
            param: param ? param.getAttribute('content') : '',
            token: token ? token.getAttribute('content') : ''
        };
    }

    function requestJson(url, method, data) {
        var csrfData = csrf();
        var body = null;
        var controller = typeof window.AbortController === 'function' ? new window.AbortController() : null;
        var timedOut = false;
        var timeoutId = null;
        var headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };

        if (csrfData.token !== '') {
            headers['X-CSRF-Token'] = csrfData.token;
        }

        if (data) {
            body = new FormData();
            Object.keys(data).forEach(function(key) {
                body.append(key, String(data[key]));
            });
            if (csrfData.param !== '' && csrfData.token !== '') {
                body.append(csrfData.param, csrfData.token);
            }
        }

        if (controller) {
            timeoutId = window.setTimeout(function() {
                timedOut = true;
                controller.abort();
            }, 30000);
        }

        return window.fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: headers,
            body: body,
            signal: controller ? controller.signal : undefined
        }).then(jsonResponse).catch(function(error) {
            if (timedOut) {
                throw new Error('Время ожидания ответа сервера истекло.');
            }
            throw error;
        }).then(function(result) {
            if (timeoutId !== null) {
                window.clearTimeout(timeoutId);
            }
            return result;
        }, function(error) {
            if (timeoutId !== null) {
                window.clearTimeout(timeoutId);
            }
            throw error;
        });
    }

    function visibleEditors() {
        return editors.filter(function(editor) {
            return editor.root.isConnected && editor.root.getClientRects().length !== 0;
        });
    }

    function editorForPaste() {
        var visible = visibleEditors();

        if (activeEditor && visible.indexOf(activeEditor) !== -1) {
            return activeEditor;
        }

        if (visible.length === 1) {
            return visible[0];
        }

        return null;
    }

    function registerPasteListener() {
        if (pasteListenerRegistered) {
            return;
        }

        pasteListenerRegistered = true;
        document.addEventListener('paste', function(event) {
            var files = arrayFrom(event.clipboardData && event.clipboardData.files);
            var editor;

            if (files.length === 0 && event.clipboardData && event.clipboardData.items) {
                files = arrayFrom(event.clipboardData.items).reduce(function(result, item) {
                    var file;

                    if (item.kind !== 'file') {
                        return result;
                    }

                    file = item.getAsFile();
                    if (file) {
                        result.push(file);
                    }

                    return result;
                }, []);
            }

            if (files.length === 0) {
                return;
            }

            editor = editorForPaste();
            if (!editor) {
                return;
            }

            event.preventDefault();
            editor.addFiles(files);
        });
    }

    function PhotoEditor(root) {
        this.root = root;
        this.form = root.closest('form');
        this.droparea = root.querySelector('[data-photo-editor-droparea]');
        this.input = root.querySelector('[data-photo-editor-input]');
        this.list = root.querySelector('[data-photo-editor-list]');
        this.empty = root.querySelector('[data-photo-editor-empty]');
        this.message = root.querySelector('[data-photo-editor-message]');
        this.manifestInput = root.querySelector('[data-photo-editor-manifest]');
        this.tokenInput = root.querySelector('[data-photo-editor-token]');
        this.createSessionUrl = root.getAttribute('data-create-session-url') || '';
        this.repoId = Number(root.getAttribute('data-repo-id')) || 0;
        this.uploadContext = root.getAttribute('data-upload-context') || '';
        this.uploadUrl = root.getAttribute('data-upload-url') || '';
        this.maxUploadBytes = Math.max(0, Number(root.getAttribute('data-max-upload-bytes')) || 0);
        this.maxConcurrent = Math.max(1, Number(root.getAttribute('data-max-concurrent-uploads')) || 3);
        this.accept = this.input ? this.input.getAttribute('accept') || '' : '';
        this.queue = [];
        this.activeUploads = 0;
        this.activeDeletes = 0;
        this.sessionPromise = null;
        this.dragCounter = 0;
        this.dragState = null;
        this.nextClientId = 1;

        this.bind();
        this.updateState();
    }

    PhotoEditor.prototype.bind = function() {
        var self = this;

        this.root.addEventListener('pointerdown', function() {
            activeEditor = self;
        });
        this.root.addEventListener('focusin', function() {
            activeEditor = self;
        });

        if (this.droparea && this.input) {
            this.droparea.addEventListener('click', function(event) {
                if (!event.target.closest('input')) {
                    self.input.click();
                }
            });
            this.droparea.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    self.input.click();
                }
            });
            this.input.addEventListener('change', function() {
                self.addFiles(arrayFrom(self.input.files));
                self.input.value = '';
            });
            this.droparea.addEventListener('dragenter', function(event) {
                if (!self.hasFileDrag(event)) {
                    return;
                }

                event.preventDefault();
                self.dragCounter += 1;
                self.droparea.classList.add('photo-editor__droparea--active');
            });
            this.droparea.addEventListener('dragover', function(event) {
                if (!self.hasFileDrag(event)) {
                    return;
                }

                event.preventDefault();
                event.dataTransfer.dropEffect = 'copy';
            });
            this.droparea.addEventListener('dragleave', function() {
                self.dragCounter = Math.max(0, self.dragCounter - 1);
                if (self.dragCounter === 0) {
                    self.droparea.classList.remove('photo-editor__droparea--active');
                }
            });
            this.droparea.addEventListener('drop', function(event) {
                if (!self.hasFileDrag(event)) {
                    return;
                }

                event.preventDefault();
                activeEditor = self;
                self.dragCounter = 0;
                self.droparea.classList.remove('photo-editor__droparea--active');
                self.addFiles(arrayFrom(event.dataTransfer.files));
            });
        }

        this.list.addEventListener('click', function(event) {
            var remove = event.target.closest('[data-photo-editor-remove]');
            var retry = event.target.closest('[data-photo-editor-retry]');
            if (remove) {
                event.preventDefault();
                self.removeCard(remove.closest('[data-photo-editor-card]'));
                return;
            }

            if (retry) {
                event.preventDefault();
                self.retryCard(retry.closest('[data-photo-editor-card]'));
                return;
            }
        });

        this.list.addEventListener('pointerdown', function(event) {
            var handle = event.target.closest('[data-photo-editor-drag-handle]');

            if (handle) {
                self.startSorting(event, handle.closest('[data-photo-editor-card]'), handle);
            }
        });
        this.list.addEventListener('keydown', function(event) {
            var handle = event.target.closest('[data-photo-editor-drag-handle]');
            var card;
            var sibling;

            if (!handle || ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].indexOf(event.key) === -1) {
                return;
            }

            event.preventDefault();
            card = handle.closest('[data-photo-editor-card]');
            sibling = event.key === 'ArrowLeft' || event.key === 'ArrowUp'
                ? card.previousElementSibling
                : card.nextElementSibling;

            if (!sibling) {
                return;
            }

            if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                self.list.insertBefore(card, sibling);
            } else {
                self.list.insertBefore(sibling, card);
            }

            self.updateState();
            handle.focus();
        });

        if (this.form) {
            this.form.addEventListener('submit', function(event) {
                if (!self.hasBlockingCards()) {
                    return;
                }

                event.preventDefault();
                self.showMessage(
                    'Дождитесь окончания загрузки или устраните ошибки в карточках фотографий.',
                    'blocking'
                );
                self.root.scrollIntoView({behavior: 'smooth', block: 'center'});
            }, true);
        }
    };

    PhotoEditor.prototype.hasFileDrag = function(event) {
        return arrayFrom(event.dataTransfer && event.dataTransfer.types).indexOf('Files') !== -1;
    };

    PhotoEditor.prototype.addFiles = function(files) {
        var self = this;

        if (files.length === 0) {
            return;
        }

        activeEditor = this;
        this.clearMessage();

        files.forEach(function(file) {
            var card = self.createUploadCard(file);
            var validationError = self.validateFile(file);

            self.list.appendChild(card);

            if (validationError !== '') {
                self.setCardError(card, validationError, false);
                return;
            }

            self.queue.push(card);
        });

        this.updateState();
        this.pumpQueue();
    };

    PhotoEditor.prototype.validateFile = function(file) {
        if (!this.accepts(file)) {
            return 'Неподдерживаемый формат файла.';
        }

        if (this.maxUploadBytes > 0 && file.size > this.maxUploadBytes) {
            return 'Файл больше допустимого размера.';
        }

        return '';
    };

    PhotoEditor.prototype.accepts = function(file) {
        var rules;
        var lowerName;
        var lowerType;

        if (this.accept.trim() === '') {
            return true;
        }

        rules = this.accept.split(',').map(function(rule) {
            return rule.trim().toLowerCase();
        }).filter(Boolean);
        lowerName = String(file.name || '').toLowerCase();
        lowerType = String(file.type || '').toLowerCase();

        return rules.some(function(rule) {
            if (rule.charAt(0) === '.') {
                return lowerName.endsWith(rule);
            }

            if (rule.endsWith('/*')) {
                return lowerType.indexOf(rule.slice(0, -1)) === 0;
            }

            return lowerType === rule;
        });
    };

    PhotoEditor.prototype.createUploadCard = function(file) {
        var card = document.createElement('article');
        var clientId = String(this.nextClientId++);
        var name = file.name || 'Изображение из буфера';

        card.className = 'photo-editor__card photo-editor__card--queued';
        card.setAttribute('data-photo-editor-card', '');
        card.setAttribute('data-client-id', clientId);
        card.setAttribute('data-status', 'queued');
        card.setAttribute('role', 'listitem');
        card._photoEditorFile = file;
        card.innerHTML = [
            '<div class="photo-editor__placeholder" aria-hidden="true">',
            '<i class="glyphicon glyphicon-picture photo-editor__placeholder-icon"></i>',
            '</div>',
            '<button class="photo-editor__drag-handle" type="button" data-photo-editor-drag-handle title="Изменить порядок" aria-label="Изменить порядок фотографии">',
            '<i class="glyphicon glyphicon-move" aria-hidden="true"></i>',
            '</button>',
            '<button class="photo-editor__remove" type="button" data-photo-editor-remove title="Убрать" aria-label="Убрать фотографию">',
            '<i class="glyphicon glyphicon-trash" aria-hidden="true"></i>',
            '</button>',
            '<div class="photo-editor__meta">',
            '<span class="photo-editor__name"></span>',
            '<span class="photo-editor__status" data-photo-editor-status>В очереди</span>',
            '</div>',
            '<div class="photo-editor__progress" data-photo-editor-progress>',
            '<span style="width: 0"></span>',
            '</div>'
        ].join('');
        card.querySelector('.photo-editor__name').textContent = name;
        card.querySelector('.photo-editor__name').title = name;

        return card;
    };

    PhotoEditor.prototype.pumpQueue = function() {
        var self = this;

        if (!this.queue.some(function(card) {
            return card.getAttribute('data-status') === 'queued';
        })) {
            this.updateState();
            return;
        }

        if (this.uploadUrl === '') {
            if (this.sessionPromise) {
                return;
            }

            this.sessionPromise = this.ensureSession().then(function() {
                self.sessionPromise = null;
                self.pumpQueue();
            }).catch(function(error) {
                var cards = self.queue.slice();
                self.sessionPromise = null;
                self.queue = [];
                cards.forEach(function(card) {
                    if (card.getAttribute('data-status') === 'queued') {
                        self.setCardError(card, error.message, true);
                    }
                });
                self.updateState();
            });

            return;
        }

        while (this.activeUploads < this.maxConcurrent) {
            var card = this.queue.shift();

            if (!card) {
                break;
            }

            if (!card.isConnected || card.getAttribute('data-status') !== 'queued') {
                continue;
            }

            this.uploadCard(card);
        }

        this.updateState();
    };

    PhotoEditor.prototype.ensureSession = function() {
        var self = this;

        if (this.uploadUrl !== '') {
            return Promise.resolve();
        }

        if (this.createSessionUrl === '') {
            return Promise.reject(new Error('Не задан URL создания временной upload-сессии.'));
        }

        if (!Number.isSafeInteger(this.repoId) || this.repoId <= 0 || this.uploadContext === '') {
            return Promise.reject(new Error('Не задан контекст временной upload-сессии.'));
        }

        return requestJson(this.createSessionUrl, 'POST', {
            repoId: this.repoId,
            context: this.uploadContext
        }).then(function(data) {
            var token = data.token || data.session_id || data.id;
            var uploadUrl = data.upload_url || data.uploadUrl;

            if (!token || !uploadUrl) {
                throw new Error('Сервер вернул неполные данные upload-сессии.');
            }

            self.uploadUrl = String(uploadUrl);
            self.root.setAttribute('data-upload-url', self.uploadUrl);
            if (self.tokenInput) {
                self.tokenInput.value = String(token);
            }
        });
    };

    PhotoEditor.prototype.uploadCard = function(card) {
        var self = this;
        var file = card._photoEditorFile;
        var xhr = new XMLHttpRequest();
        var formData = new FormData();
        var csrfData = csrf();
        var progress = card.querySelector('[data-photo-editor-progress] > span');
        var requestUrl = this.uploadUrl;

        card._photoEditorXhr = xhr;
        card.setAttribute('data-status', 'uploading');
        card.classList.remove('photo-editor__card--queued', 'photo-editor__card--error');
        card.classList.add('photo-editor__card--uploading');
        card.querySelector('[data-photo-editor-status]').textContent = 'Загрузка 0%';
        progress.style.width = '0%';
        this.activeUploads += 1;

        formData.append('file', file);
        formData.append('last_modified', String(file.lastModified || 0));
        formData.append('position', String(arrayFrom(this.list.children).indexOf(card)));
        if (csrfData.param !== '' && csrfData.token !== '') {
            formData.append(csrfData.param, csrfData.token);
        }

        xhr.upload.addEventListener('progress', function(event) {
            var percent;

            if (!event.lengthComputable || card._photoEditorRemoved) {
                return;
            }

            percent = Math.max(0, Math.min(100, Math.round((event.loaded / event.total) * 100)));
            progress.style.width = percent + '%';
            card.querySelector('[data-photo-editor-status]').textContent = percent >= 100
                ? 'Обработка…'
                : 'Загрузка ' + percent + '%';
        });
        xhr.addEventListener('load', function() {
            var data = {};

            try {
                data = JSON.parse(xhr.responseText || '{}');
            } catch (error) {
                data = {};
            }

            self.finishXhr(card);
            if (card._photoEditorRemoved) {
                return;
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    self.setCardUploaded(card, data);
                } catch (error) {
                    self.setCardError(card, error.message, true);
                }
            } else if (xhr.status === 404) {
                self.handleExpiredUploadSession(card, requestUrl);
            } else {
                self.setCardError(card, responseError(data, xhr.statusText), true);
            }

            self.pumpQueue();
        });
        xhr.addEventListener('error', function() {
            self.finishXhr(card);
            if (!card._photoEditorRemoved) {
                self.setCardError(card, 'Ошибка сети.', true);
            }
            self.pumpQueue();
        });
        xhr.addEventListener('timeout', function() {
            self.finishXhr(card);
            if (!card._photoEditorRemoved) {
                self.setCardError(card, 'Время ожидания загрузки истекло.', true);
            }
            self.pumpQueue();
        });
        xhr.addEventListener('abort', function() {
            self.finishXhr(card);
            if (!card._photoEditorRemoved) {
                self.setCardError(card, 'Загрузка отменена.', true);
            }
            self.pumpQueue();
        });

        xhr.open('POST', requestUrl);
        xhr.timeout = 300000;
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        if (csrfData.token !== '') {
            xhr.setRequestHeader('X-CSRF-Token', csrfData.token);
        }
        xhr.send(formData);
        this.updateState();
    };

    PhotoEditor.prototype.handleExpiredUploadSession = function(card, requestUrl) {
        var hasNewerSession = this.uploadUrl !== '' && this.uploadUrl !== requestUrl;
        var readyTemporary;

        // Один manifest может ссылаться только на одну session. Если в форме
        // уже есть готовые temporary cards старой сессии, безопаснее попросить
        // reload: новый token сделал бы их чужими для server-side validation.
        // Поздний 404 от старого параллельного XHR не относится к уже созданной
        // новой session: такую карточку можно просто поставить в ее очередь.
        readyTemporary = hasNewerSession ? null : this.list.querySelector(
            '[data-photo-editor-card][data-entry-type="temporary"][data-status="ready"]'
        );
        if (readyTemporary) {
            this.setCardError(
                card,
                'Сессия загрузки истекла. Обновите страницу и добавьте файл повторно.',
                false
            );
            this.showMessage(
                'Сессия временной загрузки истекла. Обновите страницу перед продолжением.',
                'warning'
            );
            return;
        }

        // Несколько параллельных XHR могли получить 404 почти одновременно.
        // Сбрасываем только тот URL, которым пользовался именно этот запрос,
        // чтобы поздний ответ не уничтожил уже созданную новую session.
        if (this.uploadUrl === requestUrl) {
            this.uploadUrl = '';
            this.root.setAttribute('data-upload-url', '');
            if (this.tokenInput) {
                this.tokenInput.value = '';
            }
        }

        card.setAttribute('data-status', 'queued');
        card.classList.remove('photo-editor__card--uploading', 'photo-editor__card--error');
        card.classList.add('photo-editor__card--queued');
        card.querySelector('[data-photo-editor-status]').textContent = 'Создание новой сессии…';
        card.querySelector('[data-photo-editor-progress]').hidden = false;
        card.querySelector('[data-photo-editor-progress] > span').style.width = '0%';
        if (this.queue.indexOf(card) === -1) {
            this.queue.push(card);
        }
    };

    PhotoEditor.prototype.finishXhr = function(card) {
        if (!card._photoEditorXhr) {
            return;
        }

        card._photoEditorXhr = null;
        this.activeUploads = Math.max(0, this.activeUploads - 1);
    };

    PhotoEditor.prototype.setCardUploaded = function(card, data) {
        var id = data.id;
        var numericId = Number(id);
        var thumbnailUrl = data.thumbnail_url || data.thumbnailUrl;
        var previewUrl = data.preview_url || data.previewUrl || data.open_url || data.openUrl;
        var deleteUrl = data.delete_url || data.deleteUrl || '';
        var name = data.name || card._photoEditorFile.name || 'Фотография';
        var oldContent;
        var preview;

        if (
            id === undefined
            || id === null
            || !/^\d+$/.test(String(id))
            || !Number.isSafeInteger(numericId)
            || numericId <= 0
            || !thumbnailUrl
            || !previewUrl
            || !deleteUrl
        ) {
            throw new Error('Сервер вернул неполные данные загруженной фотографии.');
        }

        card.setAttribute('data-entry-type', 'temporary');
        card.setAttribute('data-entry-id', String(numericId));
        card.setAttribute('data-delete-url', String(deleteUrl));
        card.setAttribute('data-status', 'ready');
        card.classList.remove('photo-editor__card--queued', 'photo-editor__card--uploading', 'photo-editor__card--error');
        card.classList.add('photo-editor__card--ready');

        oldContent = card.querySelector('.photo-editor__placeholder');
        preview = document.createElement('a');
        preview.className = 'photo-editor__preview';
        preview.href = String(previewUrl);
        preview.setAttribute('data-photo-editor-preview', '');
        preview.setAttribute('data-fancybox', this.root.id);
        preview.setAttribute('data-caption', String(name));
        preview.setAttribute('aria-label', 'Просмотреть: ' + String(name));
        preview.innerHTML = '<img class="photo-editor__thumbnail" alt="" loading="lazy" decoding="async" draggable="false">';
        preview.querySelector('img').src = String(thumbnailUrl);
        preview.querySelector('img').alt = String(name);
        oldContent.replaceWith(preview);

        card.querySelector('.photo-editor__name').textContent = String(name);
        card.querySelector('.photo-editor__name').title = String(name);
        card.querySelector('[data-photo-editor-status]').textContent = 'Готово';
        card.querySelector('[data-photo-editor-progress]').hidden = true;
        card._photoEditorFile = null;
        this.updateState();
    };

    PhotoEditor.prototype.setCardError = function(card, message, retryable) {
        var retry = card.querySelector('[data-photo-editor-retry]');

        card.setAttribute('data-status', 'error');
        card.classList.remove('photo-editor__card--queued', 'photo-editor__card--uploading', 'photo-editor__card--ready');
        card.classList.add('photo-editor__card--error');
        card.querySelector('[data-photo-editor-status]').textContent = message || 'Ошибка загрузки.';
        card.querySelector('[data-photo-editor-progress]').hidden = true;

        if (retryable && !retry) {
            retry = document.createElement('button');
            retry.className = 'photo-editor__retry';
            retry.type = 'button';
            retry.setAttribute('data-photo-editor-retry', '');
            retry.title = 'Повторить';
            retry.setAttribute('aria-label', 'Повторить загрузку');
            retry.innerHTML = '<i class="glyphicon glyphicon-repeat" aria-hidden="true"></i>';
            card.appendChild(retry);
        }

        if (!retryable && retry) {
            retry.remove();
        }

        this.updateState();
    };

    PhotoEditor.prototype.retryCard = function(card) {
        var retry;

        if (!card || card.getAttribute('data-status') !== 'error' || !card._photoEditorFile) {
            return;
        }

        retry = card.querySelector('[data-photo-editor-retry]');
        if (retry) {
            retry.remove();
        }

        card.setAttribute('data-status', 'queued');
        card.classList.remove('photo-editor__card--error');
        card.classList.add('photo-editor__card--queued');
        card.querySelector('[data-photo-editor-status]').textContent = 'В очереди';
        card.querySelector('[data-photo-editor-progress]').hidden = false;
        card.querySelector('[data-photo-editor-progress] > span').style.width = '0%';
        this.queue.push(card);
        this.clearMessage();
        this.updateState();
        this.pumpQueue();
    };

    PhotoEditor.prototype.removeCard = function(card) {
        var status;
        var type;
        var deleteUrl;

        if (!card) {
            return;
        }

        status = card.getAttribute('data-status');
        type = card.getAttribute('data-entry-type');
        deleteUrl = card.getAttribute('data-delete-url') || '';
        card._photoEditorRemoved = true;

        if (status === 'uploading' && card._photoEditorXhr) {
            card._photoEditorXhr.abort();
        }

        this.queue = this.queue.filter(function(queuedCard) {
            return queuedCard !== card;
        });
        card.remove();
        this.updateState();
        this.pumpQueue();

        if (type === 'temporary' && deleteUrl !== '') {
            this.deleteTemporary(deleteUrl);
        }
    };

    PhotoEditor.prototype.deleteTemporary = function(deleteUrl) {
        var self = this;

        this.activeDeletes += 1;
        this.updateState();
        requestJson(deleteUrl, 'DELETE')
            .catch(function(error) {
                self.showMessage(
                    'Карточка убрана, но временный файл не удалось удалить сразу. ' +
                    'Он будет удалён автоматической очисткой. ' + error.message,
                    'warning'
                );
            })
            .then(function() {
                self.activeDeletes = Math.max(0, self.activeDeletes - 1);
                self.updateState();
            });
    };

    PhotoEditor.prototype.startSorting = function(event, card, handle) {
        var self = this;
        var state;

        if (
            !card
            || this.dragState
            || event.isPrimary === false
            || (event.button !== undefined && event.button !== 0)
        ) {
            return;
        }

        event.preventDefault();
        activeEditor = this;
        state = {
            card: card,
            handle: handle,
            captureTarget: this.list,
            pointerId: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            moved: false,
            preview: null,
            offsetX: 0,
            offsetY: 0
        };
        this.dragState = state;

        function move(moveEvent) {
            self.sortingMove(moveEvent);
        }

        function finish(finishEvent) {
            if (!self.dragState || finishEvent.pointerId !== self.dragState.pointerId) {
                return;
            }

            self.finishSorting();
        }

        function lostCapture(lostEvent) {
            if (!self.dragState || lostEvent.pointerId !== self.dragState.pointerId) {
                return;
            }

            self.finishSorting();
        }

        function blurred() {
            self.finishSorting();
        }

        state.moveHandler = move;
        state.finishHandler = finish;
        state.lostCaptureHandler = lostCapture;
        state.blurHandler = blurred;

        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', finish);
        window.addEventListener('pointercancel', finish);
        state.captureTarget.addEventListener('lostpointercapture', lostCapture);
        window.addEventListener('blur', blurred);

        if (typeof state.captureTarget.setPointerCapture === 'function') {
            state.captureTarget.setPointerCapture(event.pointerId);
        }
    };

    PhotoEditor.prototype.activateSorting = function(state) {
        var rectangle = state.card.getBoundingClientRect();
        var preview = state.card.cloneNode(true);

        state.moved = true;
        state.offsetX = state.startX - rectangle.left;
        state.offsetY = state.startY - rectangle.top;
        state.preview = preview;

        preview.classList.remove('photo-editor__card--dragging');
        preview.classList.add('photo-editor__card--drag-preview');
        preview.removeAttribute('data-photo-editor-card');
        preview.setAttribute('aria-hidden', 'true');
        preview.removeAttribute('role');
        preview.style.width = rectangle.width + 'px';
        preview.style.height = rectangle.height + 'px';
        preview.style.left = rectangle.left + 'px';
        preview.style.top = rectangle.top + 'px';
        arrayFrom(preview.querySelectorAll('a, button, input, [tabindex]')).forEach(function(element) {
            element.setAttribute('tabindex', '-1');
        });

        state.card.classList.add('photo-editor__card--dragging');
        this.list.classList.add('photo-editor__cards--sorting');
        document.body.appendChild(preview);
    };

    PhotoEditor.prototype.moveSortingPreview = function(state, event) {
        if (!state.preview) {
            return;
        }

        state.preview.style.left = event.clientX - state.offsetX + 'px';
        state.preview.style.top = event.clientY - state.offsetY + 'px';
    };

    PhotoEditor.prototype.captureSortPositions = function(draggedCard) {
        return arrayFrom(this.list.querySelectorAll('[data-photo-editor-card]')).reduce(function(result, card) {
            if (card !== draggedCard) {
                result.push({card: card, rectangle: card.getBoundingClientRect()});
            }

            return result;
        }, []);
    };

    PhotoEditor.prototype.animateSortReflow = function(positions) {
        var reduceMotion = typeof window.matchMedia === 'function'
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reduceMotion) {
            return;
        }

        positions.forEach(function(position) {
            var card = position.card;
            var rectangle;
            var deltaX;
            var deltaY;
            var animation;

            if (!card.isConnected || typeof card.animate !== 'function') {
                return;
            }

            if (card._photoEditorSortAnimation) {
                card._photoEditorSortAnimation.cancel();
            }

            rectangle = card.getBoundingClientRect();
            deltaX = position.rectangle.left - rectangle.left;
            deltaY = position.rectangle.top - rectangle.top;
            if (Math.abs(deltaX) < 1 && Math.abs(deltaY) < 1) {
                return;
            }

            animation = card.animate([
                {transform: 'translate3d(' + deltaX + 'px, ' + deltaY + 'px, 0)'},
                {transform: 'translate3d(0, 0, 0)'}
            ], {
                duration: 150,
                easing: 'ease-out'
            });
            card._photoEditorSortAnimation = animation;
            animation.onfinish = function() {
                if (card._photoEditorSortAnimation === animation) {
                    card._photoEditorSortAnimation = null;
                }
            };
            animation.oncancel = animation.onfinish;
        });
    };

    PhotoEditor.prototype.sortingIsHorizontal = function() {
        var columns;

        if (typeof window.getComputedStyle !== 'function') {
            return true;
        }

        columns = window.getComputedStyle(this.list).gridTemplateColumns;

        return columns !== 'none' && columns.trim().split(/\s+/).length > 1;
    };

    PhotoEditor.prototype.sortingMove = function(event) {
        var state = this.dragState;
        var target;
        var rectangle;
        var before;
        var positions;

        if (!state || event.pointerId !== state.pointerId) {
            return;
        }

        if (!state.moved) {
            if (Math.hypot(event.clientX - state.startX, event.clientY - state.startY) < 5) {
                return;
            }

            this.activateSorting(state);
        }

        event.preventDefault();
        this.moveSortingPreview(state, event);
        if (event.clientY < 60) {
            window.scrollBy(0, -14);
        } else if (event.clientY > window.innerHeight - 60) {
            window.scrollBy(0, 14);
        }

        target = document.elementFromPoint(event.clientX, event.clientY);
        target = target && target.closest('[data-photo-editor-card]');

        if (!target || target === state.card || target.parentElement !== this.list) {
            return;
        }

        rectangle = target.getBoundingClientRect();
        before = this.sortingIsHorizontal()
            ? event.clientX < rectangle.left + rectangle.width / 2
            : event.clientY < rectangle.top + rectangle.height / 2;

        if (
            (before && state.card.nextElementSibling === target)
            || (!before && target.nextElementSibling === state.card)
        ) {
            return;
        }

        positions = this.captureSortPositions(state.card);
        this.list.insertBefore(state.card, before ? target : target.nextSibling);
        this.animateSortReflow(positions);
    };

    PhotoEditor.prototype.finishSorting = function() {
        var state = this.dragState;

        if (!state) {
            return;
        }

        window.removeEventListener('pointermove', state.moveHandler);
        window.removeEventListener('pointerup', state.finishHandler);
        window.removeEventListener('pointercancel', state.finishHandler);
        state.captureTarget.removeEventListener('lostpointercapture', state.lostCaptureHandler);
        window.removeEventListener('blur', state.blurHandler);

        if (
            typeof state.captureTarget.hasPointerCapture === 'function'
            && state.captureTarget.hasPointerCapture(state.pointerId)
        ) {
            state.captureTarget.releasePointerCapture(state.pointerId);
        }

        if (state.preview && state.preview.parentNode) {
            state.preview.parentNode.removeChild(state.preview);
        }

        state.card.classList.remove('photo-editor__card--dragging');
        this.list.classList.remove('photo-editor__cards--sorting');
        this.dragState = null;
        this.updateState();
    };

    PhotoEditor.prototype.manifest = function() {
        return arrayFrom(this.list.querySelectorAll('[data-photo-editor-card]')).reduce(function(result, card) {
            var type = card.getAttribute('data-entry-type');
            var id = card.getAttribute('data-entry-id');
            var numericId = Number(id);

            if (
                card.getAttribute('data-status') === 'ready'
                && (type === 'existing' || type === 'temporary')
                && id !== null
                && /^\d+$/.test(id)
                && Number.isSafeInteger(numericId)
                && numericId > 0
            ) {
                result.push({type: type, id: numericId});
            }

            return result;
        }, []);
    };

    PhotoEditor.prototype.hasBlockingCards = function() {
        return this.activeDeletes > 0 || arrayFrom(this.list.querySelectorAll('[data-photo-editor-card]')).some(function(card) {
            return ['queued', 'uploading', 'error'].indexOf(card.getAttribute('data-status')) !== -1;
        });
    };

    PhotoEditor.prototype.updateState = function() {
        if (this.manifestInput) {
            this.manifestInput.value = JSON.stringify(this.manifest());
        }

        if (this.empty) {
            this.empty.hidden = this.list.querySelector('[data-photo-editor-card]') !== null;
        }

        if (!this.hasBlockingCards() && this.message && this.message.getAttribute('data-message-kind') === 'blocking') {
            this.clearMessage();
        }

        this.updateFormSubmitState();
    };

    PhotoEditor.prototype.updateFormSubmitState = function() {
        var blocked;

        if (!this.form) {
            return;
        }

        blocked = arrayFrom(this.form.querySelectorAll('[data-photo-editor]')).some(function(root) {
            return root._photoEditorController && root._photoEditorController.hasBlockingCards();
        });

        arrayFrom(this.form.querySelectorAll('button[type="submit"], input[type="submit"]')).forEach(function(button) {
            if (blocked) {
                if (!button.hasAttribute('data-photo-editor-disabled')) {
                    button.setAttribute('data-photo-editor-disabled', button.disabled ? 'was-disabled' : 'was-enabled');
                }
                button.disabled = true;
                return;
            }

            if (button.hasAttribute('data-photo-editor-disabled')) {
                button.disabled = button.getAttribute('data-photo-editor-disabled') === 'was-disabled';
                button.removeAttribute('data-photo-editor-disabled');
            }
        });
    };

    PhotoEditor.prototype.showMessage = function(message, kind) {
        if (!this.message) {
            return;
        }

        this.message.textContent = message;
        this.message.setAttribute('data-message-kind', kind || 'general');
        this.message.classList.toggle('alert-warning', kind === 'warning');
        this.message.classList.toggle('alert-danger', kind !== 'warning');
        this.message.hidden = false;
    };

    PhotoEditor.prototype.clearMessage = function() {
        if (!this.message) {
            return;
        }
        if (this.message.getAttribute('data-message-kind') === 'server') {
            return;
        }

        this.message.textContent = '';
        this.message.removeAttribute('data-message-kind');
        this.message.classList.remove('alert-warning');
        this.message.classList.add('alert-danger');
        this.message.hidden = true;
    };

    function init(root) {
        var editor;

        if (!root || root._photoEditorController) {
            return root ? root._photoEditorController : null;
        }

        editor = new PhotoEditor(root);
        root._photoEditorController = editor;
        editors.push(editor);
        registerPasteListener();

        return editor;
    }

    function initAll(scope) {
        arrayFrom((scope || document).querySelectorAll('[data-photo-editor]')).forEach(init);
    }

    window.StockhubPhotoEditor = {
        init: init,
        initAll: initAll
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initAll(document);
        });
    } else {
        initAll(document);
    }
})(window, document);
