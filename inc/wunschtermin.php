<?php
/**
 * Der Wunschtermin.
 *
 * Kein Countdown, kein Druck: der Betrieb wählt den Tag selbst — weil
 * geschlossen ist, weil der Chefkoch frei hat oder weil dann am wenigsten
 * los ist. Gesperrt sind Sonntage und die hinterlegten Schließtage.
 *
 * Umgesetzt als Erweiterung der Store-API, weil auf sapelzashop.com der
 * Block-Checkout läuft. Der klassische Haken woocommerce_after_order_notes
 * greift dort nicht.
 *
 */

if (!defined('ABSPATH')) exit;

const SZ_TERMIN_SCHLUESSEL = '_sz_wunschtermin';

/**
 * Die wählbaren Tage: die nächsten zwei Wochen ohne Sonntage und ohne
 * Schließtage. Ab morgen — was heute bestellt wird, fährt frühestens
 * morgen mit.
 *
 * @return array<int, array{wert:string, tag:string, datum:string}>
 */
function sz_liefertage(): array
{
    $gesperrt = apply_filters('sz_schliesstage', [], get_current_user_id());
    $tage = [];

    for ($i = 1; $i <= 14 && count($tage) < 10; $i++) {
        $zeit = strtotime("+{$i} day");
        if ((int) wp_date('N', $zeit) === 7) continue;              // Sonntag
        $wert = wp_date('Y-m-d', $zeit);
        if (in_array($wert, $gesperrt, true)) continue;

        $tage[] = [
            'wert'  => $wert,
            'tag'   => wp_date('D', $zeit),
            'datum' => wp_date('j.n.', $zeit),
        ];
    }
    return $tage;
}

/**
 * Der gewählte Tag lebt in der Sitzung, bis die Bestellung entsteht.
 */
function sz_termin_gewaehlt(): string
{
    if (!function_exists('WC') || !WC()->session) return '';
    $wert = (string) WC()->session->get('sz_wunschtermin', '');
    foreach (sz_liefertage() as $tag) {
        if ($tag['wert'] === $wert) return $wert;                    // noch gültig
    }
    return '';
}

/**
 * Warenkorb und Kasse um unsere Angaben erweitern.
 */
/*
 * Angemeldet auf init, nicht auf woocommerce_blocks_loaded: der feuert
 * während plugins_loaded, und die functions.php eines Themes wird erst
 * danach geladen. Aus einem Theme heraus kommt man dort nie an. Auf init
 * ist es früh genug — REST-Anfragen laufen erst danach los.
 */
add_action('init', function () {
    if (!function_exists('woocommerce_store_api_register_endpoint_data')) return;

    woocommerce_store_api_register_endpoint_data([
        'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
        'namespace'       => 'sapelza',
        'data_callback'   => function () {
            return ['tage' => sz_liefertage(), 'gewaehlt' => sz_termin_gewaehlt()];
        },
        'schema_callback' => function () {
            return [
            'tage' => [
                'description' => 'Waehlbare Liefertage',
                'type'        => 'array',
                'readonly'    => true,
                'items'       => [
                    'type'       => 'object',
                    'properties' => [
                        'wert'  => ['type' => 'string'],
                        'tag'   => ['type' => 'string'],
                        'datum' => ['type' => 'string'],
                    ],
                ],
            ],
            'gewaehlt' => [
                'description' => 'Der gewählte Liefertag',
                'type'        => 'string',
                'readonly'    => true,
            ],
            ];
        },
        'schema_type'     => ARRAY_A,
    ]);

    woocommerce_store_api_register_update_callback([
        'namespace' => 'sapelza',
        'callback'  => function ($daten) {
            if (!function_exists('WC') || !WC()->session) return;
            $wunsch = isset($daten['tag']) ? sanitize_text_field((string) $daten['tag']) : '';
            $gueltig = wp_list_pluck(sz_liefertage(), 'wert');
            WC()->session->set('sz_wunschtermin', in_array($wunsch, $gueltig, true) ? $wunsch : '');
        },
    ]);
});

/**
 * Beim Abschluss an die Bestellung heften.
 */
add_action('woocommerce_store_api_checkout_update_order_from_request', function ($bestellung) {
    $wunsch = sz_termin_gewaehlt();

    /*
     * Der Browser sperrt die Schaltfläche bereits. Das hier ist der
     * Riegel dahinter: wer die Oberfläche umgeht, bekommt trotzdem
     * keine Bestellung ohne Liefertag.
     */
    if ($wunsch === '') {
        $klasse = '\\Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException';
        if (class_exists($klasse)) {
            throw new $klasse('sz_wunschtermin_fehlt', 'Bitte wählen Sie einen Liefertag.', 400);
        }
        return;
    }

    $bestellung->update_meta_data(SZ_TERMIN_SCHLUESSEL, $wunsch);
}, 10, 1);

/**
 * Und überall zeigen, wo jemand die Bestellung ansieht.
 */
add_action('woocommerce_admin_order_data_after_shipping_address', function ($bestellung) {
    $wunsch = $bestellung->get_meta(SZ_TERMIN_SCHLUESSEL);
    if (!$wunsch) return;
    echo '<p><strong>Wunschtermin:</strong> ' . esc_html(wp_date('l, j. F Y', strtotime($wunsch))) . '</p>';
});

add_filter('woocommerce_email_order_meta_fields', function ($felder, $nur_text, $bestellung) {
    $wunsch = $bestellung->get_meta(SZ_TERMIN_SCHLUESSEL);
    if ($wunsch) {
        $felder['sz_wunschtermin'] = [
            'label' => 'Wunschtermin',
            'value' => wp_date('l, j. F Y', strtotime($wunsch)),
        ];
    }
    return $felder;
}, 10, 3);

/**
 * Die Oberfläche in der Kasse.
 *
 * Ausgegeben im Fuß statt über wp_add_inline_script: der Handle
 * wc-blocks-checkout ist beim Einreihen nicht verlässlich schon
 * angemeldet, und wichtiger — blocks-checkout.js ist nur ein Ladegerüst.
 * Die Ausfuhren erscheinen erst, wenn das eigentliche Bündel nachgeladen
 * ist. Deshalb wartet das Skript, statt einmal zu prüfen und aufzugeben.
 */
add_action('wp_footer', function () {
    if (!function_exists('is_checkout') || !is_checkout()) return;
    ?>
<script id="sz-wunschtermin">
( function () {
    var versuche = 0;

    /* Der Einhängepunkt heißt je nach WooCommerce-Fassung anders. */
    var STELLEN = [ 'ExperimentalOrderMeta', 'ExperimentalOrderShippingPackages', 'ExperimentalDiscountsMeta' ];

    function api() {
        var wc = window.wc, wp = window.wp;
        if ( ! wc || ! wc.blocksCheckout || ! wp || ! wp.element || ! wp.plugins ) return null;
        if ( typeof wc.blocksCheckout.extensionCartUpdate !== 'function' ) return null;
        for ( var i = 0; i < STELLEN.length; i++ ) {
            if ( wc.blocksCheckout[ STELLEN[ i ] ] ) {
                return { Slot: wc.blocksCheckout[ STELLEN[ i ] ], name: STELLEN[ i ], senden: wc.blocksCheckout.extensionCartUpdate };
            }
        }
        return null;
    }

    function einhaengen( teile ) {
        var el = window.wp.element.createElement;

        function Wunschtermin( props ) {
            var daten = ( props.extensions || {} ).sapelza || {};
            var tage = daten.tage || [];
            var stand = window.wp.element.useState( daten.gewaehlt || '' );
            var gewaehlt = stand[ 0 ], setzen = stand[ 1 ];

            /* Ohne Tag keine Bestellung. Der Fehler hängt im Prüfspeicher
               der Kasse — die Schaltfläche bleibt gesperrt, solange er
               steht. Beim Verlassen wird er wieder entfernt, sonst
               blockiert er eine Kasse ohne unser Feld. */
            window.wp.element.useEffect( function () {
                var pruef = window.wp.data && window.wp.data.dispatch( 'wc/store/validation' );
                if ( ! pruef ) return;
                if ( gewaehlt ) {
                    pruef.clearValidationError( 'sz-wunschtermin' );
                } else {
                    pruef.setValidationErrors( {
                        'sz-wunschtermin': { message: 'Bitte wählen Sie einen Liefertag.', hidden: false }
                    } );
                }
                return function () { pruef.clearValidationError( 'sz-wunschtermin' ); };
            }, [ gewaehlt ] );

            if ( ! tage.length ) return null;

            return el( 'fieldset', { className: 'sz-wunschtermin' },
                el( 'legend', null, 'Wunschtermin' ),
                el( 'p', { className: 'sz-wunschtermin-lead' },
                    'Wählen Sie den Tag, an dem es Ihnen passt. Wir fahren ohnehin durchs Tal.' ),
                el( 'div', { className: 'sz-wunschtermin-tage' },
                    tage.map( function ( tag ) {
                        return el( 'button', {
                            key: tag.wert,
                            type: 'button',
                            className: 'sz-tag',
                            'aria-pressed': gewaehlt === tag.wert,
                            onClick: function () {
                                setzen( tag.wert );
                                teile.senden( { namespace: 'sapelza', data: { tag: tag.wert } } );
                            }
                        },
                            el( 'span', { className: 'sz-tag-wochentag' }, tag.tag ),
                            el( 'span', { className: 'sz-tag-datum' }, tag.datum )
                        );
                    } )
                )
            );
        }

        window.wp.plugins.registerPlugin( 'sapelza-wunschtermin', {
            render: function () { return el( teile.Slot, null, el( Wunschtermin, null ) ); },
            scope: 'woocommerce-checkout'
        } );

        window.console && console.info( 'Sapelza: Wunschtermin eingehängt über ' + teile.name );
    }

    function versuchen() {
        var teile = api();
        if ( teile ) { einhaengen( teile ); return; }

        /* Zehn Sekunden warten — so lange braucht kein Bündel. */
        if ( ++versuche > 200 ) {
            var da = ( window.wc && window.wc.blocksCheckout ) ? Object.keys( window.wc.blocksCheckout ).join( ', ' ) : 'wc.blocksCheckout fehlt';
            window.console && console.warn( 'Sapelza: kein Einhängepunkt gefunden. Vorhanden: ' + da );
            return;
        }
        setTimeout( versuchen, 50 );
    }

    versuchen();
} )();
</script>
    <?php
}, 20);
