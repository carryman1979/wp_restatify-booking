(function () {
  function initBookingPopup(root) {
    var openBtn = root.querySelector('[data-booking-open]');
    var closeBtn = root.querySelector('[data-booking-close]');
    var overlay = root.querySelector('[data-booking-overlay]');
    var statusNode = root.querySelector('[data-booking-status]');
    var slotsNode = root.querySelector('[data-booking-slots]');
    var form = root.querySelector('[data-booking-form]');
    var slotStartInput = root.querySelector('[data-slot-start]');
    var slotEndInput = root.querySelector('[data-slot-end]');

    if (!openBtn || !closeBtn || !overlay || !statusNode || !slotsNode || !form || !slotStartInput || !slotEndInput) {
      return;
    }

    openBtn.addEventListener('click', function () {
      overlay.hidden = false;
      form.hidden = true;
      statusNode.textContent = restatifyBookingAssistant.strings.loading;
      slotsNode.innerHTML = '';
      loadSlots();
    });

    closeBtn.addEventListener('click', function () {
      overlay.hidden = true;
    });

    overlay.addEventListener('click', function (event) {
      if (event.target === overlay) {
        overlay.hidden = true;
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
            slotEndInput.value = slot.end_iso || '';
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
      body.set('slot_end', slotEndInput.value);

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

        statusNode.textContent = restatifyBookingAssistant.strings.success;
        form.reset();
        form.hidden = true;
      }).catch(function () {
        statusNode.textContent = restatifyBookingAssistant.strings.error;
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-restatify-booking]').forEach(initBookingPopup);
  });
})();
