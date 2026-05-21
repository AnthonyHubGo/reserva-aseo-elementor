document.addEventListener('DOMContentLoaded', function () {
  const textarea = document.getElementById('rae_fechas_no_disponibles');
  const startDateInput = document.getElementById('rae_fecha_no_disponible_inicio');
  const endDateInput = document.getElementById('rae_fecha_no_disponible_fin');
  const addButton = document.getElementById('rae_agregar_fecha_no_disponible');
  const dateList = document.getElementById('rae_fechas_no_disponibles_lista');

  if (!textarea || !startDateInput || !endDateInput || !addButton || !dateList) return;

  function getDates() {
    return textarea.value
      .split(/\r?\n/)
      .map(date => date.trim())
      .filter(Boolean);
  }

  function setDates(dates) {
    const uniqueDates = Array.from(new Set(dates)).sort();

    textarea.value = uniqueDates.join('\n');
    renderDates(uniqueDates);
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

  function renderDates(dates) {
    dateList.innerHTML = '';

    if (!dates.length) {
      const emptyItem = document.createElement('li');

      emptyItem.className = 'rae-admin-date-empty';
      emptyItem.textContent = 'No hay fechas agregadas.';
      dateList.appendChild(emptyItem);
      return;
    }

    dates.forEach(date => {
      const item = document.createElement('li');
      const dateText = document.createElement('span');
      const removeButton = document.createElement('button');

      dateText.textContent = date;
      removeButton.type = 'button';
      removeButton.className = 'button-link-delete';
      removeButton.textContent = 'Quitar';
      removeButton.addEventListener('click', function () {
        setDates(getDates().filter(currentDate => currentDate !== date));
      });

      item.appendChild(dateText);
      item.appendChild(removeButton);
      dateList.appendChild(item);
    });
  }

  addButton.addEventListener('click', function () {
    if (!startDateInput.value) return;

    const datesToAdd = getDateRange(startDateInput.value, endDateInput.value);

    if (!datesToAdd.length) return;

    setDates([...getDates(), ...datesToAdd]);
    startDateInput.value = '';
    endDateInput.value = '';
    startDateInput.focus();
  });

  [startDateInput, endDateInput].forEach(input => input.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter') return;

    event.preventDefault();
    addButton.click();
  }));

  renderDates(getDates());
});
