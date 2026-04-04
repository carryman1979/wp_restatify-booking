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
    var slotsNode = root.querySelector('[data-booking-slots]');
    var form = root.querySelector('[data-booking-form]');
    var slotStartInput = root.querySelector('[data-slot-start]');
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
      shouldScrollToTimes: false,
      currentFormStep: 0
    };

    if (!closeBtn || !overlay || !statusNode || !slotsNode || !form || !slotStartInput) {
      return;
    }

    function stepCount() {
      return wizardSteps ? wizardSteps.length : 0;
    }

    function validateCurrentStep() {
      if (!wizardSteps || !wizardSteps.length) {
        return true;
      }

      var current = wizardSteps[bookingState.currentFormStep];
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

    if (wizardPrev) {
      wizardPrev.addEventListener('click', function () {
        bookingState.currentFormStep -= 1;
        updateFormWizard();
      });
    }

    if (wizardNext) {
      wizardNext.addEventListener('click', function () {
        if (!validateCurrentStep()) {
          return;
        }

        bookingState.currentFormStep += 1;
        updateFormWizard();
      });
    }

    updateFormWizard();

    function getContactButtons() {
      if (!contactChannelsNode) {
        return [];
      }

      return Array.prototype.slice.call(contactChannelsNode.querySelectorAll('[data-contact-channel]'));
    }

    function getSelectedContactButton() {
      var buttons = getContactButtons();
      var selected = buttons.find(function (button) {
        return button.classList.contains('is-selected');
      });

      return selected || buttons[0] || null;
    }

    function setSelectedContactMethod(methodKey) {
      var buttons = getContactButtons();
      if (!buttons.length) {
        return;
      }

      var fallback = buttons[0];
      var selected = fallback;
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

      var selected = getSelectedContactButton();
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

    function initContactChannelToggle() {
      if (!contactChannelsNode || !contactChannelsToggle) {
        return;
      }

      var moreLabel = String(contactChannelsToggle.getAttribute('data-label-more') || 'More...');
      var lessLabel = String(contactChannelsToggle.getAttribute('data-label-less') || 'Less');

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

    var contactButtons = getContactButtons();
    if (contactButtons.length > 0) {
      contactButtons.forEach(function (button) {
        button.addEventListener('click', function () {
          setSelectedContactMethod(button.getAttribute('data-method-key') || '');
          syncContactField();
        });
      });

      setSelectedContactMethod(defaultContactMethod);
      initContactChannelToggle();
      syncContactField();
    }

    function openPopup() {
      if (autoCloseTimer) {
        clearTimeout(autoCloseTimer);
        autoCloseTimer = null;
      }

      overlay.hidden = false;
      form.hidden = true;
      statusNode.textContent = restatifyBookingAssistant.strings.loading;
      slotsNode.innerHTML = '';
      bookingState.active = true;
      bookingState.selectedSlot = '';
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

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      reserveSlot(new FormData(form));
    });

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
          return;
        }

        var slots = data && data.success && data.data && Array.isArray(data.data.slots) ? data.data.slots : [];
        if (!slots.length) {
          statusNode.textContent = restatifyBookingAssistant.strings.empty;
          return;
        }

        bookingState.slots = slots.slice();
        bookingState.selectedDayKey = getDayKey(new Date(slots[0].start_iso));
        bookingState.currentMonth = startOfMonth(new Date(slots[0].start_iso));
        statusNode.textContent = restatifyBookingAssistant.strings.selectDay || '';

        renderCalendarAndSlots();
      }).catch(function () {
        statusNode.textContent = restatifyBookingAssistant.strings.error;
      });
    }

    function renderCalendarAndSlots() {
      slotsNode.innerHTML = '';

      var wrapper = document.createElement('div');
      wrapper.className = 'restatify-booking__calendar-wrap';

      var calendar = document.createElement('div');
      calendar.className = 'restatify-booking__calendar';
      renderCalendar(calendar);

      var times = document.createElement('div');
      times.className = 'restatify-booking__times';
      renderDayTimes(times);

      wrapper.appendChild(calendar);
      wrapper.appendChild(times);
      slotsNode.appendChild(wrapper);

      if (bookingState.shouldScrollToTimes) {
        bookingState.shouldScrollToTimes = false;
        window.requestAnimationFrame(function () {
          times.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      }
    }

    function renderCalendar(container) {
      container.innerHTML = '';

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
        renderCalendarAndSlots();
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
        renderCalendarAndSlots();
      });

      header.appendChild(prev);
      header.appendChild(title);
      header.appendChild(next);
      container.appendChild(header);

      var weekdayRow = document.createElement('div');
      weekdayRow.className = 'restatify-booking__calendar-weekdays';
      getWeekdayLabels().forEach(function (wd) {
        var node = document.createElement('span');
        node.textContent = wd;
        weekdayRow.appendChild(node);
      });
      container.appendChild(weekdayRow);

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
          cell.addEventListener('click', function (clickedKey) {
            return function () {
              bookingState.selectedDayKey = clickedKey;
              bookingState.selectedSlot = '';
              bookingState.shouldScrollToTimes = true;
              slotStartInput.value = '';
              form.hidden = true;
              resetFormWizard();
              statusNode.textContent = restatifyBookingAssistant.strings.pickTime || restatifyBookingAssistant.strings.reserve;
              renderCalendarAndSlots();
            };
          }(dayKey));
        }

        grid.appendChild(cell);
      }

      container.appendChild(grid);
    }

    function renderDayTimes(container) {
      container.innerHTML = '';

      var heading = document.createElement('p');
      heading.className = 'restatify-booking__times-heading';
      heading.textContent = restatifyBookingAssistant.strings.pickTime || 'Select a time';
      container.appendChild(heading);

      var selectedDay = bookingState.selectedDayKey;
      var daySlots = getSlotsForDay(selectedDay);
      if (!daySlots.length) {
        var empty = document.createElement('p');
        empty.className = 'restatify-booking__times-empty';
        empty.textContent = restatifyBookingAssistant.strings.empty;
        container.appendChild(empty);
        return;
      }

      daySlots.forEach(function (slot) {
        var startDate = new Date(slot.start_iso);
        var label = startDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'restatify-booking__slot';
        button.textContent = label;
        button.addEventListener('click', function () {
          slotStartInput.value = slot.start_iso;
          bookingState.selectedSlot = slot.start_iso;
          form.hidden = false;
          resetFormWizard();
          statusNode.textContent = restatifyBookingAssistant.strings.reserve + ': ' + startDate.toLocaleString();
        });

        container.appendChild(button);
      });
    }

    function getSlotsForDay(dayKey) {
      return bookingState.slots.filter(function (slot) {
        return getDayKey(new Date(slot.start_iso)) === dayKey;
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
      var baseMonday = new Date(Date.UTC(2024, 0, 1)); // Monday
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

    function reserveSlot(formData) {
      setSubmitBusy(true);
      statusNode.textContent = restatifyBookingAssistant.strings.reserve + '...';

      var body = new URLSearchParams();
      body.set('action', 'restatify_booking_reserve_slot');
      body.set('nonce', restatifyBookingAssistant.nonce);
      body.set('name', formData.get('name') || '');
      body.set('email', formData.get('email') || '');
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
