document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('rae-form');

  if (!form) return;

  const dateInput = form.querySelector('input[name="fecha"]');
  const personCards = Array.from(form.querySelectorAll('.rae-person-card'));
  const noPersonalDate = form.querySelector('.rae-no-personal-date');
  const prevButton = form.querySelector('.rae-carousel-prev');
  const nextButton = form.querySelector('.rae-carousel-next');
  let carouselPage = 0;

  function getCardsPerPage() {
    if (window.matchMedia('(max-width: 640px)').matches) return 1;
    if (window.matchMedia('(max-width: 900px)').matches) return 2;

    return 4;
  }

  function getVisibleCards() {
    return personCards.filter(card => card.dataset.availableForDate !== 'false');
  }

  function updateCarousel() {
    const visibleCards = getVisibleCards();
    const cardsPerPage = getCardsPerPage();
    const maxPage = Math.max(0, Math.ceil(visibleCards.length / cardsPerPage) - 1);
    const shouldDisableArrows = visibleCards.length <= 4;

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
