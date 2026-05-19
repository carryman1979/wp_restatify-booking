(function () {
    'use strict';

    var cfg = window.restatifyBookingAdminConfig || {};
    var optionKey = cfg.optionKey || 'restatify_booking_assistant_options';
    var calendarNamePlaceholder = cfg.calendarNamePlaceholder || 'Kalendername';
    var deleteLabel = cfg.deleteLabel || 'Löschen';
    var whatsappPlaceholder = cfg.whatsappPlaceholder || 'WhatsApp';
    var phoneNumberPlaceholder = cfg.phoneNumberPlaceholder || 'Telefonnummer';
    var untilLabel = cfg.untilLabel || 'bis';

    var setupRepeater = function (config) {
        var table = document.querySelector(config.tableSelector);
        if (!table) {
            return;
        }

        var tbody = table.querySelector('tbody');
        if (!tbody) {
            return;
        }

        var reindexRows = function () {
            var rows = tbody.querySelectorAll(config.rowSelector);
            rows.forEach(function (row, index) {
                var base = optionKey + '[' + config.optionKey + '][' + index + ']';
                var fields = row.querySelectorAll('[data-rs-field], input, select, textarea');
                fields.forEach(function (field) {
                    var key = field.getAttribute('data-rs-field') || '';
                    if (!key) {
                        return;
                    }
                    field.name = base + '[' + key + ']';
                });
            });
        };

        var ensureOneRow = function () {
            if (tbody.querySelectorAll(config.rowSelector).length > 0) {
                return;
            }
            tbody.appendChild(config.rowTemplate());
            reindexRows();
        };

        document.querySelectorAll(config.addSelector).forEach(function (button) {
            button.addEventListener('click', function () {
                var position = button.getAttribute(config.positionAttribute);
                var row = config.rowTemplate();
                if (position === 'top' && tbody.firstChild) {
                    tbody.insertBefore(row, tbody.firstChild);
                } else {
                    tbody.appendChild(row);
                }
                reindexRows();
            });
        });

        tbody.addEventListener('click', function (event) {
            var target = event.target;
            if (!(target instanceof HTMLElement) || !target.matches(config.removeSelector)) {
                return;
            }
            var row = target.closest(config.rowSelector);
            if (row) {
                row.remove();
            }
            ensureOneRow();
            reindexRows();
        });

        reindexRows();
    };

    setupRepeater({
        tableSelector: '[data-rs-calendar-sources-table]',
        rowSelector: '[data-rs-calendar-source-row]',
        addSelector: '[data-rs-add-calendar-row]',
        removeSelector: '[data-rs-remove-calendar-row]',
        positionAttribute: 'data-rs-add-calendar-row',
        optionKey: 'api_calendar_sources_rows',
        rowTemplate: function () {
            var row = document.createElement('tr');
            row.setAttribute('data-rs-calendar-source-row', '');
            row.innerHTML = [
                '<td><input class="regular-text code" type="text" data-rs-field="calendar_id" placeholder="calendar-id@group.calendar.google.com"></td>',
                '<td><input class="regular-text" type="text" data-rs-field="label" placeholder="' + calendarNamePlaceholder + '"></td>',
                '<td><select data-rs-field="privacy_mode"><option value="private">private</option><option value="official">official</option></select></td>',
                '<td><select data-rs-field="calendar_type"><option value="general">general</option><option value="holiday">holiday</option></select></td>',
                '<td><button type="button" class="button-link-delete" data-rs-remove-calendar-row>' + deleteLabel + '</button></td>'
            ].join('');
            return row;
        }
    });

    setupRepeater({
        tableSelector: '[data-rs-contact-channels-table]',
        rowSelector: '[data-rs-contact-channel-row]',
        addSelector: '[data-rs-add-contact-row]',
        removeSelector: '[data-rs-remove-contact-row]',
        positionAttribute: 'data-rs-add-contact-row',
        optionKey: 'contact_channels_rows',
        rowTemplate: function () {
            var row = document.createElement('tr');
            row.setAttribute('data-rs-contact-channel-row', '');
            row.innerHTML = [
                '<td><input class="regular-text code" type="text" data-rs-field="key" placeholder="whatsapp"></td>',
                '<td><input class="regular-text" type="text" data-rs-field="label" placeholder="' + whatsappPlaceholder + '"></td>',
                '<td><select data-rs-field="input_kind"><option value="tel">tel</option><option value="email">email</option><option value="url">url</option><option value="text">text</option></select></td>',
                '<td><input class="regular-text" type="text" data-rs-field="placeholder" placeholder="+49..."></td>',
                '<td><input class="regular-text" type="text" data-rs-field="value_label" placeholder="' + phoneNumberPlaceholder + '"></td>',
                '<td><input class="regular-text code" type="text" data-rs-field="ics_template" placeholder="Telefon: {value}"></td>',
                '<td><button type="button" class="button-link-delete" data-rs-remove-contact-row>' + deleteLabel + '</button></td>'
            ].join('');
            return row;
        }
    });

    var createAvailabilitySlot = function () {
        var row = document.createElement('div');
        row.className = 'rs-availability-slot';
        row.setAttribute('data-rs-availability-slot', '');
        row.innerHTML = [
            '<input type="time" data-rs-field="start">',
            '<span>' + untilLabel + '</span>',
            '<input type="time" data-rs-field="end">',
            '<button type="button" class="button-link-delete" data-rs-remove-availability-slot>' + deleteLabel + '</button>'
        ].join('');
        return row;
    };

    var reindexAvailabilityDay = function (day) {
        if (!(day instanceof HTMLElement)) {
            return;
        }
        var weekday = day.getAttribute('data-weekday') || '';
        var slots = day.querySelectorAll('[data-rs-availability-slot]');
        slots.forEach(function (slot, index) {
            slot.querySelectorAll('[data-rs-field]').forEach(function (field) {
                var key = field.getAttribute('data-rs-field') || '';
                if (!key) {
                    return;
                }
                field.name = optionKey + '[api_availability_rows][' + weekday + '][windows][' + index + '][' + key + ']';
            });
        });
    };

    document.querySelectorAll('[data-rs-availability-day]').forEach(function (day) {
        var enabled = day.querySelector('[data-rs-availability-enabled]');
        var wrapper = day.querySelector('[data-rs-availability-slots-wrapper]');
        var slots = day.querySelector('[data-rs-availability-slots]');
        var addButton = day.querySelector('[data-rs-add-availability-slot]');

        if (!(enabled instanceof HTMLInputElement) || !(wrapper instanceof HTMLElement) || !(slots instanceof HTMLElement) || !(addButton instanceof HTMLElement)) {
            return;
        }

        var syncDayState = function () {
            var isEnabled = enabled.checked;
            day.classList.toggle('is-disabled', !isEnabled);
            wrapper.hidden = !isEnabled;
            if (isEnabled && slots.querySelectorAll('[data-rs-availability-slot]').length === 0) {
                slots.appendChild(createAvailabilitySlot());
            }
            reindexAvailabilityDay(day);
        };

        enabled.addEventListener('change', syncDayState);
        addButton.addEventListener('click', function () {
            slots.appendChild(createAvailabilitySlot());
            reindexAvailabilityDay(day);
        });
        slots.addEventListener('click', function (event) {
            var target = event.target;
            if (!(target instanceof HTMLElement) || !target.matches('[data-rs-remove-availability-slot]')) {
                return;
            }
            var slot = target.closest('[data-rs-availability-slot]');
            if (slot) {
                slot.remove();
            }
            reindexAvailabilityDay(day);
        });

        reindexAvailabilityDay(day);
        syncDayState();
    });

    var sharedMailEditor = window.RestatifySharedMailEditor || null;

    var ensureEditor = function (textarea) {
        if (!(textarea instanceof HTMLTextAreaElement) || textarea.dataset.rsEditorInitialized === '1') {
            return;
        }
        if (!window.wp || !window.wp.editor || typeof window.wp.editor.initialize !== 'function') {
            return;
        }

        window.wp.editor.initialize(textarea.id, {
            tinymce: {
                wpautop: true,
                toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink,undo,redo,removeformat',
                toolbar2: ''
            },
            quicktags: false,
            mediaButtons: false
        });

        textarea.dataset.rsEditorInitialized = '1';

        var bindEditor = function () {
            if (!window.tinymce || typeof window.tinymce.get !== 'function') {
                return;
            }
            var editor = window.tinymce.get(textarea.id);
            if (!editor) {
                window.requestAnimationFrame(bindEditor);
                return;
            }
            editor.on('focus', function () {
                var modal = textarea.closest('[data-rs-mail-modal]');
                if (modal instanceof HTMLElement) {
                    modal.dataset.rsActiveEditorId = textarea.id;
                }
            });

            editor.on('change keyup SetContent', function () {
                var codeMirror = document.querySelector('[data-rs-mail-html-code-for="' + textarea.id + '"]');
                if (codeMirror instanceof HTMLTextAreaElement) {
                    codeMirror.value = editor.getContent() || '';
                }
            });
        };

        window.requestAnimationFrame(bindEditor);
    };

    var closeModal = function (modal) {
        if (!(modal instanceof HTMLElement)) {
            return;
        }
        modal.hidden = true;
        delete modal.dataset.rsActiveEditorId;
        if (!document.querySelector('[data-rs-mail-modal]:not([hidden])')) {
            document.body.classList.remove('rs-mail-modal-open');
        }
    };

    var openModal = function (modalId) {
        var modal = document.querySelector('[data-rs-mail-modal="' + modalId + '"]');
        if (!(modal instanceof HTMLElement)) {
            return;
        }
        modal.hidden = false;
        document.body.classList.add('rs-mail-modal-open');
        modal.querySelectorAll('[data-rs-mail-html-editor]').forEach(function (field) {
            ensureEditor(field);
        });
        modal.querySelectorAll('[data-rs-mail-html-code-for]').forEach(function (field) {
            if (!(field instanceof HTMLTextAreaElement)) {
                return;
            }
            var editorId = field.getAttribute('data-rs-mail-html-code-for') || '';
            var editorField = modal.querySelector('#' + editorId);
            if (editorField instanceof HTMLTextAreaElement) {
                field.value = editorField.value || '';
            }
        });
        var firstFocusable = modal.querySelector('input, textarea, button, select');
        if (firstFocusable instanceof HTMLElement) {
            firstFocusable.focus();
        }
    };

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        var openTrigger = target.closest('[data-rs-open-mail-modal]');
        if (openTrigger instanceof HTMLElement) {
            event.preventDefault();
            openModal(openTrigger.getAttribute('data-rs-open-mail-modal') || '');
            return;
        }

        var closeTrigger = target.closest('[data-rs-close-mail-modal]');
        if (closeTrigger instanceof HTMLElement) {
            event.preventDefault();
            closeModal(closeTrigger.closest('[data-rs-mail-modal]'));
            return;
        }

        var resetTrigger = target.closest('[data-rs-mail-reset-template]');
        if (resetTrigger instanceof HTMLElement) {
            event.preventDefault();
            var section = resetTrigger.closest('[data-rs-mail-template-section]');
            if (!(section instanceof HTMLElement)) {
                return;
            }

            var readDefault = function (key) {
                if (!key) {
                    return '';
                }
                var defaultField = section.querySelector('[data-rs-mail-default-key="' + key + '"]');
                return defaultField instanceof HTMLTextAreaElement ? (defaultField.value || '') : '';
            };

            var subjectKey = resetTrigger.getAttribute('data-rs-mail-subject-key') || '';
            if (subjectKey) {
                var subjectField = section.querySelector('#' + subjectKey);
                if (subjectField instanceof HTMLInputElement) {
                    subjectField.value = readDefault(subjectKey);
                }
            }

            var htmlEditorId = resetTrigger.getAttribute('data-rs-mail-html-editor-id') || '';
            var htmlKey = resetTrigger.getAttribute('data-rs-mail-html-key') || '';
            if (htmlEditorId && htmlKey) {
                var htmlValue = readDefault(htmlKey);
                var htmlField = section.querySelector('#' + htmlEditorId);
                if (htmlField instanceof HTMLTextAreaElement) {
                    htmlField.value = htmlValue;
                }

                if (window.tinymce && typeof window.tinymce.get === 'function') {
                    var editor = window.tinymce.get(htmlEditorId);
                    if (editor && typeof editor.setContent === 'function') {
                        editor.setContent(htmlValue);
                    }
                }

                var codeField = section.querySelector('#' + htmlEditorId + '_code');
                if (codeField instanceof HTMLTextAreaElement) {
                    codeField.value = htmlValue;
                }
            }

            var textKey = resetTrigger.getAttribute('data-rs-mail-text-key') || '';
            if (textKey) {
                var textField = section.querySelector('#' + textKey);
                if (textField instanceof HTMLTextAreaElement) {
                    textField.value = readDefault(textKey);
                }
            }

            return;
        }

        if (target.matches('[data-rs-mail-modal]')) {
            closeModal(target);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }
        var modal = document.querySelector('[data-rs-mail-modal]:not([hidden])');
        if (modal instanceof HTMLElement) {
            closeModal(modal);
        }
    });

    if (sharedMailEditor && typeof sharedMailEditor.initTabSystem === 'function') {
        sharedMailEditor.initTabSystem({
            tabSelector: '[data-rs-mail-tab]',
            panelSelector: '[data-rs-mail-tab-panel]',
            tabGroupAttr: 'data-rs-mail-tab',
            panelGroupAttr: 'data-rs-mail-tab-panel',
            panelAttr: 'data-rs-mail-panel',
            activeClass: 'is-active',
            onSwitch: function (group, panel) {
                if (panel === 'code' && typeof sharedMailEditor.syncCodeFromEditor === 'function') {
                    sharedMailEditor.syncCodeFromEditor(group, '[data-rs-mail-html-code-for]', 'data-rs-mail-html-code-for');
                }
            }
        });
    }

    if (sharedMailEditor && typeof sharedMailEditor.bindHtmlCodeSync === 'function') {
        sharedMailEditor.bindHtmlCodeSync({
            codeSelector: '[data-rs-mail-html-code-for]',
            codeForAttr: 'data-rs-mail-html-code-for'
        });
    }

    if (sharedMailEditor && typeof sharedMailEditor.initPlaceholderButtons === 'function') {
        sharedMailEditor.initPlaceholderButtons({
            buttonSelector: '[data-rs-insert-placeholder]',
            placeholderAttr: 'data-rs-insert-placeholder',
            activeEditorIdResolver: function (button) {
                var modal = button.closest('[data-rs-mail-modal]');
                return modal instanceof HTMLElement ? (modal.dataset.rsActiveEditorId || '') : '';
            },
            onAfterEditorInsert: function (editorId) {
                if (typeof sharedMailEditor.syncCodeFromEditor === 'function') {
                    sharedMailEditor.syncCodeFromEditor(editorId, '[data-rs-mail-html-code-for]', 'data-rs-mail-html-code-for');
                }
            }
        });
    }
})();
