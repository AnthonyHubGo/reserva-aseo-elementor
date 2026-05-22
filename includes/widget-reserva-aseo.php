<?php

if (!defined('ABSPATH')) exit;

use Elementor\Widget_Base;

class RAE_Widget_Reserva_Aseo extends Widget_Base {

  public function get_name() {
    return 'reserva_aseo';
  }

  public function get_title() {
    return 'Reserva de Aseo';
  }

  public function get_icon() {
    return 'eicon-calendar';
  }

  public function get_categories() {
    return ['general'];
  }

  public function get_style_depends() {
    return ['rae-nunito', 'rae-css'];
  }

  public function get_script_depends() {
    return ['rae-js'];
  }

  protected function render() {
    $personal = get_posts([
      'post_type' => 'personal_aseo',
      'numberposts' => -1,
      'post_status' => 'publish',
    ]);

    if (function_exists('rae_personal_aseo_disponible')) {
      $personal = array_values(array_filter($personal, function ($persona) {
        return rae_personal_aseo_disponible($persona->ID);
      }));
    }
    ?>

    <form id="rae-form" class="rae-form">
      <div class="rae-no-availability-alert" <?php echo empty($personal) ? '' : 'hidden'; ?>>
        No se puede reservar este día porque no hay personal disponible.
      </div>

      <input type="text" name="nombre" placeholder="Nombre completo" required>
      <input type="email" name="email" placeholder="Correo electrónico" required>
      <input type="tel" name="telefono" placeholder="Número de teléfono de contacto" required>
      <span class="rae-date-field">
        <input type="text" name="fecha" placeholder="Selecciona la fecha" required>
      </span>

      <select name="jornada" required>
        <option value="">Selecciona jornada</option>
        <option value="manana">Mañana</option>
        <option value="tarde">Tarde</option>
        <option value="completa">Jornada Completa</option>
      </select>

      <p><strong>Elige la persona para tu servicio:</strong></p>

      <div class="rae-personal-carousel">
        <button type="button" class="rae-carousel-arrow rae-carousel-prev" aria-label="Ver personal anterior">
          &#8249;
        </button>

        <div class="rae-personal-viewport">
          <div class="rae-personal-grid">
            <?php foreach ($personal as $persona): ?>
              <?php
              $fechas_no_disponibles = get_post_meta($persona->ID, '_rae_fechas_no_disponibles', true);

              if (!is_array($fechas_no_disponibles)) {
                $fechas_no_disponibles = [];
              }

              $ocupaciones = function_exists('rae_obtener_ocupaciones_personal')
                ? rae_obtener_ocupaciones_personal($persona->ID)
                : [];
              ?>

              <label
                class="rae-person-card"
                data-unavailable-dates="<?php echo esc_attr(wp_json_encode(array_values($fechas_no_disponibles))); ?>"
                data-occupations="<?php echo esc_attr(wp_json_encode($ocupaciones)); ?>"
              >
                <input type="radio" name="persona_id" value="<?php echo esc_attr($persona->ID); ?>" required>

                <div class="rae-person-photo">
                  <?php
                  if (has_post_thumbnail($persona->ID)) {
                    echo get_the_post_thumbnail($persona->ID, 'thumbnail');
                  } else {
                    echo '<div class="rae-no-photo">Sin foto</div>';
                  }
                  ?>
                </div>

                <span><?php echo esc_html($persona->post_title); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <button type="button" class="rae-carousel-arrow rae-carousel-next" aria-label="Ver personal siguiente">
          &#8250;
        </button>
      </div>

      <p class="rae-no-personal-date" hidden>No hay personal disponible para la fecha seleccionada.</p>

      <div class="rae-address-fields">
        <p><strong>Dirección del servicio:</strong></p>
        <input type="text" name="ciudad" placeholder="Ciudad" required>
        <input type="text" name="barrio" placeholder="Barrio" required>
        <input type="text" name="direccion" placeholder="Dirección" required>
        <input type="text" name="casa" placeholder="Casa / Apartamento" required>
      </div>

      <button type="submit">Reservar servicio</button>

      <div id="rae-msg"></div>
    </form>

    <?php
  }
}
