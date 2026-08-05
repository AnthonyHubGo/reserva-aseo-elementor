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
  const stepPanels = Array.from(form.querySelectorAll('[data-step-panel]'));
  const stepIndicators = Array.from(form.querySelectorAll('[data-step-indicator]'));
  const nextStepButtons = Array.from(form.querySelectorAll('[data-next-step]'));
  const previousStepButtons = Array.from(form.querySelectorAll('[data-previous-step]'));
  const msg = form.querySelector('#rae-msg');
  const dateConfig = window.raeReservaConfig || {};
  const today = dateConfig.today || new Date().toISOString().slice(0, 10);
  const holidays = dateConfig.holidays || {};
  let carouselPage = 0;
  let currentStep = 1;

  function setMessage(message) {
    if (msg) {
      msg.innerText = message || '';
    }
  }

  function showStep(step, moveFocus = true) {
    const nextStep = Math.min(3, Math.max(1, Number(step) || 1));

    currentStep = nextStep;

    stepPanels.forEach(panel => {
      const panelStep = Number(panel.dataset.stepPanel);
      const isActive = panelStep === currentStep;

      panel.hidden = !isActive;
      panel.classList.toggle('is-active', isActive);
    });

    stepIndicators.forEach(indicator => {
      const indicatorStep = Number(indicator.dataset.stepIndicator);
      const isActive = indicatorStep === currentStep;

      indicator.classList.toggle('is-active', isActive);
      indicator.classList.toggle('is-complete', indicatorStep < currentStep);

      if (isActive) {
        indicator.setAttribute('aria-current', 'step');
      } else {
        indicator.removeAttribute('aria-current');
      }
    });

    setMessage('');

    const activePanel = stepPanels.find(panel => Number(panel.dataset.stepPanel) === currentStep);
    const heading = activePanel ? activePanel.querySelector('h3') : null;

    if (moveFocus && heading && typeof heading.focus === 'function') {
      heading.setAttribute('tabindex', '-1');
      heading.focus({ preventScroll: true });
    }

    if (moveFocus) {
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    updateCarousel();
  }

  function getInvalidControl(panel) {
    if (!panel) return null;

    return Array.from(panel.querySelectorAll('input, select, textarea'))
      .find(control => !control.disabled && !control.checkValidity()) || null;
  }

  function validateStep(step) {
    const panel = stepPanels.find(item => Number(item.dataset.stepPanel) === Number(step));
    const invalidControl = getInvalidControl(panel);

    if (invalidControl) {
      invalidControl.reportValidity();
      invalidControl.focus({ preventScroll: false });
      return false;
    }

    if (Number(step) === 2) {
      const dateError = isInvalidReservationDate(dateInput ? dateInput.value : '');

      if (dateError) {
        setMessage(dateError);

        if (dateInput) {
          dateInput.focus();
        }

        return false;
      }

      if (submitButton && submitButton.disabled) {
        setMessage('No se puede continuar porque no hay personal disponible para esta fecha y jornada.');
        return false;
      }
    }

    return true;
  }

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
    if (window.matchMedia('(max-width: 520px)').matches) return 1;
    if (window.matchMedia('(max-width: 800px)').matches) return 2;

    return 3;
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

  nextStepButtons.forEach(button => {
    button.addEventListener('click', function () {
      if (!validateStep(currentStep)) return;

      showStep(Number(button.dataset.nextStep));
    });
  });

  previousStepButtons.forEach(button => {
    button.addEventListener('click', function () {
      showStep(Number(button.dataset.previousStep));
    });
  });

  window.addEventListener('resize', updateCarousel);

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    for (let step = 1; step <= 3; step += 1) {
      const panel = stepPanels.find(item => Number(item.dataset.stepPanel) === step);
      const invalidControl = getInvalidControl(panel);

      if (invalidControl) {
        showStep(step);
        invalidControl.reportValidity();
        invalidControl.focus({ preventScroll: false });
        return;
      }

      if (step === 2) {
        const dateError = isInvalidReservationDate(dateInput ? dateInput.value : '');

        if (dateError || (submitButton && submitButton.disabled)) {
          showStep(step);
          setMessage(dateError || 'No se puede continuar porque no hay personal disponible para esta fecha y jornada.');

          if (dateError && dateInput) {
            dateInput.focus();
          }

          return;
        }
      }
    }

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

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.setAttribute('aria-busy', 'true');
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
          } else {
            showStep(1);
          }
        } else if (submitButton) {
          submitButton.disabled = false;
          submitButton.removeAttribute('aria-busy');
        }
      })
      .catch(() => {
        if (msg) {
          msg.innerText = 'Ocurrió un error al enviar la reserva.';
        }

        if (submitButton) {
          submitButton.disabled = false;
          submitButton.removeAttribute('aria-busy');
        }
      });
  });

  showStep(1, false);
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
