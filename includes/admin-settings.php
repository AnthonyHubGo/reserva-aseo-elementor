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
    $settings_action !== 'guardar_logo_email' ||
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
      <input type="hidden" name="rae_settings_action" value="guardar_logo_email">
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
        </tbody>
      </table>

      <?php submit_button('Guardar cambios'); ?>
    </form>
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
