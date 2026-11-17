/**
 * Vaporis · Ubigeo Perú (v2)
 * Encadena Departamento (campo nativo `state`) -> Distrito (campo nativo `city`).
 *
 * Ya no hay campo Provincia: la lista de distritos es la del departamento entero.
 * Los nombres que se repiten dentro de un mismo departamento vienen ya
 * desambiguados desde PHP como "Nombre (Provincia)".
 *
 * Notas:
 *  - Woo repinta el checkout por AJAX (updated_checkout), así que hay que
 *    reenganchar y conservar lo que el cliente ya eligió.
 *  - El departamento llega como CÓDIGO de estado (ej. "LIM"); se traduce con
 *    VAPORIS_UBIGEO.states.
 */
(function ($) {
    'use strict';

    if (typeof VAPORIS_UBIGEO === 'undefined' || !VAPORIS_UBIGEO.tree) return;

    var TREE   = VAPORIS_UBIGEO.tree;   // { departamento: [etiquetas de distrito] }
    var STATES = VAPORIS_UBIGEO.states || {};
    var I18N   = VAPORIS_UBIGEO.i18n || {};

    function norm(s) {
        return (s || '')
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, ' ')
            .trim();
    }

    /** Código de estado de Woo -> nombre de departamento tal como está en el árbol */
    function depFromState(code) {
        if (!code) return '';
        if (TREE[code]) return code;

        var nombre = STATES[code] || '';
        if (TREE[nombre]) return nombre;

        var k = norm(nombre || code);
        for (var dep in TREE) {
            if (norm(dep) === k) return dep;
        }
        return '';
    }

    /**
     * Red de seguridad: si el campo llega como <input type="text">, se sustituye
     * por un <select> equivalente en el DOM.
     *
     * Hace falta porque el elemento "Checkout Customer Details" de Bricks arma el
     * markup por su cuenta y puede ignorar el `type => select` que declaramos en
     * los filtros de WooCommerce. El name y el id se conservan, así que el valor
     * viaja igual en el POST y la validación del servidor sigue mandando.
     */
    function asegurarSelect(grupo) {
        var el = document.getElementById(grupo + '_city');
        if (!el || el.tagName === 'SELECT') return el;
        if (el.tagName !== 'INPUT') return null;

        var sel = document.createElement('select');
        sel.id       = el.id;
        sel.name     = el.name;
        sel.className = (el.className || '')
            .replace(/\binput-text\b/g, '')
            .trim() + ' vaporis-distrito-select';
        if (el.required) sel.required = true;
        if (el.getAttribute('autocomplete')) sel.setAttribute('autocomplete', el.getAttribute('autocomplete'));

        // Conserva lo que hubiera escrito o traiga guardado el perfil
        var previo = el.value || '';
        if (previo) $(sel).data('vaporisValue', previo);

        el.parentNode.replaceChild(sel, el);

        if (window.console && console.info) {
            console.info('[Vaporis Ubigeo] ' + grupo + '_city convertido a <select> en el DOM ' +
                         '(el markup lo generó Bricks, no WooCommerce).');
        }
        return sel;
    }

    function sync(grupo, opciones) {
        opciones = opciones || {};

        asegurarSelect(grupo);

        var $city = $('#' + grupo + '_city');
        if (!$city.length || !$city.is('select')) return;   // país no PE, o campo ausente

        if (($('#' + grupo + '_country').val() || 'PE') !== 'PE') return;

        var dep       = depFromState($('#' + grupo + '_state').val());
        var distritos = (dep && TREE[dep]) ? TREE[dep] : [];
        var previo    = opciones.reset ? '' : ($city.data('vaporisValue') || $city.val() || '');
        var esSelect2 = $city.hasClass('select2-hidden-accessible');

        $city.empty().append($('<option>', {
            value: '',
            text: dep ? (I18N.selDist || '— Selecciona distrito —')
                      : (I18N.pickDep || 'Elige primero el departamento')
        }));

        distritos.forEach(function (d) {
            $city.append($('<option>', { value: d, text: d }));
        });

        $city.val(distritos.indexOf(previo) !== -1 ? previo : '');

        if (esSelect2) $city.trigger('change.select2');
    }

    /**
     * Si Bricks pinta su propia etiqueta, puede seguir diciendo "Población".
     * Se corrige en el DOM sin tocar el asterisco de campo obligatorio.
     */
    function arreglarEtiqueta(campoId, texto) {
        if (!texto) return;
        var lab = document.querySelector('label[for="' + campoId + '"]');
        if (!lab) return;

        var actual = (lab.childNodes[0] && lab.childNodes[0].nodeType === 3)
            ? lab.childNodes[0].nodeValue.trim() : '';
        if (actual === texto) return;

        if (lab.childNodes[0] && lab.childNodes[0].nodeType === 3) {
            lab.childNodes[0].nodeValue = texto + ' ';
        } else {
            lab.insertBefore(document.createTextNode(texto + ' '), lab.firstChild);
        }
    }

    function recordar(grupo) {
        var $city = $('#' + grupo + '_city');
        if ($city.length && $city.val()) $city.data('vaporisValue', $city.val());
    }

    function bind() {
        ['billing', 'shipping'].forEach(function (grupo) {
            sync(grupo);
            arreglarEtiqueta(grupo + '_city',  I18N.labelCity);
            arreglarEtiqueta(grupo + '_state', I18N.labelState);
        });
    }

    // Cambiar de departamento invalida el distrito anterior
    $(document.body).on('change', '#billing_state, #billing_country', function () {
        $('#billing_city').removeData('vaporisValue');
        sync('billing', { reset: true });
    });

    $(document.body).on('change', '#shipping_state, #shipping_country', function () {
        $('#shipping_city').removeData('vaporisValue');
        sync('shipping', { reset: true });
    });

    $(document.body).on('change', '#billing_city', function () { recordar('billing'); });
    $(document.body).on('change', '#shipping_city', function () { recordar('shipping'); });

    $(document.body).on('updated_checkout country_to_state_changed', bind);
    $(bind);

})(jQuery);
