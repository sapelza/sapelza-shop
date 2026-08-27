<?php
/**
 * Der Wunschtermin.
 *
 * Kein Countdown, kein Druck: der Betrieb wählt Tag und Zeitfenster selbst
 * — weil geschlossen ist, weil der Chefkoch frei hat oder weil dann am
 * wenigsten los ist. Wir richten uns danach.
 *
 * Die Auswahl steht im WARENKORB, nicht in der Kasse. Sie gehört zur
 * Bestellung, nicht zur Bezahlung — und in der Kasse müsste sie sich
 * zwischen Adressfeldern behaupten, während sie im Warenkorb eine eigene
 * Fläche bekommt. Die Kasse zeigt nur noch, was gewählt wurde.
 *
 * Bewusst ohne Block-Erweiterung: die bräuchte einen Bauschritt und bricht
 * bei jeder Änderung an WooCommerce Blocks. Ein Abschnitt unter dem
 * Warenkorb funktioniert mit der Block- wie mit der klassischen Fassung.
 */

if (!defined('ABSPATH')) exit;

const SZ_TERMIN_SCHLUESSEL  = '_sz_wunschtermin';
const SZ_FENSTER_SCHLUESSEL = '_sz_zeitfenster';

/**
 * Die Zeitfenster, in denen der Porter fährt.
 *
 * @return array<string,string> Schlüssel => Beschriftung
 */
function sz_zeitfenster(): array
{
    return apply_filters('sz_zeitfenster', [
        '08-10' => __('08–10 Uhr', 'sapelza-shop'),
        '12-14' => __('12–14 Uhr', 'sapelza-shop'),
        '18-20' => __('18–20 Uhr', 'sapelza-shop'),
    ]);
}

/**
 * Die nächsten Liefertage, ab morgen.
 *
 * Geschlossene Tage werden MITGELIEFERT und gekennzeichnet, nicht
 * weggelassen. Wer den Montag sucht und gar nicht findet, hält es für
 * einen Fehler; wer ihn ausgegraut sieht, versteht es sofort.
 *
 * @return array<int,array{wert:string,tag:string,datum:string,zu:bool,frei:int}>
 */
function sz_liefertage(): array
{
    $gesperrt = apply_filters('sz_schliesstage', [], get_current_user_id());
    $fenster  = count(sz_zeitfenster());
    $tage     = [];

    for ($i = 1; $i <= 12; $i++) {
        $zeit = strtotime("+{$i} day");
        $wert = wp_date('Y-m-d', $zeit);
        $wtag = (int) wp_date('N', $zeit);

        /*
         * Sonntag ist zu. Ob auch der Montag zu ist, entscheidet der
         * Betrieb — deshalb ein Filter und keine feste Annahme im Code.
         */
        $zu = ($wtag === 7)
            || in_array($wert, $gesperrt, true)
            || (bool) apply_filters('sz_tag_geschlossen', false, $wert, $wtag);

        $tage[] = [
            'wert'  => $wert,
            'tag'   => wp_date('D', $zeit),
            'datum' => wp_date('j.n.', $zeit),
            'zu'    => $zu,
            'frei'  => $zu ? 0 : (int) apply_filters('sz_freie_fenster', $fenster, $wert),
        ];
    }

    return $tage;
}

/** Nur die Werte der Tage, an denen tatsächlich geliefert wird. */
function sz_liefertage_offen(): array
{
    return array_column(array_filter(sz_liefertage(), fn($t) => !$t['zu']), 'wert');
}

/**
 * Der gewählte Tag, oder ''.
 *
 * Bei jedem Abruf geprüft: ein Tag, der inzwischen vergangen oder gesperrt
 * ist, gilt nicht mehr.
 */
function sz_termin_gewaehlt(): string
{
    if (!function_exists('WC') || !WC()->session) return '';
    $wert = (string) WC()->session->get('sz_wunschtermin', '');
    return in_array($wert, sz_liefertage_offen(), true) ? $wert : '';
}

/** Das gewählte Zeitfenster, oder ''. */
function sz_fenster_gewaehlt(): string
{
    if (!function_exists('WC') || !WC()->session) return '';
    $wert = (string) WC()->session->get('sz_zeitfenster', '');
    return array_key_exists($wert, sz_zeitfenster()) ? $wert : '';
}

/** Tag und Fenster als lesbarer Satz, oder ''. */
function sz_termin_text(): string
{
    $tag = sz_termin_gewaehlt();
    if ($tag === '') return '';

    $text = wp_date('l, j.n.', strtotime($tag));

    $fenster = sz_fenster_gewaehlt();
    if ($fenster !== '') {
        $namen = sz_zeitfenster();
        $text .= ' · ' . $namen[$fenster];
    }

    return $text;
}

/* ===================================================================
   Auswahl entgegennehmen
   =================================================================== */

/*
 * Bewusst über admin-ajax und nicht über die Store API: das braucht keinen
 * Bauschritt, funktioniert ohne die Block-Kasse und bleibt lesbar.
 */
add_action('wp_ajax_sz_termin', 'sz_termin_speichern');
add_action('wp_ajax_nopriv_sz_termin', 'sz_termin_speichern');

function sz_termin_speichern(): void
{
    check_ajax_referer('sz_termin');

    if (!function_exists('WC') || !WC()->session) {
        wp_send_json_error(['grund' => 'keine-sitzung'], 400);
    }

    $tag = isset($_POST['tag']) ? sanitize_text_field(wp_unslash($_POST['tag'])) : '';
    $fen = isset($_POST['fenster']) ? sanitize_text_field(wp_unslash($_POST['fenster'])) : '';

    if ($tag !== '' && !in_array($tag, sz_liefertage_offen(), true)) {
        wp_send_json_error(['grund' => 'tag-ungueltig'], 400);
    }
    if ($fen !== '' && !array_key_exists($fen, sz_zeitfenster())) {
        wp_send_json_error(['grund' => 'fenster-ungueltig'], 400);
    }

    WC()->session->set('sz_wunschtermin', $tag);
    WC()->session->set('sz_zeitfenster', $fen);

    wp_send_json_success(['text' => sz_termin_text()]);
}

/* ===================================================================
   Darstellung im Warenkorb
   =================================================================== */

/*
 * An den Seiteninhalt angehängt, nicht an einen WooCommerce-Haken: die
 * Block-Fassung des Warenkorbs bietet keinen, und so wirkt es mit beiden
 * Fassungen gleich.
 */
add_filter('the_content', function ($inhalt) {
    if (!function_exists('is_cart') || !is_cart()) return $inhalt;
    if (!in_the_loop() || !is_main_query()) return $inhalt;
    if (function_exists('WC') && WC()->cart && WC()->cart->is_empty()) return $inhalt;

    return $inhalt . sz_termin_abschnitt();
}, 20);

function sz_termin_abschnitt(): string
{
    $tage    = sz_liefertage();
    $fenster = sz_zeitfenster();
    $tag_ist = sz_termin_gewaehlt();
    $fen_ist = sz_fenster_gewaehlt();

    ob_start();
    ?>
    <section class="sz-termin" id="sz-termin" data-sz-termin
             data-nonce="<?php echo esc_attr(wp_create_nonce('sz_termin')); ?>"
             data-ziel="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">

        <div class="sz-termin__kopf">
            <h2 class="sz-termin__titel"><?php echo esc_html__('Wann soll geliefert werden?', 'sapelza-shop'); ?></h2>
            <span class="sz-termin__merk mono"><?php echo esc_html__('Sie bestimmen den Tag', 'sapelza-shop'); ?></span>
        </div>

        <p class="sz-termin__lead">
            <?php echo esc_html__('Wählen Sie den Tag, an dem es Ihnen passt — wenn der Betrieb offen ist, wenn jemand annehmen kann, wenn am wenigsten los ist. Wir richten uns danach.', 'sapelza-shop'); ?>
        </p>

        <div class="sz-termin__tage" role="group" aria-label="<?php echo esc_attr__('Liefertag', 'sapelza-shop'); ?>">
            <?php foreach ($tage as $t) : ?>
                <button type="button" class="sz-tag<?php echo $t['zu'] ? ' ist-zu' : ''; ?>"
                        data-sz-tag="<?php echo esc_attr($t['wert']); ?>"
                        <?php echo $t['zu'] ? 'disabled' : ''; ?>
                        aria-pressed="<?php echo $t['wert'] === $tag_ist ? 'true' : 'false'; ?>">
                    <span class="sz-tag__wtag mono"><?php echo esc_html($t['tag']); ?></span>
                    <span class="sz-tag__datum mono"><?php echo esc_html($t['datum']); ?></span>
                    <span class="sz-tag__frei">
                        <?php
                        if ($t['zu']) {
                            echo esc_html__('geschlossen', 'sapelza-shop');
                        } else {
                            printf(
                                /* translators: %d ist die Zahl der noch freien Zeitfenster. */
                                esc_html(_n('%d Fenster frei', '%d Fenster frei', (int) $t['frei'], 'sapelza-shop')),
                                (int) $t['frei']
                            );
                        }
                        ?>
                    </span>
                </button>
            <?php endforeach; ?>
        </div>

        <p class="sz-termin__label mono"><?php echo esc_html__('Zeitfenster', 'sapelza-shop'); ?></p>
        <div class="sz-termin__fenster" role="group" aria-label="<?php echo esc_attr__('Zeitfenster', 'sapelza-shop'); ?>">
            <?php foreach ($fenster as $schluessel => $name) : ?>
                <button type="button" class="sz-fenster"
                        data-sz-fenster="<?php echo esc_attr($schluessel); ?>"
                        aria-pressed="<?php echo $schluessel === $fen_ist ? 'true' : 'false'; ?>">
                    <?php echo esc_html($name); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <p class="sz-termin__ergebnis" data-sz-ergebnis aria-live="polite">
            <?php
            $text = sz_termin_text();
            echo $text !== ''
                ? esc_html($text)
                : esc_html__('Noch kein Termin gewählt.', 'sapelza-shop');
            ?>
        </p>

        <p class="sz-termin__fein">
            <?php echo esc_html__('Ihre Schließtage sind ausgegraut.', 'sapelza-shop'); ?>
        </p>
    </section>
    <?php
    return (string) ob_get_clean();
}

/*
 * Das Skript für die Auswahl. Klein genug, um es hier zu halten — eine
 * eigene Datei für vierzig Zeilen wäre eine Anfrage mehr für nichts.
 */
add_action('wp_footer', function () {
    if (!function_exists('is_cart') || !is_cart()) return;
    ?>
    <script id="sz-termin-js">
    ( function () {
        var wurzel = document.querySelector( '[data-sz-termin]' );
        if ( ! wurzel ) return;

        var ergebnis = wurzel.querySelector( '[data-sz-ergebnis]' );
        var leer = <?php echo wp_json_encode(__('Noch kein Termin gewählt.', 'sapelza-shop')); ?>;

        function gewaehlt( auswahl, feld ) {
            var el = wurzel.querySelector( auswahl + '[aria-pressed="true"]' );
            return el ? el.getAttribute( feld ) : '';
        }

        function senden() {
            var daten = new URLSearchParams();
            daten.set( 'action', 'sz_termin' );
            daten.set( '_wpnonce', wurzel.dataset.nonce );
            daten.set( 'tag', gewaehlt( '[data-sz-tag]', 'data-sz-tag' ) );
            daten.set( 'fenster', gewaehlt( '[data-sz-fenster]', 'data-sz-fenster' ) );

            fetch( wurzel.dataset.ziel, { method: 'POST', body: daten, credentials: 'same-origin' } )
                .then( function ( a ) { return a.json(); } )
                .then( function ( a ) {
                    if ( a && a.success && ergebnis ) ergebnis.textContent = a.data.text || leer;
                } )
                .catch( function () {
                    /* Die Auswahl steht sichtbar. Ein Netzfehler darf sie
                       nicht zuruecksetzen — beim Absenden greift ohnehin
                       der Riegel in der Kasse. */
                } );
        }

        wurzel.addEventListener( 'click', function ( e ) {
            var knopf = e.target.closest( '[data-sz-tag], [data-sz-fenster]' );
            if ( ! knopf || knopf.disabled ) return;

            var art = knopf.hasAttribute( 'data-sz-tag' ) ? '[data-sz-tag]' : '[data-sz-fenster]';
            var alle = wurzel.querySelectorAll( art );
            for ( var i = 0; i < alle.length; i++ ) {
                alle[ i ].setAttribute( 'aria-pressed', String( alle[ i ] === knopf ) );
            }

            senden();
        } );
    } )();
    </script>
    <?php
}, 30);

/* ===================================================================
   Kasse: nur noch zeigen, was gewählt wurde
   =================================================================== */

/**
 * Das Gatter vor der Kasse.
 *
 * Ohne Liefertag geht es gar nicht erst zur Kasse, sondern zurueck in den
 * Warenkorb — dorthin, wo die Auswahl steht.
 *
 * Warum template_redirect und kein Kassen-Haken: die gaengigen Haken der
 * Kasse gehoeren zur KLASSISCHEN Fassung und laufen auf der Block-Kasse
 * nie. template_redirect greift bei beiden.
 */
add_action('template_redirect', function () {
    if (!function_exists('is_checkout') || !is_checkout()) return;
    if (function_exists('is_order_received_page') && is_order_received_page()) return;
    if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-pay')) return;
    if (!WC()->cart || WC()->cart->is_empty()) return;
    if (sz_termin_gewaehlt() !== '' && sz_fenster_gewaehlt() !== '') return;

    /*
     * Beides wird verlangt, und nichts wird vorausgewaehlt. Ein
     * Lieferfenster, das jemand anderes bestimmt hat, ist genau das, was
     * beim Liefer-Countdown verworfen wurde: es nimmt dem Betrieb die
     * Wahl und sieht nur so aus, als waere sie getroffen worden.
     */
    wc_add_notice(
        sz_termin_gewaehlt() === ''
            ? __('Bitte waehlen Sie zuerst einen Liefertag — wir richten die Tour danach ein.', 'sapelza-shop')
            : __('Bitte waehlen Sie noch ein Zeitfenster.', 'sapelza-shop'),
        'notice'
    );

    wp_safe_redirect(wc_get_cart_url() . '#sz-termin');
    exit;
}, 20);

/**
 * Der Liefertermin in der Kasse.
 *
 * Ueber den Seiteninhalt, nicht ueber einen WooCommerce-Haken: die
 * gaengigen Haken der Kasse (woocommerce_review_order_before_payment und
 * Verwandte) gehoeren zur KLASSISCHEN Fassung und laufen auf der
 * Block-Kasse nie. Genau daran ist die erste Fassung gescheitert.
 *
 * Vorangestellt statt angehaengt: der Termin soll oben stehen, bevor
 * jemand Adresse und Zahlung ausfuellt — nicht darunter, wo er erst nach
 * dem Absenden auffiele.
 */
add_filter('the_content', function ($inhalt) {
    if (!function_exists('is_checkout') || !is_checkout()) return $inhalt;
    if (function_exists('is_order_received_page') && is_order_received_page()) return $inhalt;
    if (!in_the_loop() || !is_main_query()) return $inhalt;

    $text = sz_termin_text();
    if ($text === '') return $inhalt;

    ob_start();
    ?>
    <div class="sz-termin__kasse">
        <span class="sz-termin__label mono"><?php echo esc_html__('Liefertermin', 'sapelza-shop'); ?></span>
        <strong><?php echo esc_html($text); ?></strong>
        <a href="<?php echo esc_url(wc_get_cart_url() . '#sz-termin'); ?>"><?php echo esc_html__('ändern', 'sapelza-shop'); ?></a>
    </div>
    <?php
    return (string) ob_get_clean() . $inhalt;
}, 20);

/* ===================================================================
   An die Bestellung heften
   =================================================================== */

/**
 * Der Riegel.
 *
 * Ohne Liefertag keine Bestellung — auf beiden Wegen. Fehlt die
 * WooCommerce-Ausnahmeklasse, wird eine gewöhnliche geworfen: der Riegel
 * muss im Zweifel SCHLIESSEN, nicht aufgehen. Genau das war der Fehler in
 * der ersten Fassung, die bei fehlender Klasse einfach zurückkehrte.
 */
function sz_termin_pruefen(): void
{
    if (sz_termin_gewaehlt() !== '' && sz_fenster_gewaehlt() !== '') return;

    $meldung = __('Bitte waehlen Sie im Warenkorb Liefertag und Zeitfenster.', 'sapelza-shop');

    $klasse = '\\Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException';
    if (class_exists($klasse)) {
        throw new $klasse('sz_wunschtermin_fehlt', $meldung, 400);
    }

    throw new Exception(esc_html($meldung));
}

/* Block-Kasse */
add_action('woocommerce_store_api_checkout_update_order_from_request', function ($bestellung) {
    sz_termin_pruefen();
    sz_termin_anheften($bestellung);
}, 10, 1);

/* Klassische Kasse */
add_action('woocommerce_checkout_process', function () {
    if (sz_termin_gewaehlt() !== '' && sz_fenster_gewaehlt() !== '') return;
    wc_add_notice(__('Bitte waehlen Sie im Warenkorb Liefertag und Zeitfenster.', 'sapelza-shop'), 'error');
});

add_action('woocommerce_checkout_create_order', function ($bestellung) {
    sz_termin_anheften($bestellung);
}, 10, 1);

function sz_termin_anheften($bestellung): void
{
    if (!$bestellung instanceof WC_Order) return;

    $tag = sz_termin_gewaehlt();
    if ($tag === '') return;

    $bestellung->update_meta_data(SZ_TERMIN_SCHLUESSEL, $tag);

    $fenster = sz_fenster_gewaehlt();
    if ($fenster !== '') $bestellung->update_meta_data(SZ_FENSTER_SCHLUESSEL, $fenster);
}

/**
 * Und überall zeigen, wo jemand die Bestellung ansieht.
 */
function sz_termin_der_bestellung(WC_Order $bestellung): string
{
    $tag = (string) $bestellung->get_meta(SZ_TERMIN_SCHLUESSEL);
    if ($tag === '') return '';

    $text = wp_date('l, j.n.Y', strtotime($tag));

    $fenster = (string) $bestellung->get_meta(SZ_FENSTER_SCHLUESSEL);
    $namen   = sz_zeitfenster();
    if ($fenster !== '' && isset($namen[$fenster])) $text .= ' · ' . $namen[$fenster];

    return $text;
}

add_action('woocommerce_admin_order_data_after_shipping_address', function ($bestellung) {
    if (!$bestellung instanceof WC_Order) return;
    $text = sz_termin_der_bestellung($bestellung);
    if ($text === '') return;

    echo '<p><strong>' . esc_html__('Wunschtermin', 'sapelza-shop') . ':</strong><br>'
       . esc_html($text) . '</p>';
});

add_filter('woocommerce_email_order_meta_fields', function ($felder, $nur_text, $bestellung) {
    if (!$bestellung instanceof WC_Order) return $felder;
    $text = sz_termin_der_bestellung($bestellung);
    if ($text === '') return $felder;

    $felder['sz_wunschtermin'] = [
        'label' => __('Wunschtermin', 'sapelza-shop'),
        'value' => $text,
    ];
    return $felder;
}, 10, 3);
