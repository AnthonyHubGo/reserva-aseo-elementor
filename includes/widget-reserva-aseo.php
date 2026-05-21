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
    ?>

    <form id="rae-form" class="rae-form">
      <input type="text" name="nombre" placeholder="Nombre completo" required>
      <input type="email" name="email" placeholder="Correo electrónico" required>

      <p><strong>Elige la persona para tu servicio:</strong></p>

      <div class="rae-personal-grid">
        <?php foreach ($personal as $persona): ?>
          <label class="rae-person-card">
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

      <input type="date" name="fecha" required>

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