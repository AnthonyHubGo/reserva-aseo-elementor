<?php

if (!defined('ABSPATH')) exit;

function rae_email_nombre_jornada($jornada) {
  $jornadas = [
    'manana' => 'Mañana',
    'tarde' => 'Tarde',
    'completa' => 'Jornada Completa',
  ];

  return $jornadas[$jornada] ?? $jornada;
}

function rae_email_nombre_estado($estado) {
  $estados = [
    'pendiente' => 'Pendiente',
    'pendiente_pago' => 'Pendiente de pago',
    'pagado' => 'Pagado',
    'confirmada' => 'Confirmada',
    'rechazada' => 'Rechazada',
    'cancelada' => 'Cancelada',
  ];

  return $estados[$estado] ?? ucfirst((string) $estado);
}

function rae_email_asunto_estado($estado) {
  if ($estado === 'pendiente' || $estado === 'pendiente_pago') {
    return 'Tu reserva con SAT esta pendiente';
  }

  return 'Tu reserva con SAT ha sido ' . strtolower(rae_email_nombre_estado($estado));
}

function rae_email_headers() {
  return ['Content-Type: text/html; charset=UTF-8'];
}

function rae_notification_email() {
  $email = sanitize_email(get_option('rae_notification_email', ''));

  return is_email($email) ? $email : '';
}

function rae_email_logo_url() {
  $logo_id = absint(get_option('rae_email_logo_id'));

  return $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
}

function rae_email_brand_header() {
  $logo_url = rae_email_logo_url();

  if ($logo_url) {
    return '<img src="' . esc_url($logo_url) . '" alt="SAT" style="display: block; max-width: 160px; height: auto;">';
  }

  return '
    <div style="color: #ffffff; font-size: 26px; font-weight: 800; letter-spacing: 0;">SAT</div>
    <div style="margin-top: 4px; color: #e8fbfc; font-size: 14px;">Servicio de aseo doméstico</div>';
}

function rae_email_row($label, $value) {
  return '
    <tr>
      <td style="padding: 10px 12px; border-bottom: 1px solid #d8edf0; color: #094c69; font-weight: 700; width: 42%;">' . esc_html($label) . '</td>
      <td style="padding: 10px 12px; border-bottom: 1px solid #d8edf0; color: #273b44;">' . esc_html($value) . '</td>
    </tr>';
}

function rae_email_section($title, $rows) {
  return '
    <h2 style="margin: 26px 0 10px; color: #094c69; font-size: 18px; line-height: 1.3;">' . esc_html($title) . '</h2>
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border: 1px solid #aadde3; border-radius: 10px; border-collapse: separate; border-spacing: 0; overflow: hidden; background: #ffffff;">
      ' . implode('', $rows) . '
    </table>';
}

function rae_email_template($heading, $intro, $cliente_rows, $reserva_rows, $footer_text) {
  return '<!doctype html>
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
      body, table, td, h1, h2, p, div {
        font-family: "Nunito", Arial, sans-serif !important;
      }
    </style>
  </head>
  <body style="margin: 0; padding: 0; background: #f4fbfc; font-family: &quot;Nunito&quot;, Arial, sans-serif; color: #273b44;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background: #f4fbfc; padding: 28px 12px; font-family: &quot;Nunito&quot;, Arial, sans-serif;">
      <tr>
        <td align="center">
          <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width: 640px; border-collapse: collapse;">
            <tr>
              <td style="padding: 22px 26px; border-radius: 14px 14px 0 0; background: #00939a;">
                ' . rae_email_brand_header() . '
              </td>
            </tr>
            <tr>
              <td style="padding: 28px 26px; border: 1px solid #aadde3; border-top: 0; border-radius: 0 0 14px 14px; background: #ffffff; box-shadow: 0 18px 42px rgba(9, 76, 105, 0.12);">
                <h1 style="margin: 0 0 16px; color: #094c69; font-size: 24px; line-height: 1.25;">' . esc_html($heading) . '</h1>
                <div style="color: #273b44; font-size: 16px; line-height: 1.65;">' . wp_kses_post(wpautop($intro)) . '</div>
                ' . rae_email_section('Detalles del cliente', $cliente_rows) . '
                ' . rae_email_section('Detalles de la reserva', $reserva_rows) . '
                <p style="margin: 26px 0 0; color: #273b44; font-size: 16px; line-height: 1.65;">' . esc_html($footer_text) . '</p>
              </td>
            </tr>
            <tr>
              <td align="center" style="padding: 18px 12px 0; color: #949899; font-size: 13px;">
                SAT
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>';
}

function rae_email_cliente_rows($reserva) {
  return [
    rae_email_row('Nombre', $reserva->cliente_nombre ?? ''),
    rae_email_row('Correo electrónico', $reserva->cliente_email ?? ''),
    rae_email_row('Teléfono de contacto', $reserva->cliente_telefono ?? ''),
    rae_email_row('Ciudad', $reserva->cliente_ciudad ?? ''),
    rae_email_row('Barrio', $reserva->cliente_barrio ?? ''),
    rae_email_row('Dirección', $reserva->cliente_direccion ?? ''),
    rae_email_row('Casa / Apartamento', $reserva->cliente_casa ?? ''),
  ];
}

function rae_email_reserva_rows($reserva, $estado_label, $estado_field_label = 'Estado de la reserva') {
  $rows = [
    rae_email_row('Persona seleccionada', get_the_title(absint($reserva->persona_id ?? 0))),
    rae_email_row('Fecha del servicio', $reserva->fecha ?? ''),
    rae_email_row('Jornada', rae_email_nombre_jornada($reserva->jornada ?? '')),
    rae_email_row($estado_field_label, $estado_label),
  ];

  $motivo_cancelacion = trim((string) ($reserva->cancelacion_motivo ?? ''));

  if (strtolower((string) $estado_label) === 'cancelada' && $motivo_cancelacion !== '') {
    $rows[] = rae_email_row('Motivo de cancelación', $motivo_cancelacion);
  }

  return $rows;
}

function rae_enviar_email_reserva_creada($reserva) {
  if (!$reserva || !is_email($reserva->cliente_email ?? '')) {
    return false;
  }

  $asunto = rae_email_asunto_estado('pendiente_pago');
  $intro = sprintf(
    'Hola %s,<br><br>Gracias por reservar con SAT.<br><br>Hemos recibido tu solicitud de servicio de aseo doméstico. Tu reserva se encuentra pendiente de pago. Te enviaremos una nueva notificación cuando Wompi confirme el resultado del pago.',
    esc_html($reserva->cliente_nombre)
  );
  $mensaje = rae_email_template(
    'Hemos recibido tu solicitud de reserva',
    $intro,
    rae_email_cliente_rows($reserva),
    rae_email_reserva_rows($reserva, 'Pendiente de pago', 'Estado'),
    'Gracias por confiar en SAT.'
  );

  $cliente_enviado = wp_mail($reserva->cliente_email, $asunto, $mensaje, rae_email_headers());
  rae_enviar_email_notificacion_interna($reserva, 'pendiente_pago', 'Nueva reserva pendiente de pago');

  return $cliente_enviado;
}

function rae_enviar_email_reserva_estado($reserva, $nuevo_estado) {
  if (!$reserva || !is_email($reserva->cliente_email ?? '')) {
    return false;
  }

  $estado_label = rae_email_nombre_estado($nuevo_estado);
  $asunto = rae_email_asunto_estado($nuevo_estado);
  $intro = sprintf(
    'Hola %s,<br><br>Te informamos que el estado de tu reserva ha sido actualizado.',
    esc_html($reserva->cliente_nombre)
  );
  $mensaje = rae_email_template(
    'Actualización del estado de tu reserva',
    $intro,
    rae_email_cliente_rows($reserva),
    rae_email_reserva_rows($reserva, $estado_label, 'Nuevo estado'),
    'Si tienes alguna pregunta o necesitas ajustar la información de tu reserva, comunícate con nuestro equipo de atención. Gracias por confiar en SAT.'
  );

  $cliente_enviado = wp_mail($reserva->cliente_email, $asunto, $mensaje, rae_email_headers());
  rae_enviar_email_notificacion_interna($reserva, $nuevo_estado, 'Estado de reserva actualizado');

  return $cliente_enviado;
}

function rae_enviar_email_notificacion_interna($reserva, $estado, $heading) {
  $notification_email = rae_notification_email();

  if (!$notification_email || !$reserva) {
    return false;
  }

  $estado_label = rae_email_nombre_estado($estado);
  $payment_reference = trim((string) ($reserva->payment_reference ?? ''));
  $wompi_transaction_id = trim((string) ($reserva->wompi_transaction_id ?? ''));
  $intro = 'Se registró una actualización en una reserva de aseo. Revisa los detalles para seguimiento operativo.';
  $reserva_rows = rae_email_reserva_rows($reserva, $estado_label, 'Estado');

  if ($payment_reference !== '') {
    $reserva_rows[] = rae_email_row('Referencia de pago', $payment_reference);
  }

  if ($wompi_transaction_id !== '') {
    $reserva_rows[] = rae_email_row('Transacción Wompi', $wompi_transaction_id);
  }

  $mensaje = rae_email_template(
    $heading,
    $intro,
    rae_email_cliente_rows($reserva),
    $reserva_rows,
    'Este correo fue enviado automáticamente desde el sistema de reservas SAT.'
  );

  return wp_mail(
    $notification_email,
    '[SAT Reservas] ' . $heading,
    $mensaje,
    rae_email_headers()
  );
}
