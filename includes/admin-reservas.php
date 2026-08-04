<?php

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
  add_menu_page(
    'Reservas de Aseo',
    'Reservas de Aseo',
    'rae_view_reservas',
    'reservas-aseo',
    'rae_render_admin_reservas',
    'dashicons-calendar-alt',
    26
  );
});

function rae_reserva_puede_confirmarse_manualmente($reserva) {
  if (!$reserva || ($reserva->payment_gateway ?? '') === 'wompi') {
    return false;
  }

  return in_array(
    (string) ($reserva->estado ?? ''),
    ['pendiente', 'pendiente_pago', 'pagado'],
    true
  );
}

/**
 * Maneja acciones antes de imprimir HTML.
 */
add_action('admin_init', function () {
  if (
    isset($_POST['page'], $_POST['rae_action'], $_POST['reserva_id']) &&
    $_POST['page'] === 'reservas-aseo' &&
    $_POST['rae_action'] === 'cancelar' &&
    current_user_can('rae_manage_reservas')
  ) {
    check_admin_referer('rae_cancelar_reserva_' . absint(wp_unslash($_POST['reserva_id'])));

    global $wpdb;

    $table = $wpdb->prefix . 'reservas_aseo';
    $reserva_id = absint(wp_unslash($_POST['reserva_id']));
    $motivo_cancelacion = sanitize_textarea_field(wp_unslash($_POST['motivo_cancelacion'] ?? ''));

    if ($motivo_cancelacion === '') {
      wp_safe_redirect(add_query_arg(['page' => 'reservas-aseo', 'rae_error' => 'motivo_cancelacion'], admin_url('admin.php')));
      exit;
    }

    $reserva = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $reserva_id
      )
    );

    if ($reserva && $reserva->estado !== 'cancelada') {
      $actualizado = $wpdb->update(
        $table,
        ['estado' => 'cancelada'],
        ['id' => $reserva_id],
        ['%s'],
        ['%d']
      );

      if (!empty($actualizado) && is_email($reserva->cliente_email)) {
        $reserva->cancelacion_motivo = $motivo_cancelacion;
        rae_enviar_email_estado_reserva($reserva, 'cancelada');
      }
    }

    wp_safe_redirect(admin_url('admin.php?page=reservas-aseo'));
    exit;
  }

  if (
    isset($_POST['page'], $_POST['rae_action'], $_POST['reserva_id']) &&
    $_POST['page'] === 'reservas-aseo' &&
    $_POST['rae_action'] === 'eliminar' &&
    current_user_can('rae_manage_reservas')
  ) {
    check_admin_referer('rae_eliminar_reserva_' . absint(wp_unslash($_POST['reserva_id'])));

    global $wpdb;

    $table = $wpdb->prefix . 'reservas_aseo';
    $reserva_id = absint(wp_unslash($_POST['reserva_id']));
    $motivo_cancelacion = sanitize_textarea_field(wp_unslash($_POST['motivo_cancelacion'] ?? ''));
    $reserva = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $reserva_id
      )
    );

    if ($reserva) {
      if ($reserva->estado !== 'cancelada') {
        if ($motivo_cancelacion === '') {
          wp_safe_redirect(add_query_arg(['page' => 'reservas-aseo', 'rae_error' => 'motivo_cancelacion'], admin_url('admin.php')));
          exit;
        }

        $wpdb->update(
          $table,
          ['estado' => 'cancelada'],
          ['id' => $reserva_id],
          ['%s'],
          ['%d']
        );

        $reserva->cancelacion_motivo = $motivo_cancelacion;
        rae_enviar_email_estado_reserva($reserva, 'cancelada');
      }

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
    !current_user_can('rae_manage_reservas')
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
    check_admin_referer('rae_confirmar_reserva_' . $reserva_id);
    $nuevo_estado = 'confirmada';
  }

  if ($nuevo_estado) {
    $actualizado = false;
    $reserva = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $reserva_id
      )
    );

    if (!rae_reserva_puede_confirmarse_manualmente($reserva)) {
      $redirect_args['rae_error'] = 'confirmacion_no_disponible';
    } else {
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
  if (!current_user_can('rae_view_reservas')) {
    wp_die(esc_html__('No tienes permisos para acceder a esta página.', 'reserva-aseo-elementor'));
  }

  global $wpdb;

  if (function_exists('rae_wompi_expire_pending_reservations')) {
    rae_wompi_expire_pending_reservations();
  }

  $table = $wpdb->prefix . 'reservas_aseo';
  $jornadas_permitidas = ['manana', 'tarde', 'completa'];
  $estados_permitidos = ['pendiente', 'pendiente_pago', 'pagado', 'confirmada', 'rechazada', 'cancelada', 'expirada', 'pago_revision'];
  $puede_gestionar_reservas = current_user_can('rae_manage_reservas');

  $persona_id = isset($_GET['persona_id']) ? absint(wp_unslash($_GET['persona_id'])) : 0;
  $fecha = isset($_GET['fecha']) ? sanitize_text_field(wp_unslash($_GET['fecha'])) : '';
  $jornada = isset($_GET['jornada']) ? sanitize_text_field(wp_unslash($_GET['jornada'])) : '';
  $estado = isset($_GET['estado']) ? sanitize_text_field(wp_unslash($_GET['estado'])) : '';
  $admin_error = isset($_GET['rae_error']) ? sanitize_text_field(wp_unslash($_GET['rae_error'])) : '';

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

    <?php if ($admin_error === 'motivo_cancelacion'): ?>
      <div class="notice notice-error is-dismissible">
        <p>Debes indicar el motivo de cancelación antes de cambiar la reserva a cancelada.</p>
      </div>
    <?php endif; ?>

    <?php if ($admin_error === 'confirmacion_no_disponible'): ?>
      <div class="notice notice-error is-dismissible">
        <p>Esta reserva no puede confirmarse manualmente. Los pagos de Wompi se confirman automáticamente y una reserva cancelada no puede reactivarse.</p>
      </div>
    <?php endif; ?>

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
        <option value="completa" <?php selected($jornada, 'completa'); ?>>Jornada Completa</option>
      </select>

      <select name="estado">
        <option value="">Todos los estados</option>
        <option value="pendiente" <?php selected($estado, 'pendiente'); ?>>Pendiente</option>
        <option value="pendiente_pago" <?php selected($estado, 'pendiente_pago'); ?>>Pendiente de pago</option>
        <option value="pagado" <?php selected($estado, 'pagado'); ?>>Pagado</option>
        <option value="confirmada" <?php selected($estado, 'confirmada'); ?>>Confirmada</option>
        <option value="rechazada" <?php selected($estado, 'rechazada'); ?>>Rechazada</option>
        <option value="cancelada" <?php selected($estado, 'cancelada'); ?>>Cancelada</option>
        <option value="expirada" <?php selected($estado, 'expirada'); ?>>Expirada</option>
        <option value="pago_revision" <?php selected($estado, 'pago_revision'); ?>>Pago requiere revisión</option>
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
          <th>Teléfono</th>
          <th>Dirección</th>
          <th>Persona</th>
          <th>Fecha</th>
          <th>Jornada</th>
          <th>Estado</th>
          <th>Pago</th>
          <th>Creada</th>
          <th>Acciones</th>
        </tr>
      </thead>

      <tbody>
        <?php if (empty($reservas)): ?>
          <tr>
            <td colspan="11">No hay reservas con estos filtros.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($reservas as $reserva): ?>
            <?php
            $telefono = rae_valor_reserva_o_default($reserva->cliente_telefono ?? '');
            $ciudad = rae_valor_reserva_o_default($reserva->cliente_ciudad ?? '');
            $barrio = rae_valor_reserva_o_default($reserva->cliente_barrio ?? '');
            $direccion = rae_valor_reserva_o_default($reserva->cliente_direccion ?? '');
            $casa = rae_valor_reserva_o_default($reserva->cliente_casa ?? '');
            $direccion_detalle_id = 'rae-direccion-reserva-' . absint($reserva->id);
            $mensaje_eliminar = $reserva->estado === 'cancelada'
              ? 'Estas seguro de que quieres eliminar esta reserva? Esto eliminará su registro y no podrás revertir esta acción'
              : 'Estas seguro de que quieres eliminar esta reserva? Si la eliminas se cancelará automaticamente y le llegará un mensaje al cliente de que fue cancelada su reserva';
            ?>
            <tr>
              <td><?php echo esc_html($reserva->cliente_nombre); ?></td>
              <td><?php echo esc_html($reserva->cliente_email); ?></td>
              <td><?php echo esc_html($telefono); ?></td>
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
                      <dt>Teléfono de contacto</dt>
                      <dd><?php echo esc_html($telefono); ?></dd>
                    </div>
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
              <td><?php echo esc_html(rae_nombre_estado_reserva($reserva->estado)); ?></td>
              <td>
                <div class="rae-payment-summary">
                  <strong><?php echo esc_html(rae_nombre_estado_pago($reserva->payment_status ?? '')); ?></strong>
                  <?php if (!empty($reserva->payment_reference)): ?>
                    <span>Ref: <?php echo esc_html($reserva->payment_reference); ?></span>
                  <?php endif; ?>
                  <?php if (!empty($reserva->payment_amount_cop)): ?>
                    <span>Valor: <?php echo esc_html(rae_formatear_pesos_colombianos($reserva->payment_amount_cop)); ?></span>
                  <?php endif; ?>
                  <?php if (!empty($reserva->wompi_transaction_id)): ?>
                    <span>Wompi: <?php echo esc_html($reserva->wompi_transaction_id); ?></span>
                  <?php endif; ?>
                </div>
              </td>
              <td><?php echo esc_html($reserva->created_at); ?></td>
              <td>
                <?php if ($puede_gestionar_reservas): ?>
                  <div class="rae-reserva-actions">
                    <div class="rae-reserva-state-actions">
                      <?php
                      $puede_confirmar_manualmente = rae_reserva_puede_confirmarse_manualmente($reserva);
                      ?>
                      <?php if ($puede_confirmar_manualmente): ?>
                        <a
                          class="button button-primary"
                          href="<?php echo esc_url(wp_nonce_url(
                            add_query_arg(array_merge($filtros_actuales, [
                              'page' => 'reservas-aseo',
                              'rae_action' => 'confirmar',
                              'reserva_id' => $reserva->id,
                            ]), admin_url('admin.php')),
                            'rae_confirmar_reserva_' . absint($reserva->id)
                          )); ?>"
                        >
                          Confirmar
                        </a>
                      <?php endif; ?>

                      <?php if (!in_array($reserva->estado, ['cancelada', 'expirada', 'rechazada'], true)): ?>
                        <button
                          type="button"
                          class="button"
                          data-modal-id="rae-cancel-reserva-<?php echo esc_attr($reserva->id); ?>"
                        >
                          Cancelar
                        </button>
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

                  <?php if (!in_array($reserva->estado, ['cancelada', 'expirada', 'rechazada'], true)): ?>
                    <div id="rae-cancel-reserva-<?php echo esc_attr($reserva->id); ?>" class="rae-cancel-reserva-modal" hidden>
                      <div class="rae-delete-reserva-dialog" role="dialog" aria-modal="true" aria-labelledby="rae-cancel-title-<?php echo esc_attr($reserva->id); ?>">
                        <h2 id="rae-cancel-title-<?php echo esc_attr($reserva->id); ?>">Cancelar reserva</h2>
                        <p>Indica el motivo de la cancelación. Este motivo será incluido en el correo que recibirá el cliente.</p>

                        <form method="post">
                          <input type="hidden" name="page" value="reservas-aseo">
                          <input type="hidden" name="rae_action" value="cancelar">
                          <input type="hidden" name="reserva_id" value="<?php echo esc_attr($reserva->id); ?>">
                          <?php wp_nonce_field('rae_cancelar_reserva_' . absint($reserva->id)); ?>

                          <label class="rae-cancel-reason-field">
                            <span>Motivo de cancelación</span>
                            <textarea name="motivo_cancelacion" rows="4" required></textarea>
                          </label>

                          <div class="rae-delete-reserva-actions">
                            <button type="submit" class="button button-primary">Cancelar reserva</button>
                            <button type="button" class="button rae-reserva-modal-close">Volver</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  <?php endif; ?>

                  <div id="rae-delete-reserva-<?php echo esc_attr($reserva->id); ?>" class="rae-delete-reserva-modal" hidden>
                    <div class="rae-delete-reserva-dialog" role="dialog" aria-modal="true" aria-labelledby="rae-delete-title-<?php echo esc_attr($reserva->id); ?>">
                      <h2 id="rae-delete-title-<?php echo esc_attr($reserva->id); ?>">Eliminar reserva</h2>
                      <p><?php echo esc_html($mensaje_eliminar); ?></p>

                      <div class="rae-delete-reserva-actions">
                        <form method="post">
                          <input type="hidden" name="page" value="reservas-aseo">
                          <input type="hidden" name="rae_action" value="eliminar">
                          <input type="hidden" name="reserva_id" value="<?php echo esc_attr($reserva->id); ?>">
                          <?php wp_nonce_field('rae_eliminar_reserva_' . absint($reserva->id)); ?>

                          <?php if ($reserva->estado !== 'cancelada'): ?>
                            <label class="rae-cancel-reason-field">
                              <span>Motivo de cancelación</span>
                              <textarea name="motivo_cancelacion" rows="4" required></textarea>
                            </label>
                          <?php endif; ?>

                          <button type="submit" class="button button-primary">Cancelar reserva</button>
                        </form>

                        <button type="button" class="button rae-reserva-modal-close">Volver</button>
                      </div>
                    </div>
                  </div>
                <?php else: ?>
                  <span aria-hidden="true">—</span>
                <?php endif; ?>
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

    .rae-payment-summary {
      display: grid;
      gap: 2px;
      min-width: 180px;
    }

    .rae-payment-summary span {
      color: #646970;
      font-size: 12px;
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

    .rae-delete-reserva-modal,
    .rae-cancel-reserva-modal {
      position: fixed;
      z-index: 100000;
      inset: 0;
      display: grid;
      place-items: center;
      padding: 20px;
      background: rgba(0, 0, 0, 0.45);
    }

    .rae-delete-reserva-modal[hidden],
    .rae-cancel-reserva-modal[hidden] {
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

    .rae-delete-reserva-actions form {
      display: grid;
      gap: 14px;
      width: 100%;
    }

    .rae-cancel-reason-field {
      display: grid;
      gap: 6px;
      margin-top: 14px;
      color: #1d2327;
      font-weight: 600;
    }

    .rae-cancel-reason-field textarea {
      width: 100%;
      min-height: 96px;
      resize: vertical;
      font-weight: 400;
    }
  </style>

  <script>
    document.addEventListener('click', function (event) {
      const openButton = event.target.closest('[data-modal-id]');
      const closeButton = event.target.closest('.rae-reserva-modal-close');

      if (openButton) {
        const modal = document.getElementById(openButton.dataset.modalId);

        if (modal) {
          modal.hidden = false;
        }
      }

      if (closeButton) {
        const modal = closeButton.closest('.rae-delete-reserva-modal, .rae-cancel-reserva-modal');

        if (modal) {
          modal.hidden = true;
        }
      }

      if (
        event.target.classList.contains('rae-delete-reserva-modal') ||
        event.target.classList.contains('rae-cancel-reserva-modal')
      ) {
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
    'completa' => 'Jornada Completa',
  ];

  return $jornadas[$jornada] ?? $jornada;
}

function rae_nombre_estado_reserva($estado) {
  $estados = [
    'pendiente' => 'Pendiente',
    'pendiente_pago' => 'Pendiente de pago',
    'pagado' => 'Pagado',
    'confirmada' => 'Confirmada',
    'rechazada' => 'Rechazada',
    'cancelada' => 'Cancelada',
    'expirada' => 'Expirada',
    'pago_revision' => 'Pago requiere revisión',
  ];

  return $estados[$estado] ?? ucfirst((string) $estado);
}

function rae_nombre_estado_pago($estado) {
  $estados = [
    '' => 'Sin pago',
    'pending' => 'Pendiente',
    'pending_vobo' => 'Pendiente',
    'approved' => 'Aprobado',
    'declined' => 'Rechazado',
    'voided' => 'Anulado',
    'error' => 'Error',
    'expired' => 'Caducado',
  ];

  return $estados[strtolower((string) $estado)] ?? ucfirst((string) $estado);
}

function rae_formatear_pesos_colombianos($amount) {
  return '$' . number_format(absint($amount), 0, ',', '.');
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
