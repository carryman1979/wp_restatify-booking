(function () {
  var popupRoots = [];

  function initBookingPopup(root) {
    var openBtn = root.querySelector('[data-booking-open]');
    var closeBtn = root.querySelector('[data-booking-close]');
    var cancelBtn = root.querySelector('[data-booking-cancel]');
    var overlay = root.querySelector('[data-booking-overlay]');
    var statusNode = root.querySelector('[data-booking-status]');
    var slotsNode = root.querySelector('[data-booking-slots]');
    var form = root.querySelector('[data-booking-form]');
    var slotStartInput = root.querySelector('[data-slot-start]');

    var bookingState = {
      active: false,
      selectedSlot: '',
      confirmed: false
    };

    if (!closeBtn || !overlay || !statusNode || !slotsNode || !form || !slotStartInput) {
      return;
    }

    function openPopup() {
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
        var slots = data && data.success && data.data && Array.isArray(data.data.slots) ? data.data.slots : [];
        if (!slots.length) {
          statusNode.textContent = restatifyBookingAssistant.strings.empty;
          return;
        }

        statusNode.textContent = '';
        slotsNode.innerHTML = '';

        slots.forEach(function (slot) {
          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'restatify-booking__slot';
          button.textContent = new Date(slot.start_iso).toLocaleString();
          button.addEventListener('click', function () {
            slotStartInput.value = slot.start_iso;
            bookingState.selectedSlot = slot.start_iso;
            form.hidden = false;
            statusNode.textContent = restatifyBookingAssistant.strings.reserve + ': ' + button.textContent;
          });
          slotsNode.appendChild(button);
        });
      }).catch(function () {
        statusNode.textContent = restatifyBookingAssistant.strings.error;
      });
    }

    function reserveSlot(formData) {
      var body = new URLSearchParams();
      body.set('action', 'restatify_booking_reserve_slot');
      body.set('nonce', restatifyBookingAssistant.nonce);
      body.set('name', formData.get('name') || '');
      body.set('email', formData.get('email') || '');
      body.set('note', formData.get('note') || '');
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
          statusNode.textContent = restatifyBookingAssistant.strings.error;
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
        form.hidden = true;
      }).catch(function () {
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
