<?php

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
  add_submenu_page(
    'reservas-aseo',
    'Configuración',
    'Configuración',
    'manage_options',
    'reservas-aseo-configuracion',
    'rae_render_admin_settings'
  );
});

add_action('admin_enqueue_scripts', function ($hook) {
  $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

  if ($hook !== 'reservas-de-aseo_page_reservas-aseo-configuracion' && $page !== 'reservas-aseo-configuracion') {
    return;
  }

  wp_enqueue_media();
});

add_action('admin_init', function () {
  $settings_action = isset($_POST['rae_settings_action'])
    ? sanitize_text_field(wp_unslash($_POST['rae_settings_action']))
    : '';

  if (
    $settings_action !== 'guardar_configuracion' ||
    !current_user_can('manage_options')
  ) {
    return;
  }

  check_admin_referer('rae_guardar_configuracion');

  $logo_id = isset($_POST['rae_email_logo_id']) ? absint(wp_unslash($_POST['rae_email_logo_id'])) : 0;

  if ($logo_id) {
    $attachment = get_post($logo_id);

    if (!$attachment || $attachment->post_type !== 'attachment' || !wp_attachment_is_image($logo_id)) {
      $logo_id = 0;
    }
  }

  if ($logo_id) {
    update_option('rae_email_logo_id', $logo_id);
  } else {
    delete_option('rae_email_logo_id');
  }

  $notification_email = sanitize_email(wp_unslash($_POST['rae_notification_email'] ?? ''));

  if ($notification_email && is_email($notification_email)) {
    update_option('rae_notification_email', $notification_email, false);
  } else {
    delete_option('rae_notification_email');
  }

  $wompi_settings = [
    'mode' => isset($_POST['rae_wompi_mode']) && sanitize_text_field(wp_unslash($_POST['rae_wompi_mode'])) === 'production'
      ? 'production'
      : 'sandbox',
    'public_key' => sanitize_text_field(wp_unslash($_POST['rae_wompi_public_key'] ?? '')),
    'private_key' => sanitize_text_field(wp_unslash($_POST['rae_wompi_private_key'] ?? '')),
    'events_secret' => sanitize_text_field(wp_unslash($_POST['rae_wompi_events_secret'] ?? '')),
    'integrity_secret' => sanitize_text_field(wp_unslash($_POST['rae_wompi_integrity_secret'] ?? '')),
    'currency' => 'COP',
    'amount_cop' => preg_replace('/[^0-9]/', '', sanitize_text_field(wp_unslash($_POST['rae_wompi_amount_cop'] ?? ''))),
  ];

  update_option('rae_wompi_settings', $wompi_settings, false);

  wp_safe_redirect(
    add_query_arg(
      [
        'page' => 'reservas-aseo-configuracion',
        'settings-updated' => 'true',
      ],
      admin_url('admin.php')
    )
  );
  exit;
});

function rae_render_admin_settings() {
  if (!current_user_can('manage_options')) {
    wp_die(esc_html__('No tienes permisos para acceder a esta página.', 'reserva-aseo-elementor'));
  }

  $logo_id = absint(get_option('rae_email_logo_id'));
  $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
  $notification_email = sanitize_email(get_option('rae_notification_email', ''));
  $wompi = function_exists('rae_wompi_settings') ? rae_wompi_settings() : [];
  $webhook_url = rest_url('sat-reservas/v1/wompi-webhook');
  $webhook_logs = get_option('rae_wompi_webhook_logs', []);

  if (!is_array($webhook_logs)) {
    $webhook_logs = [];
  }

  $settings_updated = isset($_GET['settings-updated']) ? sanitize_text_field(wp_unslash($_GET['settings-updated'])) : '';
  ?>

  <div class="wrap">
    <h1>Configuración de Reservas de Aseo</h1>

    <?php if ($settings_updated === 'true'): ?>
      <div class="notice notice-success is-dismissible">
        <p>Configuración guardada correctamente.</p>
      </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=reservas-aseo-configuracion')); ?>" class="rae-settings-form">
      <?php wp_nonce_field('rae_guardar_configuracion'); ?>
      <input type="hidden" name="rae_settings_action" value="guardar_configuracion">
      <input type="hidden" id="rae_email_logo_id" name="rae_email_logo_id" value="<?php echo esc_attr($logo_id); ?>">

      <table class="form-table" role="presentation">
        <tbody>
          <tr>
            <th scope="row">
              <label for="rae_email_logo_id">Logo de la empresa para correos</label>
            </th>
            <td>
              <div id="rae-email-logo-preview" class="rae-email-logo-preview">
                <?php if ($logo_url): ?>
                  <img src="<?php echo esc_url($logo_url); ?>" alt="SAT">
                <?php else: ?>
                  <span>No hay logo seleccionado.</span>
                <?php endif; ?>
              </div>

              <p>
                <button type="button" class="button" id="rae-select-email-logo">Seleccionar logo</button>
                <button type="button" class="button" id="rae-remove-email-logo" <?php disabled(!$logo_url); ?>>Quitar logo</button>
              </p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="rae_notification_email">Correo de notificaciones de reservas</label>
            </th>
            <td>
              <input
                type="email"
                class="regular-text"
                id="rae_notification_email"
                name="rae_notification_email"
                value="<?php echo esc_attr($notification_email); ?>"
                placeholder="<?php echo esc_attr(get_option('admin_email')); ?>"
              >
              <p class="description">Recibirá una copia interna cuando se cree una reserva o cambie su estado. Déjalo vacío para no enviar notificaciones internas.</p>
            </td>
          </tr>

          <tr>
            <th scope="row">Wompi Colombia</th>
            <td>
              <fieldset>
                <p>
                  <label for="rae_wompi_mode"><strong>Modo</strong></label><br>
                  <select id="rae_wompi_mode" name="rae_wompi_mode">
                    <option value="sandbox" <?php selected($wompi['mode'] ?? 'sandbox', 'sandbox'); ?>>Sandbox</option>
                    <option value="production" <?php selected($wompi['mode'] ?? 'sandbox', 'production'); ?>>Producción</option>
                  </select>
                </p>

                <p>
                  <label for="rae_wompi_public_key"><strong>Llave pública</strong></label><br>
                  <input type="text" class="regular-text" id="rae_wompi_public_key" name="rae_wompi_public_key" value="<?php echo esc_attr($wompi['public_key'] ?? ''); ?>" autocomplete="off">
                </p>

                <p>
                  <label for="rae_wompi_private_key"><strong>Llave privada</strong></label><br>
                  <input type="password" class="regular-text" id="rae_wompi_private_key" name="rae_wompi_private_key" value="<?php echo esc_attr($wompi['private_key'] ?? ''); ?>" autocomplete="new-password">
                </p>

                <p>
                  <label for="rae_wompi_events_secret"><strong>Llave de eventos / webhook</strong></label><br>
                  <input type="password" class="regular-text" id="rae_wompi_events_secret" name="rae_wompi_events_secret" value="<?php echo esc_attr($wompi['events_secret'] ?? ''); ?>" autocomplete="new-password">
                </p>

                <p>
                  <label for="rae_wompi_integrity_secret"><strong>Secreto de integridad</strong></label><br>
                  <input type="password" class="regular-text" id="rae_wompi_integrity_secret" name="rae_wompi_integrity_secret" value="<?php echo esc_attr($wompi['integrity_secret'] ?? ''); ?>" autocomplete="new-password">
                  <span class="description">Necesario para firmar el Web Checkout sin exponer secretos en el frontend.</span>
                </p>

                <p>
                  <label for="rae_wompi_amount_cop"><strong>Valor por reserva en COP</strong></label><br>
                  <input type="number" min="1" step="1" id="rae_wompi_amount_cop" name="rae_wompi_amount_cop" value="<?php echo esc_attr($wompi['amount_cop'] ?? ''); ?>">
                </p>

                <p>
                  <strong>Moneda:</strong> COP
                </p>

                <p>
                  <strong>URL webhook:</strong><br>
                  <code><?php echo esc_html($webhook_url); ?></code>
                </p>
              </fieldset>
            </td>
          </tr>
        </tbody>
      </table>

      <?php submit_button('Guardar cambios'); ?>
    </form>

    <h2>Últimos webhooks de Wompi</h2>

    <?php if (empty($webhook_logs)): ?>
      <p>No se ha recibido ningún webhook todavía.</p>
    <?php else: ?>
      <table class="widefat striped">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Estado</th>
            <th>Mensaje</th>
            <th>Contexto</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($webhook_logs as $webhook_log): ?>
            <tr>
              <td><?php echo esc_html($webhook_log['created_at'] ?? ''); ?></td>
              <td><?php echo esc_html($webhook_log['status'] ?? ''); ?></td>
              <td><?php echo esc_html($webhook_log['message'] ?? ''); ?></td>
              <td><code><?php echo esc_html(wp_json_encode($webhook_log['context'] ?? [])); ?></code></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <style>
    .rae-email-logo-preview {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 220px;
      min-height: 110px;
      padding: 16px;
      border: 1px solid #aadde3;
      border-radius: 8px;
      background: #fff;
      color: #646970;
    }

    .rae-email-logo-preview img {
      display: block;
      max-width: 180px;
      height: auto;
    }
  </style>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const selectButton = document.getElementById('rae-select-email-logo');
      const removeButton = document.getElementById('rae-remove-email-logo');
      const logoInput = document.getElementById('rae_email_logo_id');
      const preview = document.getElementById('rae-email-logo-preview');
      let mediaFrame;

      function setPreview(url) {
        preview.textContent = '';

        if (url) {
          const image = document.createElement('img');

          image.src = url;
          image.alt = 'SAT';
          preview.appendChild(image);
          removeButton.disabled = false;
          return;
        }

        const emptyText = document.createElement('span');

        emptyText.textContent = 'No hay logo seleccionado.';
        preview.appendChild(emptyText);
        removeButton.disabled = true;
      }

      selectButton.addEventListener('click', function () {
        if (mediaFrame) {
          mediaFrame.open();
          return;
        }

        mediaFrame = wp.media({
          title: 'Seleccionar logo',
          button: {
            text: 'Usar este logo'
          },
          multiple: false,
          library: {
            type: 'image'
          }
        });

        mediaFrame.on('select', function () {
          const attachment = mediaFrame.state().get('selection').first().toJSON();
          const imageUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

          logoInput.value = attachment.id;
          setPreview(imageUrl);
        });

        mediaFrame.open();
      });

      removeButton.addEventListener('click', function () {
        logoInput.value = '';
        setPreview('');
      });
    });
  </script>

  <?php
}
