document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('rae-form');

  if (!form) return;

  const dateInput = form.querySelector('input[name="fecha"]');
  const personCards = Array.from(form.querySelectorAll('.rae-person-card'));
  const noPersonalDate = form.querySelector('.rae-no-personal-date');

  function updateAvailableCards() {
    const selectedDate = dateInput ? dateInput.value : '';
    let visibleCards = 0;

    personCards.forEach(card => {
      const input = card.querySelector('input[name="persona_id"]');
      let unavailableDates = [];

      try {
        unavailableDates = JSON.parse(card.dataset.unavailableDates || '[]');
      } catch (error) {
        unavailableDates = [];
      }

      const isUnavailable = selectedDate && unavailableDates.includes(selectedDate);

      card.hidden = isUnavailable;

      if (input) {
        input.disabled = isUnavailable;

        if (isUnavailable && input.checked) {
          input.checked = false;
        }
      }

      if (!isUnavailable) {
        visibleCards += 1;
      }
    });

    if (noPersonalDate) {
      noPersonalDate.hidden = visibleCards > 0;
    }
  }

  if (dateInput) {
    dateInput.addEventListener('change', updateAvailableCards);
    updateAvailableCards();
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const msg = document.getElementById('rae-msg');
    const data = new FormData(form);

    data.append('action', 'rae_guardar_reserva');

    msg.innerText = 'Guardando reserva...';

    fetch(rae_ajax.ajax_url, {
      method: 'POST',
      body: data,
    })
      .then(response => response.json())
      .then(result => {
        msg.innerText = result.data;

        if (result.success) {
          form.reset();
          updateAvailableCards();
        }
      })
      .catch(() => {
        msg.innerText = 'Ocurrió un error al enviar la reserva.';
      });
  });
});
