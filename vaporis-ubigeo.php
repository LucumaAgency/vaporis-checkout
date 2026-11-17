<?php
/**
 * Vaporis · Ubigeo Perú — Departamento -> Distrito
 *
 * NO es un plugin suelto: lo carga vaporis-checkout-pe.php.
 *
 * v2: se eliminó el campo Provincia del formulario. El cliente elige
 * Departamento y luego Distrito, y la lista de distritos es la del departamento
 * completo (todas sus provincias juntas).
 *
 * La provincia NO se pierde: se deduce del distrito elegido y se guarda en el
 * pedido junto al código de ubigeo, que es lo que piden facturación y couriers.
 *
 * Desambiguación: dentro de un mismo departamento hay nombres de distrito que se
 * repiten en provincias distintas (en Lima: Miraflores, San Luis, San Antonio y
 * Laraos). Para esos, y SOLO esos, la opción se muestra y se guarda como
 * "Nombre (Provincia)", que es como se escribe en una dirección de todos modos.
 */

if ( ! defined('ABSPATH') ) exit;

if ( ! defined('VAPORIS_UBIGEO_VER') ) define('VAPORIS_UBIGEO_VER', '2.2.0');

class Vaporis_Ubigeo {

    /** @var array|null Árbol departamento => provincia => [[codigo, distrito], ...] */
    private static $data = null;

    /** @var array|null Cache de listas planas por departamento */
    private static $planos = [];

    public static function init() {
        add_filter('woocommerce_states',                 [__CLASS__, 'states_pe'], 20);
        add_filter('woocommerce_default_address_fields', [__CLASS__, 'address_fields'], 30);
        // El de verdad decisivo: se aplica después de los locales de país,
        // que es donde se perdía el type=select en la v1.
        add_filter('woocommerce_checkout_fields',        [__CLASS__, 'checkout_fields'], 100);
        add_filter('woocommerce_billing_fields',         [__CLASS__, 'account_fields'], 100);
        add_filter('woocommerce_shipping_fields',        [__CLASS__, 'account_fields'], 100);
        // Woo cachea los locales; hay que declarar city como select también ahí.
        add_filter('woocommerce_get_country_locale',     [__CLASS__, 'country_locale'], 100);

        add_action('wp_enqueue_scripts',                 [__CLASS__, 'enqueue'], 20);
        add_action('woocommerce_after_checkout_validation', [__CLASS__, 'validate'], 20, 2);
        add_action('woocommerce_checkout_create_order',  [__CLASS__, 'save_order'], 20, 2);
        add_action('woocommerce_admin_order_data_after_shipping_address', [__CLASS__, 'admin_display']);
    }

    /** Carga y cachea el JSON de ubigeo */
    public static function data() {
        if ( self::$data === null ) {
            $file = __DIR__ . '/data/ubigeo-peru.json';
            $json = is_readable($file) ? file_get_contents($file) : '';
            self::$data = $json ? (json_decode($json, true) ?: []) : [];
            if ( ! self::$data ) {
                error_log('[Vaporis Ubigeo] No se pudo leer ' . $file);
            }
        }
        return self::$data;
    }

    /**
     * Lista plana de distritos de un departamento: etiqueta => [provincia, ubigeo].
     * La etiqueta lleva "(Provincia)" solo si el nombre se repite dentro del depto.
     */
    public static function distritos_de($dep) {
        if ( isset(self::$planos[$dep]) ) return self::$planos[$dep];

        $tree = self::data();
        if ( ! isset($tree[$dep]) ) return self::$planos[$dep] = [];

        // Primero contamos repeticiones de nombre dentro del departamento
        $veces = [];
        foreach ( $tree[$dep] as $prov => $dists ) {
            foreach ( $dists as $par ) {
                $veces[ $par[1] ] = ( $veces[ $par[1] ] ?? 0 ) + 1;
            }
        }

        $out = [];
        foreach ( $tree[$dep] as $prov => $dists ) {
            foreach ( $dists as $par ) {
                list($codigo, $nombre) = $par;
                $etiqueta = ( $veces[$nombre] > 1 ) ? $nombre . ' (' . $prov . ')' : $nombre;
                $out[$etiqueta] = [$prov, $codigo];
            }
        }

        uksort($out, function ($a, $b) {
            return strcoll(
                remove_accents(mb_strtolower($a)),
                remove_accents(mb_strtolower($b))
            );
        });

        return self::$planos[$dep] = $out;
    }

    /** Normaliza para cruzar nombres entre Woo y nuestro dataset */
    public static function norm($s) {
        $s = remove_accents( (string) $s );
        return preg_replace('/[^a-z0-9]+/', ' ', strtolower( trim($s) ));
    }

    /**
     * Departamentos como estados de Perú, respetando los códigos que ya trae Woo
     * para no romper pedidos ni zonas de envío existentes.
     */
    public static function states_pe($states) {
        $data = self::data();
        if ( ! $data ) return $states;

        $actuales = ( isset($states['PE']) && is_array($states['PE']) ) ? $states['PE'] : [];

        $por_nombre = [];
        foreach ( $actuales as $code => $name ) {
            $por_nombre[ self::norm($name) ] = $code;
        }

        $out = [];
        foreach ( array_keys($data) as $dep ) {
            $code = $por_nombre[ self::norm($dep) ]
                 ?? strtoupper( substr( preg_replace('/[^A-Za-z]/', '', remove_accents($dep)), 0, 3) );
            while ( isset($out[$code]) && $out[$code] !== $dep ) $code .= 'X';
            $out[$code] = $dep;
        }

        $states['PE'] = $out;
        return $states;
    }

    /** Opciones del select de distrito para el valor de departamento actual */
    private static function opciones_distrito($dep) {
        $lista = $dep ? self::distritos_de($dep) : [];
        if ( ! $lista ) {
            return ['' => __('Elige primero el departamento', 'vaporis')];
        }
        $opts = ['' => __('— Selecciona distrito —', 'vaporis')];
        foreach ( array_keys($lista) as $etiqueta ) {
            $opts[$etiqueta] = $etiqueta;
        }
        return $opts;
    }

    /** Convierte city en select y renombra state */
    private static function moldear(&$campo, $es_city, $dep = '') {
        if ( $es_city ) {
            $campo['type']     = 'select';
            $campo['label']    = __('Distrito', 'vaporis');
            $campo['required'] = true;
            $campo['options']  = self::opciones_distrito($dep);
            $campo['class']    = array_values( array_unique( array_merge(
                (array) ( $campo['class'] ?? [] ), ['form-row-wide', 'vaporis-distrito']
            ) ) );
            $campo['input_class'] = ['vaporis-distrito-select'];
            unset($campo['placeholder'], $campo['autocomplete'], $campo['validate']);
        } else {
            $campo['label']    = __('Departamento', 'vaporis');
            $campo['required'] = true;
        }
    }

    public static function address_fields($fields) {
        if ( isset($fields['city']) )  self::moldear($fields['city'], true);
        if ( isset($fields['state']) ) self::moldear($fields['state'], false);
        return $fields;
    }

    /**
     * Locales por país: Woo los aplica DESPUÉS de default_address_fields, así que
     * si no se declara aquí, el `type` vuelve a "text". Esta era la causa de que
     * el distrito siguiera siendo un input de texto.
     */
    public static function country_locale($locale) {
        if ( ! isset($locale['PE']) ) $locale['PE'] = [];
        $locale['PE']['city'] = array_merge(
            (array) ( $locale['PE']['city'] ?? [] ),
            ['type' => 'select', 'required' => true, 'label' => __('Distrito', 'vaporis')]
        );
        $locale['PE']['state'] = array_merge(
            (array) ( $locale['PE']['state'] ?? [] ),
            ['required' => true, 'label' => __('Departamento', 'vaporis')]
        );
        return $locale;
    }

    /** Último filtro en aplicarse en el checkout: aquí se gana siempre */
    public static function checkout_fields($fields) {
        foreach ( ['billing', 'shipping'] as $g ) {
            if ( ! isset($fields[$g]) ) continue;

            // v1 dejaba un campo provincia; si quedó guardado en algún sitio, fuera.
            unset($fields[$g][$g . '_provincia']);

            $dep = self::dep_actual($g);

            if ( isset($fields[$g][$g . '_city']) ) {
                self::moldear($fields[$g][$g . '_city'], true, $dep);
            }
            if ( isset($fields[$g][$g . '_state']) ) {
                self::moldear($fields[$g][$g . '_state'], false);
            }
        }
        return $fields;
    }

    /** Lo mismo para las direcciones de Mi cuenta */
    public static function account_fields($fields) {
        if ( is_admin() && ! wp_doing_ajax() ) return $fields;
        foreach ( ['billing_', 'shipping_'] as $p ) {
            if ( isset($fields[$p . 'city']) )  self::moldear($fields[$p . 'city'], true, self::dep_actual(rtrim($p, '_')));
            if ( isset($fields[$p . 'state']) ) self::moldear($fields[$p . 'state'], false);
        }
        return $fields;
    }

    /** Departamento que el cliente tiene puesto ahora mismo (POST o perfil) */
    private static function dep_actual($grupo) {
        $code = '';
        if ( isset($_POST[$grupo . '_state']) ) {
            $code = sanitize_text_field( wp_unslash($_POST[$grupo . '_state']) );
        } elseif ( function_exists('WC') && WC()->customer ) {
            $code = ( $grupo === 'shipping' )
                ? WC()->customer->get_shipping_state()
                : WC()->customer->get_billing_state();
        }
        return self::dep_from_state($code);
    }

    /** Carga el JS solo donde hace falta */
    public static function enqueue() {
        if ( ! function_exists('is_checkout') ) return;

        // Con plantillas de Bricks, is_checkout() a veces devuelve false. Se
        // comprueba además el ID de la página de checkout configurada en Woo.
        $es_checkout = is_checkout()
            || is_account_page()
            || ( function_exists('wc_get_page_id') && get_queried_object_id() === wc_get_page_id('checkout') )
            || ( function_exists('wc_get_page_id') && get_queried_object_id() === wc_get_page_id('myaccount') );

        /** Escotilla por si hiciera falta forzarlo desde el tema */
        if ( ! apply_filters('vaporis_ubigeo_cargar_js', $es_checkout) ) return;

        $data = self::data();
        if ( ! $data ) return;

        wp_enqueue_script(
            'vaporis-ubigeo',
            plugins_url('assets/vaporis-ubigeo.js', __FILE__),
            ['jquery'],
            VAPORIS_UBIGEO_VER,
            true
        );

        // Al JS se le manda ya aplanado: departamento => [etiquetas de distrito]
        $plano = [];
        foreach ( array_keys($data) as $dep ) {
            $plano[$dep] = array_keys( self::distritos_de($dep) );
        }

        $estados = [];
        if ( function_exists('WC') && WC()->countries ) {
            $todos = WC()->countries->get_states('PE');
            if ( is_array($todos) ) $estados = $todos;
        }

        wp_localize_script('vaporis-ubigeo', 'VAPORIS_UBIGEO', [
            'tree'   => $plano,
            'states' => $estados,
            'i18n'   => [
                'pickDep'    => __('Elige primero el departamento', 'vaporis'),
                'selDist'    => __('— Selecciona distrito —', 'vaporis'),
                // Por si Bricks pinta su propia etiqueta y sigue diciendo "Población"
                'labelCity'  => __('Distrito', 'vaporis'),
                'labelState' => __('Departamento', 'vaporis'),
            ],
        ]);
    }

    /** El distrito enviado tiene que existir en el departamento enviado */
    public static function validate($data, $errors) {
        if ( ! self::data() ) return;

        foreach ( ['billing', 'shipping'] as $g ) {
            if ( $g === 'shipping' && empty($data['ship_to_different_address']) ) continue;
            if ( ($data[$g . '_country'] ?? 'PE') !== 'PE' ) continue;

            $dep  = self::dep_from_state( $data[$g . '_state'] ?? '' );
            $dist = $data[$g . '_city'] ?? '';
            if ( $dep === '' || $dist === '' ) continue; // Woo ya avisa de los requeridos

            if ( ! isset( self::distritos_de($dep)[$dist] ) ) {
                $errors->add($g . '_city', __('El distrito elegido no pertenece a ese departamento.', 'vaporis'));
            }
        }
    }

    /** Código de estado de Woo -> nombre de departamento del árbol */
    public static function dep_from_state($code) {
        if ( $code === '' || $code === null ) return '';
        $tree = self::data();
        if ( isset($tree[$code]) ) return $code;

        $states = ( function_exists('WC') && WC()->countries ) ? WC()->countries->get_states('PE') : [];
        $nombre = $states[$code] ?? '';
        if ( $nombre !== '' && isset($tree[$nombre]) ) return $nombre;

        $k = self::norm($nombre !== '' ? $nombre : $code);
        foreach ( array_keys($tree) as $dep ) {
            if ( self::norm($dep) === $k ) return $dep;
        }
        return '';
    }

    /** Guarda provincia deducida y código de ubigeo (HPOS-safe) */
    public static function save_order($order, $data) {
        foreach ( ['billing', 'shipping'] as $g ) {
            $dep  = self::dep_from_state( $_POST[$g . '_state'] ?? '' );
            $dist = isset($_POST[$g . '_city'])
                ? sanitize_text_field( wp_unslash($_POST[$g . '_city']) ) : '';
            if ( $dep === '' || $dist === '' ) continue;

            $lista = self::distritos_de($dep);
            if ( ! isset($lista[$dist]) ) continue;

            list($prov, $codigo) = $lista[$dist];
            $order->update_meta_data('_' . $g . '_provincia', $prov);
            $order->update_meta_data('_' . $g . '_ubigeo', $codigo);
        }
    }

    /** Provincia y ubigeo visibles en el pedido del admin */
    public static function admin_display($order) {
        foreach ( ['billing' => 'Facturación', 'shipping' => 'Envío'] as $g => $etiqueta ) {
            $prov = $order->get_meta('_' . $g . '_provincia');
            $ubi  = $order->get_meta('_' . $g . '_ubigeo');
            if ( ! $prov && ! $ubi ) continue;
            echo '<p><strong>' . esc_html($etiqueta) . ' · ' . esc_html__('Provincia', 'vaporis') . ':</strong> '
               . esc_html($prov ?: '—')
               . ( $ubi ? ' <small>(ubigeo ' . esc_html($ubi) . ')</small>' : '' )
               . '</p>';
        }
    }
}

add_action('plugins_loaded', ['Vaporis_Ubigeo', 'init']);

/** Compatibilidad HPOS */
add_action('before_woocommerce_init', function () {
    if ( class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            defined('VAPORIS_CHECKOUT_PE_DIR') ? VAPORIS_CHECKOUT_PE_DIR . '/vaporis-checkout-pe.php' : __FILE__,
            true
        );
    }
});
