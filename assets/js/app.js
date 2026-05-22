function raeInitReservationForm(form) {
  if (!form || form.dataset.raeInitialized === 'true') return;

  form.dataset.raeInitialized = 'true';

  const dateInput = form.querySelector('input[name="fecha"]');
  const personalViewport = form.querySelector('.rae-personal-viewport');
  const personCards = Array.from(form.querySelectorAll('.rae-person-card'));
  const noPersonalDate = form.querySelector('.rae-no-personal-date');
  const prevButton = form.querySelector('.rae-carousel-prev');
  const nextButton = form.querySelector('.rae-carousel-next');
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

      card.dataset.availableForDate = isUnavailable ? 'false' : 'true';

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

    if (dateError) {
      if (msg) {
        msg.innerText = dateError;
      }

      return;
    }

    data.append('action', 'rae_guardar_reserva');

    if (msg) {
      msg.innerText = 'Guardando reserva...';
    }

    fetch(rae_ajax.ajax_url, {
      method: 'POST',
      body: data,
    })
      .then(response => response.json())
      .then(result => {
        if (msg) {
          msg.innerText = result.data;
        }

        if (result.success) {
          form.reset();
          updateAvailableCards();
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
