(function () {
  var popupRoots = [];
  var htmlLang = document.documentElement && document.documentElement.lang ? String(document.documentElement.lang) : '';
  var locale = htmlLang || navigator.language || 'de-DE';
  var weekStartsMonday = /^de(-|$)/i.test(locale);

  function initBookingPopup(root) {
    var openBtn = root.querySelector('[data-booking-open]');
    var closeBtn = root.querySelector('[data-booking-close]');
    var cancelBtn = root.querySelector('[data-booking-cancel]');
    var overlay = root.querySelector('[data-booking-overlay]');
    var statusNode = root.querySelector('[data-booking-status]');
    var form = root.querySelector('[data-booking-form]');
    var slotStartInput = root.querySelector('[data-slot-start]');
    var dateStepNode = root.querySelector('[data-booking-calendar]');
    var timeStepNode = root.querySelector('[data-booking-times]');
    var wizardTrack = root.querySelector('[data-booking-steps]');
    var wizardSteps = root.querySelectorAll('[data-booking-step]');
    var wizardPrev = root.querySelector('[data-step-prev]');
    var wizardNext = root.querySelector('[data-step-next]');
    var wizardIndicator = root.querySelector('[data-step-indicator]');
    var contactMethodInput = root.querySelector('[data-contact-method]');
    var contactChannelsNode = root.querySelector('[data-contact-channels]');
    var contactChannelsToggle = root.querySelector('[data-contact-channels-toggle]');
    var contactValueInput = root.querySelector('[data-contact-value]');
    var contactValueLabel = root.querySelector('[data-contact-value-label]');
    var submitBtn = root.querySelector('.restatify-booking__submit');
    var autoCloseTimer = null;
    var defaultContactMethod = contactMethodInput ? String(contactMethodInput.value || '') : '';

    var bookingState = {
      active: false,
      selectedSlot: '',
      confirmed: false,
      slots: [],
      selectedDayKey: '',
      currentMonth: null,
      currentFormStep: 0
    };

    if (!closeBtn || !overlay || !statusNode || !form || !slotStartInput || !dateStepNode || !timeStepNode) {
      return;
    }

    function stepCount() {
      return wizardSteps ? wizardSteps.length : 0;
    }

    function renderNoSlotsStatus() {
      var noSlotsConfig = restatifyBookingAssistant.noSlots || {};
      var strings = restatifyBookingAssistant.strings || {};
      var chatAvailable = !!noSlotsConfig.chatAvailable;
      var contactEmail = String(noSlotsConfig.contactEmail || '').trim();

      statusNode.textContent = '';

      var headline = document.createElement('p');
      headline.textContent = strings.emptyRange || strings.empty || 'Im ausgewählten Zeitraum wurden keine freien Termine gefunden.';
      statusNode.appendChild(headline);

      if (chatAvailable) {
        var chatHint = document.createElement('p');
        chatHint.textContent = strings.emptyChatHint || 'Nutze bitte das Chat-Overlay, um uns schnell zu erreichen.';
        statusNode.appendChild(chatHint);
      }

      if (contactEmail) {
        var emailHint = document.createElement('p');
        var emailHintText = document.createTextNode((strings.emptyEmailHint || 'Bitte schreibe uns eine E-Mail an') + ' ');
        var emailLink = document.createElement('a');
        emailLink.href = 'mailto:' + contactEmail;
        emailLink.textContent = contactEmail;
        emailHint.appendChild(emailHintText);
        emailHint.appendChild(emailLink);
        statusNode.appendChild(emailHint);
      }
    }

    function isStepValid(stepIndex) {
      if (stepIndex === 0) {
        return bookingState.selectedDayKey !== '';
      }

      if (stepIndex === 1) {
        return String(slotStartInput.value || '').trim() !== '';
      }

      var current = wizardSteps[stepIndex];
      if (!current) {
        return true;
      }

      var requiredFields = current.querySelectorAll('input[required], textarea[required], select[required]');
      for (var i = 0; i < requiredFields.length; i += 1) {
        var field = requiredFields[i];
        if (!field.checkValidity()) {
          field.reportValidity();
          return false;
        }
      }

      return true;
    }

    function updateFormWizard() {
      var total = stepCount();
      if (!wizardTrack || total === 0) {
        if (submitBtn) {
          submitBtn.hidden = false;
        }
        return;
      }

      if (bookingState.currentFormStep < 0) {
        bookingState.currentFormStep = 0;
      }
      if (bookingState.currentFormStep > total - 1) {
        bookingState.currentFormStep = total - 1;
      }

      wizardTrack.style.transform = 'translateX(-' + String(bookingState.currentFormStep * 100) + '%)';
      for (var i = 0; i < wizardSteps.length; i += 1) {
        wizardSteps[i].setAttribute('aria-hidden', i === bookingState.currentFormStep ? 'false' : 'true');
      }

      var isFirst = bookingState.currentFormStep === 0;
      var isLast = bookingState.currentFormStep === total - 1;

      if (wizardPrev) {
        wizardPrev.hidden = isFirst;
      }
      if (wizardNext) {
        wizardNext.hidden = isLast;
      }
      if (wizardIndicator) {
        wizardIndicator.textContent = String(bookingState.currentFormStep + 1) + '/' + String(total);
      }
      if (submitBtn) {
        submitBtn.hidden = !isLast;
      }
    }

    function resetFormWizard() {
      bookingState.currentFormStep = 0;
      updateFormWizard();
    }

    function getContactButtons() {
      if (!contactChannelsNode) {
        return [];
      }
      return Array.prototype.slice.call(contactChannelsNode.querySelectorAll('[data-contact-channel]'));
    }

    function setSelectedContactMethod(methodKey) {
      var buttons = getContactButtons();
      if (!buttons.length) {
        return;
      }

      var selected = buttons[0];
      buttons.forEach(function (button) {
        if (String(button.getAttribute('data-method-key') || '') === String(methodKey || '')) {
          selected = button;
        }
      });

      buttons.forEach(function (button) {
        var active = button === selected;
        button.classList.toggle('is-selected', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
      });

      if (contactMethodInput) {
        contactMethodInput.value = String(selected.getAttribute('data-method-key') || '');
      }
    }

    function syncContactField() {
      if (!contactValueInput || !contactValueLabel) {
        return;
      }

      var selected = getContactButtons().find(function (button) {
        return button.classList.contains('is-selected');
      }) || getContactButtons()[0];

      if (!selected) {
        return;
      }

      var inputKind = String(selected.getAttribute('data-input-kind') || 'text');
      var valueLabel = String(selected.getAttribute('data-value-label') || 'Kontaktdaten');
      var placeholder = String(selected.getAttribute('data-placeholder') || '');

      if (inputKind === 'email') {
        contactValueInput.type = 'email';
        contactValueInput.inputMode = 'email';
      } else if (inputKind === 'tel') {
        contactValueInput.type = 'tel';
        contactValueInput.inputMode = 'tel';
      } else if (inputKind === 'url') {
        contactValueInput.type = 'url';
        contactValueInput.inputMode = 'url';
      } else {
        contactValueInput.type = 'text';
        contactValueInput.inputMode = 'text';
      }

      contactValueLabel.textContent = valueLabel;
      contactValueInput.placeholder = placeholder;
    }

    function initContactToggle() {
      if (!contactChannelsNode || !contactChannelsToggle) {
        return;
      }

      var moreLabel = String(contactChannelsToggle.getAttribute('data-label-more') || 'Mehr...');
      var lessLabel = String(contactChannelsToggle.getAttribute('data-label-less') || 'Weniger');

      function update(expanded) {
        contactChannelsNode.classList.toggle('is-expanded', expanded);
        contactChannelsNode.classList.toggle('is-collapsed', !expanded);
        contactChannelsToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        contactChannelsToggle.textContent = expanded ? lessLabel : moreLabel;
      }

      update(contactChannelsNode.classList.contains('is-expanded'));
      contactChannelsToggle.addEventListener('click', function () {
        update(!contactChannelsNode.classList.contains('is-expanded'));
      });
    }

    function getSlotsForDay(dayKey) {
      return bookingState.slots.filter(function (slot) {
        return getDayKey(new Date(slot.start_iso)) === dayKey;
      });
    }

    function renderDateStep() {
      dateStepNode.innerHTML = '';

      var month = bookingState.currentMonth || startOfMonth(new Date());
      bookingState.currentMonth = startOfMonth(month);

      var header = document.createElement('div');
      header.className = 'restatify-booking__calendar-header';

      var prev = document.createElement('button');
      prev.type = 'button';
      prev.className = 'restatify-booking__calendar-nav';
      prev.textContent = '<';
      prev.addEventListener('click', function () {
        bookingState.currentMonth = startOfMonth(new Date(month.getFullYear(), month.getMonth() - 1, 1));
        renderDateStep();
      });

      var title = document.createElement('strong');
      title.className = 'restatify-booking__calendar-title';
      title.textContent = month.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });

      var next = document.createElement('button');
      next.type = 'button';
      next.className = 'restatify-booking__calendar-nav';
      next.textContent = '>';
      next.addEventListener('click', function () {
        bookingState.currentMonth = startOfMonth(new Date(month.getFullYear(), month.getMonth() + 1, 1));
        renderDateStep();
      });

      header.appendChild(prev);
      header.appendChild(title);
      header.appendChild(next);
      dateStepNode.appendChild(header);

      var weekdayRow = document.createElement('div');
      weekdayRow.className = 'restatify-booking__calendar-weekdays';
      getWeekdayLabels().forEach(function (wd) {
        var node = document.createElement('span');
        node.textContent = wd;
        weekdayRow.appendChild(node);
      });
      dateStepNode.appendChild(weekdayRow);

      var grid = document.createElement('div');
      grid.className = 'restatify-booking__calendar-grid';

      var firstDay = new Date(month.getFullYear(), month.getMonth(), 1);
      var daysInMonth = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
      var offset = getWeekdayIndex(firstDay);

      for (var blank = 0; blank < offset; blank += 1) {
        var emptyCell = document.createElement('span');
        emptyCell.className = 'restatify-booking__calendar-empty';
        grid.appendChild(emptyCell);
      }

      for (var day = 1; day <= daysInMonth; day += 1) {
        var date = new Date(month.getFullYear(), month.getMonth(), day);
        var dayKey = getDayKey(date);
        var available = getSlotsForDay(dayKey).length > 0;

        var cell = document.createElement('button');
        cell.type = 'button';
        cell.className = 'restatify-booking__calendar-day';
        if (available) {
          cell.classList.add('is-available');
        } else {
          cell.classList.add('is-disabled');
          cell.disabled = true;
        }
        if (bookingState.selectedDayKey === dayKey) {
          cell.classList.add('is-selected');
        }

        cell.textContent = String(day);
        if (available) {
          cell.addEventListener('click', function (clickedDay) {
            return function () {
              bookingState.selectedDayKey = clickedDay;
              bookingState.selectedSlot = '';
              slotStartInput.value = '';
              renderDateStep();
              renderTimeStep();
            };
          }(dayKey));
        }

        grid.appendChild(cell);
      }

      dateStepNode.appendChild(grid);
    }

    function renderTimeStep() {
      timeStepNode.innerHTML = '';
      var daySlots = getSlotsForDay(bookingState.selectedDayKey);

      if (!daySlots.length) {
        var empty = document.createElement('p');
        empty.className = 'restatify-booking__times-empty';
        empty.textContent = restatifyBookingAssistant.strings.pickTime || 'Bitte zuerst ein Datum auswählen.';
        timeStepNode.appendChild(empty);
        return;
      }

      var grid = document.createElement('div');
      grid.className = 'restatify-booking__times-grid';

      daySlots.forEach(function (slot) {
        var startDate = new Date(slot.start_iso);
        var label = startDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'restatify-booking__slot';
        if (bookingState.selectedSlot === slot.start_iso) {
          button.classList.add('is-selected');
        }
        button.textContent = label;
        button.addEventListener('click', function () {
          bookingState.selectedSlot = slot.start_iso;
          slotStartInput.value = slot.start_iso;
          renderTimeStep();
        });

        grid.appendChild(button);
      });

      timeStepNode.appendChild(grid);
    }

    function loadSlots() {
      var body = new URLSearchParams();
      body.set('action', 'restatify_booking_find_slots');
      body.set('nonce', restatifyBookingAssistant.nonce);
      body.set('timezone', restatifyBookingAssistant.timezone);
      body.set('duration_minutes', String(restatifyBookingAssistant.durationMinutes));
      body.set('window_days', String(restatifyBookingAssistant.windowDays));

      fetch(restatifyBookingAssistant.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString()
      }).then(function (response) {
        return response.json();
      }).then(function (data) {
        if (!data || data.success !== true) {
          var message = data && data.data && data.data.message ? String(data.data.message) : restatifyBookingAssistant.strings.error;
          statusNode.textContent = message;
          form.hidden = true;
          return;
        }

        var slots = data && data.success && data.data && Array.isArray(data.data.slots) ? data.data.slots : [];
        if (!slots.length) {
          renderNoSlotsStatus();
          form.hidden = true;
          return;
        }

        bookingState.slots = slots.slice();
        bookingState.selectedDayKey = '';
        bookingState.currentMonth = startOfMonth(new Date(slots[0].start_iso));
        bookingState.selectedSlot = '';
        slotStartInput.value = '';

        renderDateStep();
        renderTimeStep();
        resetFormWizard();
        form.hidden = false;
        statusNode.textContent = '';
      }).catch(function () {
        statusNode.textContent = restatifyBookingAssistant.strings.error;
        form.hidden = true;
      });
    }

    function openPopup() {
      if (autoCloseTimer) {
        clearTimeout(autoCloseTimer);
        autoCloseTimer = null;
      }

      overlay.hidden = false;
      form.hidden = true;
      statusNode.textContent = restatifyBookingAssistant.strings.loading;
      bookingState.active = true;
      bookingState.confirmed = false;
      loadSlots();
    }

    function closePopup(cancelledByVisitor) {
      if (autoCloseTimer) {
        clearTimeout(autoCloseTimer);
        autoCloseTimer = null;
      }

      overlay.hidden = true;

      if (cancelledByVisitor && bookingState.active && !bookingState.confirmed) {
        sendChatEvent('cancelled', {
          startIso: bookingState.selectedSlot
        });
      }

      bookingState.active = false;
      bookingState.selectedSlot = '';
      bookingState.selectedDayKey = '';
      slotStartInput.value = '';
      form.hidden = true;
      resetFormWizard();
      setSubmitBusy(false);
    }

    function setSubmitBusy(isBusy) {
      if (!submitBtn) {
        return;
      }
      submitBtn.disabled = isBusy;
      submitBtn.setAttribute('aria-busy', isBusy ? 'true' : 'false');
      submitBtn.style.opacity = isBusy ? '0.7' : '';
      submitBtn.style.cursor = isBusy ? 'wait' : '';
    }

    function reserveSlot(formData) {
      setSubmitBusy(true);
      statusNode.textContent = restatifyBookingAssistant.strings.reserve + '...';

      var body = new URLSearchParams();
      body.set('action', 'restatify_booking_reserve_slot');
      body.set('nonce', restatifyBookingAssistant.nonce);
      body.set('name', formData.get('name') || '');
      body.set('email', formData.get('email') || '');
      body.set('subject', formData.get('subject') || '');
      body.set('note', formData.get('note') || '');
      body.set('contact_method', formData.get('contact_method') || 'phone');
      body.set('contact_value', formData.get('contact_value') || '');
      body.set('slot_start', slotStartInput.value);

      fetch(restatifyBookingAssistant.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString()
      }).then(function (response) {
        return response.json();
      }).then(function (data) {
        if (!data || !data.success) {
          setSubmitBusy(false);
          var message = data && data.data && data.data.message ? String(data.data.message) : restatifyBookingAssistant.strings.error;
          statusNode.textContent = message;
          return;
        }

        var payload = data && data.data ? data.data : {};
        bookingState.confirmed = true;
        sendChatEvent('confirmed', {
          startIso: payload.start_iso || slotStartInput.value,
          endIso: payload.end_iso || '',
          reference: payload.reference || ''
        });

        statusNode.textContent = restatifyBookingAssistant.strings.success;
        form.reset();
        setSelectedContactMethod(defaultContactMethod);
        syncContactField();
        resetFormWizard();
        form.hidden = true;

        autoCloseTimer = setTimeout(function () {
          closePopup(false);
        }, 1400);
      }).catch(function () {
        setSubmitBusy(false);
        statusNode.textContent = restatifyBookingAssistant.strings.error;
      });
    }

    function getWeekdayIndex(date) {
      var sundayFirst = date.getDay();
      if (!weekStartsMonday) {
        return sundayFirst;
      }
      return (sundayFirst + 6) % 7;
    }

    function getWeekdayLabels() {
      var baseMonday = new Date(Date.UTC(2024, 0, 1));
      var labels = [];

      for (var i = 0; i < 7; i += 1) {
        var day = new Date(baseMonday);
        day.setUTCDate(baseMonday.getUTCDate() + i);
        labels.push(day.toLocaleDateString(locale, { weekday: 'short' }));
      }

      if (weekStartsMonday) {
        return labels;
      }

      return [labels[6]].concat(labels.slice(0, 6));
    }

    function getDayKey(date) {
      var y = date.getFullYear();
      var m = String(date.getMonth() + 1).padStart(2, '0');
      var d = String(date.getDate()).padStart(2, '0');
      return y + '-' + m + '-' + d;
    }

    function startOfMonth(date) {
      return new Date(date.getFullYear(), date.getMonth(), 1);
    }

    function sendChatEvent(eventType, details) {
      var chatNonce = restatifyBookingAssistant.chatNonce || '';
      if (!chatNonce) {
        return;
      }

      var conversationId = '';
      var conversationToken = '';
      try {
        conversationId = String(window.localStorage.getItem('restatify_mco_chat_id') || '');
        conversationToken = String(window.localStorage.getItem('restatify_mco_chat_token') || '');
      } catch (error) {
        return;
      }

      if (!conversationId || !conversationToken) {
        return;
      }

      var body = new URLSearchParams();
      body.set('action', 'restatify_mco_booking_event');
      body.set('nonce', chatNonce);
      body.set('conversation_id', conversationId);
      body.set('conversation_token', conversationToken);
      body.set('event_type', eventType);
      body.set('start_iso', (details && details.startIso) ? String(details.startIso) : '');
      body.set('end_iso', (details && details.endIso) ? String(details.endIso) : '');
      body.set('reference', (details && details.reference) ? String(details.reference) : '');

      fetch(restatifyBookingAssistant.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString()
      }).catch(function () {
        return;
      });
    }

    if (wizardPrev) {
      wizardPrev.addEventListener('click', function () {
        bookingState.currentFormStep -= 1;
        updateFormWizard();
      });
    }

    if (wizardNext) {
      wizardNext.addEventListener('click', function () {
        if (!isStepValid(bookingState.currentFormStep)) {
          return;
        }
        bookingState.currentFormStep += 1;
        updateFormWizard();
      });
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!isStepValid(bookingState.currentFormStep)) {
        return;
      }
      reserveSlot(new FormData(form));
    });

    var contactButtons = getContactButtons();
    if (contactButtons.length > 0) {
      contactButtons.forEach(function (button) {
        button.addEventListener('click', function () {
          setSelectedContactMethod(button.getAttribute('data-method-key') || '');
          syncContactField();
        });
      });
      setSelectedContactMethod(defaultContactMethod);
      initContactToggle();
      syncContactField();
    }

    root.restatifyOpenPopup = openPopup;
    popupRoots.push(root);

    if (openBtn) {
      openBtn.addEventListener('click', openPopup);
    }
    closeBtn.addEventListener('click', function () {
      closePopup(true);
    });
    if (cancelBtn) {
      cancelBtn.addEventListener('click', function () {
        closePopup(true);
      });
    }

    overlay.addEventListener('click', function (event) {
      if (event.target === overlay) {
        closePopup(true);
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !overlay.hidden) {
        closePopup(true);
      }
    });

    updateFormWizard();
  }

  function openFromLink() {
    if (!popupRoots.length) {
      return;
    }

    var preferred = popupRoots.find(function (root) {
      return root.hasAttribute('data-booking-global');
    });
    var target = preferred || popupRoots[0];

    if (target && typeof target.restatifyOpenPopup === 'function') {
      target.restatifyOpenPopup();
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-restatify-booking]').forEach(initBookingPopup);

    document.addEventListener('restatify:booking-open', function () {
      openFromLink();
    });

    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('a[href="#restatify-booking"], a[href$="#restatify-booking"]');
      if (!trigger) {
        return;
      }

      event.preventDefault();
      openFromLink();
    });

    if (window.location.hash === '#restatify-booking') {
      openFromLink();
    }
  });
})();


