document.addEventListener('DOMContentLoaded', function () {
  const textarea = document.getElementById('rae_fechas_no_disponibles');
  const reasonsTextarea = document.getElementById('rae_motivos_no_disponibilidad_fechas');
  const occupationsInput = document.getElementById('rae_ocupaciones_personal');
  const startDateInput = document.getElementById('rae_fecha_no_disponible_inicio');
  const endDateInput = document.getElementById('rae_fecha_no_disponible_fin');
  const addButton = document.getElementById('rae_agregar_fecha_no_disponible');
  const prevButton = document.getElementById('rae_calendario_mes_anterior');
  const nextButton = document.getElementById('rae_calendario_mes_siguiente');
  const monthLabel = document.getElementById('rae_calendario_mes_actual');
  const reasonFilter = document.getElementById('rae_calendario_filtro_motivo');
  const calendar = document.getElementById('rae_calendario_disponibilidad');
  const reasonInputs = Array.from(document.querySelectorAll('input[name="rae_disponibilidad_motivo"]'));

  if (
    !textarea ||
    !reasonsTextarea ||
    !occupationsInput ||
    !startDateInput ||
    !endDateInput ||
    !addButton ||
    !prevButton ||
    !nextButton ||
    !monthLabel ||
    !reasonFilter ||
    !calendar
  ) {
    return;
  }

  const monthFormatter = new Intl.DateTimeFormat('es-CO', {
    month: 'long',
    year: 'numeric',
  });
  const weekdays = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
  const reasonLabels = {
    vacaciones: 'Vacaciones',
    incapacidad: 'Incapacidad',
    licencia_maternidad: 'Licencia de Maternidad',
  };
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
    const currentReasons = getReasons();
    const nextReasons = {};

    textarea.value = uniqueDates.join('\n');
    dispatchFieldChange(textarea);

    uniqueDates.forEach(date => {
      nextReasons[date] = currentReasons[date] || getSelectedReason();
    });

    setReasons(nextReasons);
    renderCalendar();
  }

  function getReasons() {
    try {
      const reasons = JSON.parse(reasonsTextarea.value || '{}');

      return reasons && typeof reasons === 'object' && !Array.isArray(reasons) ? reasons : {};
    } catch (error) {
      return {};
    }
  }

  function getOccupations() {
    try {
      const occupations = JSON.parse(occupationsInput.value || '{}');

      return occupations && typeof occupations === 'object' && !Array.isArray(occupations) ? occupations : {};
    } catch (error) {
      return {};
    }
  }

  function getOccupationDetails(date) {
    const occupations = getOccupations();
    const dateOccupations = Array.isArray(occupations[date]) ? occupations[date] : [];
    const hasFullDay = dateOccupations.includes('completa');
    const hasMorning = dateOccupations.includes('manana');
    const hasAfternoon = dateOccupations.includes('tarde');

    if (hasFullDay) {
      return {
        label: 'Jornada Completa',
        className: 'is-occupied-full',
      };
    }

    if (hasMorning && hasAfternoon) {
      return {
        label: 'Mañana y Tarde',
        className: 'is-occupied-full',
      };
    }

    if (hasMorning) {
      return {
        label: 'Mañana',
        className: 'is-occupied-morning',
      };
    }

    if (hasAfternoon) {
      return {
        label: 'Tarde',
        className: 'is-occupied-afternoon',
      };
    }

    return null;
  }

  function addDays(date, days) {
    const nextDate = new Date(date);

    nextDate.setDate(nextDate.getDate() + days);

    return nextDate;
  }

  function moveToNextMonday(date) {
    const nextDate = new Date(date);
    const day = nextDate.getDay();

    if (day !== 1) {
      nextDate.setDate(nextDate.getDate() + ((8 - day) % 7));
    }

    return nextDate;
  }

  function getEasterDate(year) {
    const a = year % 19;
    const b = Math.floor(year / 100);
    const c = year % 100;
    const d = Math.floor(b / 4);
    const e = b % 4;
    const f = Math.floor((b + 8) / 25);
    const g = Math.floor((b - f + 1) / 3);
    const h = (19 * a + b - d - g + 15) % 30;
    const i = Math.floor(c / 4);
    const k = c % 4;
    const l = (32 + 2 * e + 2 * i - h - k) % 7;
    const m = Math.floor((a + 11 * h + 22 * l) / 451);
    const month = Math.floor((h + l - 7 * m + 114) / 31);
    const day = ((h + l - 7 * m + 114) % 31) + 1;

    return new Date(year, month - 1, day);
  }

  function getColombianHolidays(year) {
    const easter = getEasterDate(year);
    const fixedHolidays = [
      ['Año Nuevo', new Date(year, 0, 1)],
      ['Día del Trabajo', new Date(year, 4, 1)],
      ['Independencia de Colombia', new Date(year, 6, 20)],
      ['Batalla de Boyacá', new Date(year, 7, 7)],
      ['Inmaculada Concepción', new Date(year, 11, 8)],
      ['Navidad', new Date(year, 11, 25)],
      ['Jueves Santo', addDays(easter, -3)],
      ['Viernes Santo', addDays(easter, -2)],
    ];
    const movedHolidays = [
      ['Reyes Magos', new Date(year, 0, 6)],
      ['San José', new Date(year, 2, 19)],
      ['San Pedro y San Pablo', new Date(year, 5, 29)],
      ['Asunción de la Virgen', new Date(year, 7, 15)],
      ['Día de la Raza', new Date(year, 9, 12)],
      ['Todos los Santos', new Date(year, 10, 1)],
      ['Independencia de Cartagena', new Date(year, 10, 11)],
      ['Ascensión del Señor', addDays(easter, 39)],
      ['Corpus Christi', addDays(easter, 60)],
      ['Sagrado Corazón', addDays(easter, 68)],
    ];
    const holidays = {};

    fixedHolidays.forEach(([label, date]) => {
      holidays[formatDate(date)] = label;
    });

    movedHolidays.forEach(([label, date]) => {
      holidays[formatDate(moveToNextMonday(date))] = label;
    });

    return holidays;
  }

  function setReasons(reasons) {
    reasonsTextarea.value = JSON.stringify(reasons);
    dispatchFieldChange(reasonsTextarea);
  }

  function getSelectedReason() {
    const selectedReason = reasonInputs.find(input => input.checked);

    return selectedReason ? selectedReason.value : 'vacaciones';
  }

  function dispatchFieldChange(field) {
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
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
    const reasons = getReasons();
    const filter = reasonFilter.value;
    const calendarStart = getCalendarStartDate();
    const holidays = {
      ...getColombianHolidays(calendarStart.getFullYear()),
      ...getColombianHolidays(viewedMonth.getFullYear()),
      ...getColombianHolidays(addDays(calendarStart, 41).getFullYear()),
    };

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
      const reasonText = document.createElement('small');
      const formattedDate = formatDate(currentDate);
      const isCurrentMonth = currentDate.getMonth() === viewedMonth.getMonth();
      const isUnavailable = unavailableSet.has(formattedDate);
      const reason = reasons[formattedDate] || getSelectedReason();
      const isFilteredOut = isUnavailable && filter !== 'todos' && reason !== filter;
      const occupation = getOccupationDetails(formattedDate);
      const holiday = holidays[formattedDate] || '';

      button.type = 'button';
      button.className = [
        'rae-admin-calendar-day',
        isCurrentMonth ? '' : 'is-other-month',
        holiday ? 'is-holiday' : '',
        isUnavailable ? 'is-unavailable' : 'is-available',
        occupation ? 'is-occupied' : '',
        occupation ? occupation.className : '',
        isFilteredOut ? 'is-filtered-out' : '',
      ].filter(Boolean).join(' ');
      button.setAttribute(
        'aria-label',
        `${formattedDate} ${holiday ? `festivo ${holiday}` : ''} ${occupation ? occupation.label : (isUnavailable ? reasonLabels[reason] : 'disponible')}`
      );
      button.addEventListener('click', function () {
        const currentReasons = getReasons();

        if (isUnavailable) {
          delete currentReasons[formattedDate];
          setReasons(currentReasons);
          setDates(unavailableDates.filter(date => date !== formattedDate));
          return;
        }

        currentReasons[formattedDate] = getSelectedReason();
        setReasons(currentReasons);
        setDates([...unavailableDates, formattedDate]);
      });

      dateText.textContent = String(currentDate.getDate());
      button.appendChild(dateText);

      if (isUnavailable) {
        reasonText.textContent = reasonLabels[reason] || reason;
        button.appendChild(reasonText);
      }

      if (holiday) {
        const holidayText = document.createElement('small');

        holidayText.className = 'rae-admin-calendar-holiday';
        holidayText.textContent = holiday;
        button.appendChild(holidayText);
      }

      if (occupation) {
        const occupationText = document.createElement('small');

        occupationText.className = 'rae-admin-calendar-occupation';
        occupationText.textContent = occupation.label;
        button.appendChild(occupationText);
      }

      calendar.appendChild(button);
    }
  }

  addButton.addEventListener('click', function () {
    if (!startDateInput.value) return;

    const datesToAdd = getDateRange(startDateInput.value, endDateInput.value);

    if (!datesToAdd.length) return;

    const startDate = createDate(startDateInput.value);

    viewedMonth = new Date(startDate.getFullYear(), startDate.getMonth(), 1);
    const currentReasons = getReasons();
    const selectedReason = getSelectedReason();

    datesToAdd.forEach(date => {
      currentReasons[date] = selectedReason;
    });

    setReasons(currentReasons);
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

  reasonFilter.addEventListener('change', renderCalendar);
  reasonInputs.forEach(input => input.addEventListener('change', renderCalendar));

  [startDateInput, endDateInput].forEach(input => input.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter') return;

    event.preventDefault();
    addButton.click();
  }));

  renderCalendar();
});
