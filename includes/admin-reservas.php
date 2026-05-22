<?php

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
  add_menu_page(
    'Reservas de Aseo',
    'Reservas de Aseo',
    'manage_options',
    'reservas-aseo',
    'rae_render_admin_reservas',
    'dashicons-calendar-alt',
    26
  );
});

/**
 * Maneja acciones antes de imprimir HTML.
 */
add_action('admin_init', function () {
  if (
    isset($_POST['page'], $_POST['rae_action'], $_POST['reserva_id']) &&
    $_POST['page'] === 'reservas-aseo' &&
    $_POST['rae_action'] === 'eliminar' &&
    current_user_can('manage_options')
  ) {
    check_admin_referer('rae_eliminar_reserva_' . absint(wp_unslash($_POST['reserva_id'])));

    global $wpdb;

    $table = $wpdb->prefix . 'reservas_aseo';
    $reserva_id = absint(wp_unslash($_POST['reserva_id']));
    $reserva = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $reserva_id
      )
    );

    if ($reserva) {
      $wpdb->update(
        $table,
        ['estado' => 'cancelada'],
        ['id' => $reserva_id],
        ['%s'],
        ['%d']
      );

      rae_enviar_email_estado_reserva($reserva, 'cancelada');

      $wpdb->delete(
        $table,
        ['id' => $reserva_id],
        ['%d']
      );
    }

    wp_safe_redirect(admin_url('admin.php?page=reservas-aseo'));
    exit;
  }

  if (
    !isset($_GET['page'], $_GET['rae_action'], $_GET['reserva_id']) ||
    $_GET['page'] !== 'reservas-aseo' ||
    !current_user_can('manage_options')
  ) {
    return;
  }

  global $wpdb;

  $table = $wpdb->prefix . 'reservas_aseo';
  $reserva_id = absint(wp_unslash($_GET['reserva_id']));
  $accion = sanitize_text_field(wp_unslash($_GET['rae_action']));
  $nuevo_estado = '';
  $redirect_args = ['page' => 'reservas-aseo'];
  $filtros_redirect = ['persona_id', 'fecha', 'jornada', 'estado'];

  foreach ($filtros_redirect as $filtro) {
    if (isset($_GET[$filtro]) && $_GET[$filtro] !== '') {
      $redirect_args[$filtro] = sanitize_text_field(wp_unslash($_GET[$filtro]));
    }
  }

  if ($accion === 'confirmar') {
    $nuevo_estado = 'confirmada';
  }

  if ($accion === 'cancelar') {
    $nuevo_estado = 'cancelada';
  }

  if ($nuevo_estado) {
    $actualizado = false;
    $reserva = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $reserva_id
      )
    );

    if ($reserva && $reserva->estado !== $nuevo_estado) {
      $actualizado = $wpdb->update(
        $table,
        ['estado' => $nuevo_estado],
        ['id' => $reserva_id],
        ['%s'],
        ['%d']
      );
    }

    if (!empty($actualizado) && is_email($reserva->cliente_email)) {
      rae_enviar_email_estado_reserva($reserva, $nuevo_estado);
    }
  }

  wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
  exit;
});

function rae_render_admin_reservas() {
  global $wpdb;

  $table = $wpdb->prefix . 'reservas_aseo';
  $jornadas_permitidas = ['manana', 'tarde', 'completa'];
  $estados_permitidos = ['pendiente', 'confirmada', 'cancelada'];

  $persona_id = isset($_GET['persona_id']) ? absint(wp_unslash($_GET['persona_id'])) : 0;
  $fecha = isset($_GET['fecha']) ? sanitize_text_field(wp_unslash($_GET['fecha'])) : '';
  $jornada = isset($_GET['jornada']) ? sanitize_text_field(wp_unslash($_GET['jornada'])) : '';
  $estado = isset($_GET['estado']) ? sanitize_text_field(wp_unslash($_GET['estado'])) : '';

  if ($fecha && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !rae_fecha_valida($fecha))) {
    $fecha = '';
  }

  if ($jornada && !in_array($jornada, $jornadas_permitidas, true)) {
    $jornada = '';
  }

  if ($estado && !in_array($estado, $estados_permitidos, true)) {
    $estado = '';
  }

  $filtros_actuales = [];

  if ($persona_id) {
    $filtros_actuales['persona_id'] = $persona_id;
  }

  if ($fecha) {
    $filtros_actuales['fecha'] = $fecha;
  }

  if ($jornada) {
    $filtros_actuales['jornada'] = $jornada;
  }

  if ($estado) {
    $filtros_actuales['estado'] = $estado;
  }

  $where = "WHERE 1=1";
  $params = [];

  if ($persona_id) {
    $where .= " AND persona_id = %d";
    $params[] = $persona_id;
  }

  if ($fecha) {
    $where .= " AND fecha = %s";
    $params[] = $fecha;
  }

  if ($jornada) {
    $where .= " AND jornada = %s";
    $params[] = $jornada;
  }

  if ($estado) {
    $where .= " AND estado = %s";
    $params[] = $estado;
  }

  $sql = "SELECT * FROM $table $where ORDER BY fecha DESC, created_at DESC";

  if (!empty($params)) {
    $sql = call_user_func_array([$wpdb, 'prepare'], array_merge([$sql], $params));
  }

  $reservas = $wpdb->get_results($sql);

  $personal = get_posts([
    'post_type' => 'personal_aseo',
    'numberposts' => -1,
    'post_status' => 'publish',
  ]);
  ?>

  <div class="wrap">
    <h1>Reservas de Aseo</h1>

    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin: 20px 0;">
      <input type="hidden" name="page" value="reservas-aseo">

      <select name="persona_id">
        <option value="">Todas las personas</option>
        <?php foreach ($personal as $persona): ?>
          <option value="<?php echo esc_attr($persona->ID); ?>" <?php selected($persona_id, $persona->ID); ?>>
            <?php echo esc_html($persona->post_title); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <input type="date" name="fecha" value="<?php echo esc_attr($fecha); ?>">

      <select name="jornada">
        <option value="">Todas las jornadas</option>
        <option value="manana" <?php selected($jornada, 'manana'); ?>>Mañana</option>
        <option value="tarde" <?php selected($jornada, 'tarde'); ?>>Tarde</option>
        <option value="completa" <?php selected($jornada, 'completa'); ?>>Día completa</option>
      </select>

      <select name="estado">
        <option value="">Todos los estados</option>
        <option value="pendiente" <?php selected($estado, 'pendiente'); ?>>Pendiente</option>
        <option value="confirmada" <?php selected($estado, 'confirmada'); ?>>Confirmada</option>
        <option value="cancelada" <?php selected($estado, 'cancelada'); ?>>Cancelada</option>
      </select>

      <button class="button button-primary">Filtrar</button>

      <a href="<?php echo esc_url(admin_url('admin.php?page=reservas-aseo')); ?>" class="button">
        Limpiar
      </a>
    </form>

    <table class="widefat striped">
      <thead>
        <tr>
          <th>Cliente</th>
          <th>Email</th>
          <th>Dirección</th>
          <th>Persona</th>
          <th>Fecha</th>
          <th>Jornada</th>
          <th>Estado</th>
          <th>Creada</th>
          <th>Acciones</th>
        </tr>
      </thead>

      <tbody>
        <?php if (empty($reservas)): ?>
          <tr>
            <td colspan="9">No hay reservas con estos filtros.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($reservas as $reserva): ?>
            <?php
            $ciudad = rae_valor_reserva_o_default($reserva->cliente_ciudad ?? '');
            $barrio = rae_valor_reserva_o_default($reserva->cliente_barrio ?? '');
            $direccion = rae_valor_reserva_o_default($reserva->cliente_direccion ?? '');
            $casa = rae_valor_reserva_o_default($reserva->cliente_casa ?? '');
            $direccion_detalle_id = 'rae-direccion-reserva-' . absint($reserva->id);
            ?>
            <tr>
              <td><?php echo esc_html($reserva->cliente_nombre); ?></td>
              <td><?php echo esc_html($reserva->cliente_email); ?></td>
              <td>
                <div class="rae-address-summary">
                  <strong><?php echo esc_html($ciudad); ?></strong>
                  <span><?php echo esc_html($barrio); ?></span>
                </div>

                <button
                  type="button"
                  class="button-link rae-toggle-address"
                  aria-expanded="false"
                  aria-controls="<?php echo esc_attr($direccion_detalle_id); ?>"
                  data-target="<?php echo esc_attr($direccion_detalle_id); ?>"
                >
                  Ver dirección
                </button>

                <div id="<?php echo esc_attr($direccion_detalle_id); ?>" class="rae-address-details" hidden>
                  <dl>
                    <div>
                      <dt>Ciudad</dt>
                      <dd><?php echo esc_html($ciudad); ?></dd>
                    </div>
                    <div>
                      <dt>Barrio</dt>
                      <dd><?php echo esc_html($barrio); ?></dd>
                    </div>
                    <div>
                      <dt>Dirección</dt>
                      <dd><?php echo esc_html($direccion); ?></dd>
                    </div>
                    <div>
                      <dt>Casa / Apartamento</dt>
                      <dd><?php echo esc_html($casa); ?></dd>
                    </div>
                  </dl>
                </div>
              </td>
              <td><?php echo esc_html(get_the_title($reserva->persona_id)); ?></td>
              <td><?php echo esc_html($reserva->fecha); ?></td>
              <td><?php echo esc_html(rae_nombre_jornada($reserva->jornada)); ?></td>
              <td><?php echo esc_html(ucfirst($reserva->estado)); ?></td>
              <td><?php echo esc_html($reserva->created_at); ?></td>
              <td>
                <div class="rae-reserva-actions">
                  <div class="rae-reserva-state-actions">
                    <?php if ($reserva->estado !== 'confirmada'): ?>
                      <a
                        class="button button-primary"
                        href="<?php echo esc_url(add_query_arg(array_merge($filtros_actuales, [
                          'page' => 'reservas-aseo',
                          'rae_action' => 'confirmar',
                          'reserva_id' => $reserva->id,
                        ]), admin_url('admin.php'))); ?>"
                      >
                        Confirmar
                      </a>
                    <?php endif; ?>

                    <?php if ($reserva->estado !== 'cancelada'): ?>
                      <a
                        class="button"
                        href="<?php echo esc_url(add_query_arg(array_merge($filtros_actuales, [
                          'page' => 'reservas-aseo',
                          'rae_action' => 'cancelar',
                          'reserva_id' => $reserva->id,
                        ]), admin_url('admin.php'))); ?>"
                      >
                        Cancelar
                      </a>
                    <?php endif; ?>
                  </div>

                  <button
                    type="button"
                    class="button rae-delete-reserva-button"
                    data-modal-id="rae-delete-reserva-<?php echo esc_attr($reserva->id); ?>"
                    aria-label="Eliminar reserva"
                  >
                    <span class="dashicons dashicons-trash"></span>
                  </button>
                </div>

                <div id="rae-delete-reserva-<?php echo esc_attr($reserva->id); ?>" class="rae-delete-reserva-modal" hidden>
                  <div class="rae-delete-reserva-dialog" role="dialog" aria-modal="true" aria-labelledby="rae-delete-title-<?php echo esc_attr($reserva->id); ?>">
                    <h2 id="rae-delete-title-<?php echo esc_attr($reserva->id); ?>">Eliminar reserva</h2>
                    <p>Estas seguro de que quieres eliminar esta reserva? Si la eliminas se cancelará automaticamente y le llegará un mensaje al cliente de que fue cancelada su reserva</p>

                    <div class="rae-delete-reserva-actions">
                      <form method="post">
                        <input type="hidden" name="page" value="reservas-aseo">
                        <input type="hidden" name="rae_action" value="eliminar">
                        <input type="hidden" name="reserva_id" value="<?php echo esc_attr($reserva->id); ?>">
                        <?php wp_nonce_field('rae_eliminar_reserva_' . absint($reserva->id)); ?>
                        <button type="submit" class="button button-primary">Cancelar reserva</button>
                      </form>

                      <button type="button" class="button rae-delete-reserva-close">Volver</button>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <style>
    .rae-address-summary {
      display: grid;
      gap: 2px;
      min-width: 150px;
      color: #1d2327;
    }

    .rae-address-summary span {
      color: #646970;
      font-size: 12px;
    }

    .rae-toggle-address {
      margin-top: 6px;
      font-size: 12px;
      text-decoration: none;
    }

    .rae-address-details {
      width: min(280px, 100%);
      margin-top: 8px;
      padding: 10px 12px;
      border: 1px solid #dcdcde;
      border-radius: 6px;
      background: #f6f7f7;
    }

    .rae-address-details dl {
      display: grid;
      gap: 8px;
      margin: 0;
    }

    .rae-address-details dl > div {
      display: grid;
      gap: 2px;
    }

    .rae-address-details dt {
      color: #1d2327;
      font-size: 12px;
      font-weight: 700;
    }

    .rae-address-details dd {
      margin: 0;
      color: #50575e;
    }

    .rae-reserva-actions {
      display: flex;
      gap: 8px;
      align-items: center;
      justify-content: space-between;
      min-width: 260px;
    }

    .rae-reserva-state-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
    }

    .widefat .rae-delete-reserva-button.button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      min-height: 40px;
      padding: 0;
      margin-left: auto;
      border-color: #dc2626 !important;
      background: transparent !important;
      color: #dc2626 !important;
    }

    .widefat .rae-delete-reserva-button.button:hover,
    .widefat .rae-delete-reserva-button.button:focus {
      border-color: #b91c1c !important;
      background: rgba(220, 38, 38, 0.08) !important;
      color: #b91c1c !important;
    }

    .rae-delete-reserva-button .dashicons {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 20px;
      height: 20px;
      margin: 0;
      color: currentColor;
      line-height: 1;
    }

    .widefat .rae-delete-reserva-button.button .dashicons,
    .widefat .rae-delete-reserva-button.button .dashicons::before {
      color: #dc2626 !important;
    }

    .widefat .rae-delete-reserva-button.button:hover .dashicons,
    .widefat .rae-delete-reserva-button.button:hover .dashicons::before,
    .widefat .rae-delete-reserva-button.button:focus .dashicons,
    .widefat .rae-delete-reserva-button.button:focus .dashicons::before {
      color: #b91c1c !important;
    }

    .rae-delete-reserva-modal {
      position: fixed;
      z-index: 100000;
      inset: 0;
      display: grid;
      place-items: center;
      padding: 20px;
      background: rgba(0, 0, 0, 0.45);
    }

    .rae-delete-reserva-modal[hidden] {
      display: none;
    }

    .rae-delete-reserva-dialog {
      width: min(520px, 100%);
      padding: 24px;
      border-radius: 8px;
      background: #fff;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    }

    .rae-delete-reserva-dialog h2 {
      margin-top: 0;
    }

    .rae-delete-reserva-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      justify-content: flex-end;
      margin-top: 20px;
    }
  </style>

  <script>
    document.addEventListener('click', function (event) {
      const openButton = event.target.closest('.rae-delete-reserva-button');
      const closeButton = event.target.closest('.rae-delete-reserva-close');

      if (openButton) {
        const modal = document.getElementById(openButton.dataset.modalId);

        if (modal) {
          modal.hidden = false;
        }
      }

      if (closeButton) {
        const modal = closeButton.closest('.rae-delete-reserva-modal');

        if (modal) {
          modal.hidden = true;
        }
      }

      if (event.target.classList.contains('rae-delete-reserva-modal')) {
        event.target.hidden = true;
      }

      const addressButton = event.target.closest('.rae-toggle-address');

      if (addressButton) {
        const details = document.getElementById(addressButton.dataset.target);

        if (details) {
          const isExpanded = addressButton.getAttribute('aria-expanded') === 'true';

          details.hidden = isExpanded;
          addressButton.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
          addressButton.textContent = isExpanded ? 'Ver dirección' : 'Ocultar dirección';
        }
      }
    });
  </script>

  <?php
}

function rae_nombre_jornada($jornada) {
  $jornadas = [
    'manana' => 'Mañana',
    'tarde' => 'Tarde',
    'completa' => 'Día completa',
  ];

  return $jornadas[$jornada] ?? $jornada;
}

function rae_valor_reserva_o_default($valor) {
  $valor = trim((string) $valor);

  return $valor !== '' ? $valor : 'No especificado';
}

function rae_enviar_email_estado_reserva($reserva, $nuevo_estado) {
  if (function_exists('rae_enviar_email_reserva_estado')) {
    return rae_enviar_email_reserva_estado($reserva, $nuevo_estado);
  }

  return false;
}

function rae_fecha_valida($fecha) {
  [$year, $month, $day] = array_map('intval', explode('-', $fecha));

  return checkdate($month, $day, $year);
}
