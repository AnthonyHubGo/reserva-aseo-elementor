document.addEventListener('DOMContentLoaded', function () {
  const textarea = document.getElementById('rae_fechas_no_disponibles');
  const startDateInput = document.getElementById('rae_fecha_no_disponible_inicio');
  const endDateInput = document.getElementById('rae_fecha_no_disponible_fin');
  const addButton = document.getElementById('rae_agregar_fecha_no_disponible');
  const prevButton = document.getElementById('rae_calendario_mes_anterior');
  const nextButton = document.getElementById('rae_calendario_mes_siguiente');
  const monthLabel = document.getElementById('rae_calendario_mes_actual');
  const availabilityFilter = document.getElementById('rae_calendario_filtro_disponibilidad');
  const calendar = document.getElementById('rae_calendario_disponibilidad');

  if (
    !textarea ||
    !startDateInput ||
    !endDateInput ||
    !addButton ||
    !prevButton ||
    !nextButton ||
    !monthLabel ||
    !availabilityFilter ||
    !calendar
  ) {
    return;
  }

  const monthFormatter = new Intl.DateTimeFormat('es-CO', {
    month: 'long',
    year: 'numeric',
  });
  const weekdays = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
  const initialDates = getDates();
  let viewedMonth = initialDates.length ? createDate(initialDates[0]) : new Date();

  viewedMonth = new Date(viewedMonth.getFullYear(), viewedMonth.getMonth(), 1);

  function getDates() {
    return textarea.value
      .split(/\r?\n/)
      .map(date => date.trim())
      .filter(Boolean);
  }

  function setDates(dates) {
    const uniqueDates = Array.from(new Set(dates)).sort();

    textarea.value = uniqueDates.join('\n');
    renderCalendar();
  }

  function createDate(date) {
    const parts = date.split('-').map(Number);

    return new Date(parts[0], parts[1] - 1, parts[2]);
  }

  function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
  }

  function getDateRange(startDate, endDate) {
    const dates = [];
    const currentDate = createDate(startDate);
    const lastDate = createDate(endDate || startDate);

    if (lastDate < currentDate) {
      return [];
    }

    while (currentDate <= lastDate) {
      dates.push(formatDate(currentDate));
      currentDate.setDate(currentDate.getDate() + 1);
    }

    return dates;
  }

  function getCalendarStartDate() {
    const firstDay = new Date(viewedMonth.getFullYear(), viewedMonth.getMonth(), 1);
    const mondayOffset = (firstDay.getDay() + 6) % 7;

    firstDay.setDate(firstDay.getDate() - mondayOffset);

    return firstDay;
  }

  function renderCalendar() {
    const unavailableDates = getDates();
    const unavailableSet = new Set(unavailableDates);
    const filter = availabilityFilter.value;
    const calendarStart = getCalendarStartDate();

    calendar.innerHTML = '';
    monthLabel.textContent = monthFormatter.format(viewedMonth);

    weekdays.forEach(weekday => {
      const item = document.createElement('div');

      item.className = 'rae-admin-calendar-weekday';
      item.textContent = weekday;
      calendar.appendChild(item);
    });

    for (let index = 0; index < 42; index += 1) {
      const currentDate = new Date(calendarStart);

      currentDate.setDate(calendarStart.getDate() + index);

      const button = document.createElement('button');
      const dateText = document.createElement('span');
      const formattedDate = formatDate(currentDate);
      const isCurrentMonth = currentDate.getMonth() === viewedMonth.getMonth();
      const isUnavailable = unavailableSet.has(formattedDate);
      const availability = isUnavailable ? 'no_disponible' : 'disponible';
      const isFilteredOut = availability !== filter;

      button.type = 'button';
      button.className = [
        'rae-admin-calendar-day',
        isCurrentMonth ? '' : 'is-other-month',
        isUnavailable ? 'is-unavailable' : 'is-available',
        isFilteredOut ? 'is-filtered-out' : '',
      ].filter(Boolean).join(' ');
      button.setAttribute('aria-label', `${formattedDate} ${isUnavailable ? 'no disponible' : 'disponible'}`);
      button.addEventListener('click', function () {
        if (isUnavailable) {
          setDates(unavailableDates.filter(date => date !== formattedDate));
          return;
        }

        setDates([...unavailableDates, formattedDate]);
      });

      dateText.textContent = String(currentDate.getDate());
      button.appendChild(dateText);
      calendar.appendChild(button);
    }
  }

  addButton.addEventListener('click', function () {
    if (!startDateInput.value) return;

    const datesToAdd = getDateRange(startDateInput.value, endDateInput.value);

    if (!datesToAdd.length) return;

    const startDate = createDate(startDateInput.value);

    viewedMonth = new Date(startDate.getFullYear(), startDate.getMonth(), 1);
    setDates([...getDates(), ...datesToAdd]);
    startDateInput.value = '';
    endDateInput.value = '';
    startDateInput.focus();
  });

  prevButton.addEventListener('click', function () {
    viewedMonth.setMonth(viewedMonth.getMonth() - 1);
    renderCalendar();
  });

  nextButton.addEventListener('click', function () {
    viewedMonth.setMonth(viewedMonth.getMonth() + 1);
    renderCalendar();
  });

  availabilityFilter.addEventListener('change', renderCalendar);

  [startDateInput, endDateInput].forEach(input => input.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter') return;

    event.preventDefault();
    addButton.click();
  }));

  renderCalendar();
});
