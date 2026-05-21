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
      <input type="text" name="nombre" placeholder="Nombre completo" required>
      <input type="email" name="email" placeholder="Correo electrónico" required>
      <input type="date" name="fecha" required>

      <p><strong>Elige la persona para tu servicio:</strong></p>

      <div class="rae-personal-grid">
        <?php foreach ($personal as $persona): ?>
          <?php
          $fechas_no_disponibles = get_post_meta($persona->ID, '_rae_fechas_no_disponibles', true);

          if (!is_array($fechas_no_disponibles)) {
            $fechas_no_disponibles = [];
          }
          ?>

          <label
            class="rae-person-card"
            data-unavailable-dates="<?php echo esc_attr(wp_json_encode(array_values($fechas_no_disponibles))); ?>"
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

      <p class="rae-no-personal-date" hidden>No hay personal disponible para la fecha seleccionada.</p>

      <select name="jornada" required>
        <option value="">Selecciona jornada</option>
        <option value="manana">Mañana</option>
        <option value="tarde">Tarde</option>
        <option value="completa">Día completa</option>
      </select>

      <button type="submit">Reservar servicio</button>

      <div id="rae-msg"></div>
    </form>

    <?php
  }
}
