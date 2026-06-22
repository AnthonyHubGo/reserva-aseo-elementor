function raeInitReservationForm(form) {
  if (!form || form.dataset.raeInitialized === 'true') return;

  form.dataset.raeInitialized = 'true';

  const dateInput = form.querySelector('input[name="fecha"]');
  const jornadaInput = form.querySelector('select[name="jornada"]');
  const personalViewport = form.querySelector('.rae-personal-viewport');
  const personCards = Array.from(form.querySelectorAll('.rae-person-card'));
  const noPersonalDate = form.querySelector('.rae-no-personal-date');
  const noAvailabilityAlert = form.querySelector('.rae-no-availability-alert');
  const prevButton = form.querySelector('.rae-carousel-prev');
  const nextButton = form.querySelector('.rae-carousel-next');
  const submitButton = form.querySelector('button[type="submit"]');
  const dateConfig = window.raeReservaConfig || {};
  const today = dateConfig.today || new Date().toISOString().slice(0, 10);
  const holidays = dateConfig.holidays || {};
  let carouselPage = 0;

  function isInvalidReservationDate(date) {
    if (!date) {
      return 'Selecciona una fecha para la reserva.';
    }

    if (date < today) {
      return 'No puedes reservar una fecha anterior al día actual.';
    }

    if (holidays[date]) {
      return 'No puedes reservar en días festivos de Colombia.';
    }

    return '';
  }

  function getCardsPerPage() {
    const viewportWidth = personalViewport ? personalViewport.getBoundingClientRect().width : window.innerWidth;

    if (viewportWidth < 980) return 2;

    return 4;
  }

  function getVisibleCards() {
    return personCards.filter(card => card.dataset.availableForDate !== 'false');
  }

  function jornadaHasConflict(selectedJornada, occupiedJornadas) {
    if (!selectedJornada || !Array.isArray(occupiedJornadas)) {
      return false;
    }

    if (selectedJornada === 'completa') {
      return occupiedJornadas.length > 0;
    }

    return occupiedJornadas.includes('completa') || occupiedJornadas.includes(selectedJornada);
  }

  function updateCarousel() {
    const visibleCards = getVisibleCards();
    const cardsPerPage = getCardsPerPage();
    const maxPage = Math.max(0, Math.ceil(visibleCards.length / cardsPerPage) - 1);
    const shouldDisableArrows = visibleCards.length <= cardsPerPage;

    carouselPage = Math.min(carouselPage, maxPage);

    personCards.forEach(card => {
      card.hidden = true;
    });

    visibleCards.forEach((card, index) => {
      const isOnPage = index >= carouselPage * cardsPerPage && index < (carouselPage + 1) * cardsPerPage;

      card.hidden = !isOnPage;
    });

    if (prevButton) {
      prevButton.disabled = shouldDisableArrows || carouselPage === 0;
    }

    if (nextButton) {
      nextButton.disabled = shouldDisableArrows || carouselPage >= maxPage;
    }
  }

  function updateAvailableCards() {
    const selectedDate = dateInput ? dateInput.value : '';
    const selectedJornada = jornadaInput ? jornadaInput.value : '';
    let visibleCards = 0;

    personCards.forEach(card => {
      const input = card.querySelector('input[name="persona_id"]');
      let unavailableDates = [];
      let occupations = {};

      try {
        unavailableDates = JSON.parse(card.dataset.unavailableDates || '[]');
      } catch (error) {
        unavailableDates = [];
      }

      try {
        occupations = JSON.parse(card.dataset.occupations || '{}');
      } catch (error) {
        occupations = {};
      }

      const isUnavailable = selectedDate && unavailableDates.includes(selectedDate);
      const isOccupied = selectedDate && jornadaHasConflict(selectedJornada, occupations[selectedDate]);
      const shouldHideCard = isUnavailable || isOccupied;

      card.dataset.availableForDate = shouldHideCard ? 'false' : 'true';

      if (input) {
        input.disabled = shouldHideCard;

        if (shouldHideCard && input.checked) {
          input.checked = false;
        }
      }

      if (!shouldHideCard) {
        visibleCards += 1;
      }
    });

    const hasNoAvailability = visibleCards === 0;

    if (noAvailabilityAlert) {
      noAvailabilityAlert.hidden = !hasNoAvailability;
    }

    if (noPersonalDate) {
      noPersonalDate.hidden = true;
    }

    if (submitButton) {
      submitButton.disabled = hasNoAvailability;
    }

    carouselPage = 0;
    updateCarousel();
  }

  if (dateInput) {
    if (window.flatpickr) {
      window.flatpickr(dateInput, {
        dateFormat: 'Y-m-d',
        minDate: today,
        disable: Object.keys(holidays),
      });
    } else {
      dateInput.setAttribute('min', today);
    }

    dateInput.addEventListener('change', updateAvailableCards);
    updateAvailableCards();
  }

  if (jornadaInput) {
    jornadaInput.addEventListener('change', updateAvailableCards);
  }

  if (prevButton) {
    prevButton.addEventListener('click', function () {
      carouselPage = Math.max(0, carouselPage - 1);
      updateCarousel();
    });
  }

  if (nextButton) {
    nextButton.addEventListener('click', function () {
      carouselPage += 1;
      updateCarousel();
    });
  }

  window.addEventListener('resize', updateCarousel);

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const msg = form.querySelector('#rae-msg');
    const data = new FormData(form);
    const dateError = isInvalidReservationDate(data.get('fecha'));
    const hasNoAvailability = submitButton && submitButton.disabled;

    if (dateError) {
      if (msg) {
        msg.innerText = dateError;
      }

      return;
    }

    if (hasNoAvailability) {
      if (msg) {
        msg.innerText = 'No se puede reservar este día porque no hay personal disponible.';
      }

      return;
    }

    data.append('action', 'rae_guardar_reserva');

    if (window.rae_ajax && window.rae_ajax.nonce) {
      data.append('nonce', window.rae_ajax.nonce);
    }

    if (msg) {
      msg.innerText = 'Guardando reserva...';
    }

    fetch(rae_ajax.ajax_url, {
      method: 'POST',
      body: data,
    })
      .then(response => response.json())
      .then(result => {
        const resultData = result.data || {};
        const message = typeof resultData === 'string' ? resultData : resultData.message;

        if (msg) {
          msg.innerText = message || '';
        }

        if (result.success) {
          form.reset();
          updateAvailableCards();

          if (resultData.payment_url) {
            window.location.href = resultData.payment_url;
          }
        }
      })
      .catch(() => {
        if (msg) {
          msg.innerText = 'Ocurrió un error al enviar la reserva.';
        }
      });
  });
}

function raeInitReservationForms(root) {
  const scope = root || document;
  const forms = scope.querySelectorAll ? scope.querySelectorAll('.rae-form') : [];

  if (scope.matches && scope.matches('.rae-form')) {
    raeInitReservationForm(scope);
  }

  forms.forEach(raeInitReservationForm);
}

document.addEventListener('DOMContentLoaded', function () {
  raeInitReservationForms(document);
});

window.addEventListener('load', function () {
  raeInitReservationForms(document);
});

function raeRegisterElementorReservationHook() {
  if (!window.elementorFrontend || !window.elementorFrontend.hooks) return;
  if (window.raeReservationElementorHookRegistered) return;

  window.raeReservationElementorHookRegistered = true;

  window.elementorFrontend.hooks.addAction('frontend/element_ready/reserva_aseo.default', function ($scope) {
    const scope = $scope && $scope[0] ? $scope[0] : document;

    raeInitReservationForms(scope);
  });
}

raeRegisterElementorReservationHook();
window.addEventListener('elementor/frontend/init', raeRegisterElementorReservationHook);
