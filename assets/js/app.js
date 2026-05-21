document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('rae-form');

  if (!form) return;

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
        }
      })
      .catch(() => {
        msg.innerText = 'Ocurrió un error al enviar la reserva.';
      });
  });
});