<?php
/**
 * Vaporis · Delivery por distrito
 *
 * NO es un plugin suelto: lo carga vaporis-checkout-pe.php (necesita Vaporis_Ubigeo).
 *
 * Método de envío "Delivery Vaporis" (id vaporis_delivery). Se agrega en
 * WooCommerce → Ajustes → Envío a las zonas de Perú, en lugar de la tarifa plana
 * y el envío gratuito. Decide el costo por el DISTRITO (código de ubigeo), no por
 * el departamento:
 *
 *   Lima A   → S/ 15, gratis desde S/ 499 (distritos céntricos de Lima y Callao)
 *   Lima B   → S/ 20, gratis desde S/ 499 (periferia: norte, este y balnearios del sur)
 *   Provincia→ S/ 25, gratis desde S/ 699 (todo lo demás, incluidas las provincias
 *              del departamento de Lima: Huaral, Cañete, Huaura, Barranca, etc.)
 *
 * Costos y mínimos se editan en el propio método (WooCommerce → Envío → zona →
 * Delivery Vaporis). La lista de distritos de cada grupo vive aquí abajo.
 */

if ( ! defined('ABSPATH') ) exit;

/** Distritos del grupo Lima A (S/ 15). Código de ubigeo => nombre (solo referencia). */
function vaporis_delivery_lima_a() {
    return apply_filters('vaporis_delivery_lima_a', [
        // Lima
        '150101' => 'Cercado de Lima',
        '150103' => 'Ate',
        '150104' => 'Barranco',
        '150105' => 'Breña',
        '150108' => 'Chorrillos',
        '150110' => 'Comas',
        '150111' => 'El Agustino',
        '150112' => 'Independencia',
        '150113' => 'Jesús María',
        '150114' => 'La Molina',
        '150115' => 'La Victoria',
        '150116' => 'Lince',
        '150117' => 'Los Olivos',
        '150120' => 'Magdalena del Mar',
        '150121' => 'Pueblo Libre',             // antes figuraba como "Magdalena Vieja"
        '150122' => 'Miraflores',
        '150128' => 'Rímac',
        '150130' => 'San Borja',
        '150131' => 'San Isidro',
        '150132' => 'San Juan de Lurigancho',
        '150133' => 'San Juan de Miraflores',
        '150134' => 'San Luis',
        '150135' => 'San Martín de Porres',
        '150136' => 'San Miguel',
        '150137' => 'Santa Anita',
        '150140' => 'Santiago de Surco',
        '150141' => 'Surquillo',
        '150142' => 'Villa El Salvador',
        '150143' => 'Villa María del Triunfo',
        // Callao
        '070101' => 'Callao',
        '070102' => 'Bellavista',
        '070103' => 'Carmen de la Legua Reynoso',
        '070104' => 'La Perla',
        '070105' => 'La Punta',
    ]);
}

/** Distritos del grupo Lima B (S/ 20). */
function vaporis_delivery_lima_b() {
    return apply_filters('vaporis_delivery_lima_b', [
        '150102' => 'Ancón',
        '150139' => 'Santa Rosa',
        '070106' => 'Ventanilla',
        '070107' => 'Mi Perú',
        '150125' => 'Puente Piedra',
        '150106' => 'Carabayllo',
        '150118' => 'Lurigancho (Chosica)',
        '150107' => 'Chaclacayo',
        '150109' => 'Cieneguilla',
        '150123' => 'Pachacámac',
        '150119' => 'Lurín',
        '150126' => 'Punta Hermosa',
        '150127' => 'Punta Negra',
        '150129' => 'San Bartolo',
        '150138' => 'Santa María del Mar',
        '150124' => 'Pucusana',
    ]);
}

/**
 * Ubigeo del destino del paquete (departamento + distrito elegidos en el checkout).
 * Devuelve '' si todavía no hay distrito (p. ej. en el carrito antes del checkout).
 */
function vaporis_delivery_ubigeo($destino) {
    if ( ! class_exists('Vaporis_Ubigeo') ) return '';
    if ( ( $destino['country'] ?? 'PE' ) !== 'PE' ) return '';

    $dep  = Vaporis_Ubigeo::dep_from_state( $destino['state'] ?? '' );
    $dist = (string) ( $destino['city'] ?? '' );
    if ( $dep === '' || $dist === '' ) return '';

    $lista = Vaporis_Ubigeo::distritos_de($dep);
    if ( isset($lista[$dist]) ) return $lista[$dist][1];

    // Por si llega el nombre con otra capitalización o sin tildes
    $k = Vaporis_Ubigeo::norm($dist);
    foreach ( $lista as $etiqueta => $par ) {
        if ( Vaporis_Ubigeo::norm($etiqueta) === $k ) return $par[1];
    }
    return '';
}

/**
 * Grupo del destino: 'lima_a' | 'lima_b' | 'provincia'.
 * Sin distrito aún: Lima/Callao se asumen Lima A (el checkout exige distrito,
 * así que el costo final siempre sale del distrito real).
 */
function vaporis_delivery_grupo($destino) {
    $ubigeo = vaporis_delivery_ubigeo($destino);
    if ( $ubigeo !== '' ) {
        if ( isset( vaporis_delivery_lima_a()[$ubigeo] ) ) return 'lima_a';
        if ( isset( vaporis_delivery_lima_b()[$ubigeo] ) ) return 'lima_b';
        return 'provincia';
    }
    $dep = class_exists('Vaporis_Ubigeo') ? Vaporis_Ubigeo::dep_from_state( $destino['state'] ?? '' ) : '';
    return in_array($dep, ['Lima', 'Callao'], true) || $dep === '' ? 'lima_a' : 'provincia';
}


add_action('woocommerce_shipping_init', function () {

    if ( class_exists('Vaporis_Delivery_Method') ) return;

    class Vaporis_Delivery_Method extends WC_Shipping_Method {

        public function __construct($instance_id = 0) {
            $this->id                 = 'vaporis_delivery';
            $this->instance_id        = absint($instance_id);
            $this->method_title       = __('Delivery Vaporis (por distrito)', 'vaporis');
            $this->method_description = __('Costo según el distrito: Lima A, Lima B o Provincia. Gratis desde el mínimo de cada grupo. La lista de distritos está en vaporis-delivery.php.', 'vaporis');
            $this->supports           = ['shipping-zones', 'instance-settings', 'instance-settings-modal'];
            $this->init();
        }

        public function init() {
            $this->instance_form_fields = [
                'title'          => ['title' => __('Nombre', 'vaporis'), 'type' => 'text', 'default' => 'Delivery'],
                'titulo_gratis'  => ['title' => __('Nombre cuando es gratis', 'vaporis'), 'type' => 'text', 'default' => 'Delivery gratis'],
                'lima_a_costo'   => ['title' => 'Lima A · costo (S/)', 'type' => 'price', 'default' => '15'],
                'lima_a_minimo'  => ['title' => 'Lima A · gratis desde (S/)', 'type' => 'price', 'default' => '499'],
                'lima_b_costo'   => ['title' => 'Lima B · costo (S/)', 'type' => 'price', 'default' => '20'],
                'lima_b_minimo'  => ['title' => 'Lima B · gratis desde (S/)', 'type' => 'price', 'default' => '499'],
                'prov_costo'     => ['title' => 'Provincia · costo (S/)', 'type' => 'price', 'default' => '25'],
                'prov_minimo'    => ['title' => 'Provincia · gratis desde (S/)', 'type' => 'price', 'default' => '699'],
            ];
            $this->title = $this->get_option('title', 'Delivery');
            add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
        }

        /** Subtotal que cuenta para el mínimo: productos con descuentos aplicados */
        private function monto_pedido() {
            $cart = WC()->cart;
            if ( ! $cart ) return 0;
            $total = (float) $cart->get_displayed_subtotal();
            $total -= (float) $cart->get_discount_total();
            if ( $cart->display_prices_including_tax() ) $total -= (float) $cart->get_discount_tax();
            return max(0, $total);
        }

        public function calculate_shipping($package = []) {
            $grupo = vaporis_delivery_grupo( $package['destination'] ?? [] );
            $pref  = [ 'lima_a' => 'lima_a', 'lima_b' => 'lima_b', 'provincia' => 'prov' ][$grupo];

            $costo  = (float) wc_format_decimal( $this->get_option($pref . '_costo') );
            $minimo = (float) wc_format_decimal( $this->get_option($pref . '_minimo') );
            $gratis = $minimo > 0 && $this->monto_pedido() >= $minimo;

            $this->add_rate([
                'id'        => $this->get_rate_id(),
                'label'     => $gratis ? $this->get_option('titulo_gratis', 'Delivery gratis') : $this->title,
                'cost'      => $gratis ? 0 : $costo,
                'taxes'     => false,
                'package'   => $package,
                'meta_data' => [ 'Grupo de delivery' => $grupo ],
            ]);
        }
    }
});

add_filter('woocommerce_shipping_methods', function ($methods) {
    $methods['vaporis_delivery'] = 'Vaporis_Delivery_Method';
    return $methods;
});

/**
 * El costo depende del distrito: al cambiarlo hay que recalcular el checkout.
 * (Woo ya lo hace con los select de dirección, esto es por si Bricks lo pinta distinto.)
 */
add_action('wp_footer', function () {
    if ( ! function_exists('is_checkout') || ! is_checkout() || is_order_received_page() ) return;
    ?>
    <script>
    jQuery(function ($) {
        $(document.body).on('change', '#billing_city, #shipping_city', function () {
            $(document.body).trigger('update_checkout');
        });
    });
    </script>
    <?php
}, 99);
