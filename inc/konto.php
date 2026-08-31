<?php

/**
 * Das B2B-Konto.
 *
 * Die Übersicht nach dem Entwurf: Kennzahlen, letzte Bestellungen, die
 * Ausgaben über zwölf Monate im Vergleich zum Vorjahr, was bald ansteht,
 * die Belege und die Zugänge im Betrieb.
 *
 * Grundsatz: Es steht nur da, was der Shop wirklich weiß. Der Entwurf
 * zeigt auch einen Rabattsatz und Kostenstellen — beides hat hier keine
 * verlässliche Quelle, also fehlt es lieber, als dass eine Zahl auf
 * einer Rechnungsseite erfunden wird. Was an Daten fehlt, blendet den
 * ganzen Block aus statt ihn mit Strichen zu füllen.
 *
 * @package sapelza-shop
 */

defined('ABSPATH') || exit;

/**
 * Alle Zahlen des Kontos in einem Durchgang.
 *
 * Zwölf Monate Bestellungen zweimal durchzurechnen kostet spürbar, und
 * die Zahlen ändern sich nicht im Minutentakt — deshalb eine Viertelstunde
 * im Zwischenspeicher.
 *
 * @return array{offen:float,jahr:int,monate:array,summe:float,vorsumme:float,kategorien:array}
 */
function sz_konto_kennzahlen(): array
{
    $betrieb = function_exists('sz_betrieb_id') ? sz_betrieb_id() : get_current_user_id();
    if (!$betrieb || !function_exists('wc_get_orders')) {
        return ['offen' => 0.0, 'jahr' => 0, 'monate' => [], 'summe' => 0.0, 'vorsumme' => 0.0, 'kategorien' => []];
    }

    $schluessel = 'sz_konto_' . $betrieb;
    $fertig = get_transient($schluessel);
    if (is_array($fertig)) return $fertig;

    $jetzt = current_time('timestamp');

    /* Zwölf volle Monate zurück, plus dieselben zwölf ein Jahr davor. */
    $von = strtotime('first day of -11 months', $jetzt);
    $von = strtotime(date('Y-m-01 00:00:00', $von));

    $bestellungen = wc_get_orders([
        'customer_id' => $betrieb,
        'limit'       => -1,
        'status'      => ['wc-processing', 'wc-completed', 'wc-on-hold', 'wc-pending'],
        'date_created' => '>' . date('Y-m-d', strtotime('-24 months', $von)),
    ]);

    $monate     = [];
    $kategorien = [];
    $offen      = 0.0;
    $jahr       = 0;
    $dieses     = (int) date('Y', $jetzt);

    for ($i = 11; $i >= 0; $i--) {
        $z = strtotime('-' . $i . ' months', $jetzt);
        $monate[date('Y-m', $z)] = ['stempel' => $z, 'jetzt' => 0.0, 'vorher' => 0.0];
    }

    foreach ($bestellungen as $b) {
        $erstellt = $b->get_date_created();
        if (!$erstellt) continue;

        $stempel = $erstellt->getTimestamp();
        $summe   = (float) $b->get_total();
        $status  = $b->get_status();

        if (in_array($status, ['on-hold', 'pending'], true)) $offen += $summe;
        if ((int) date('Y', $stempel) === $dieses) $jahr++;

        $marke = date('Y-m', $stempel);
        if (isset($monate[$marke])) {
            $monate[$marke]['jetzt'] += $summe;
        } else {
            /* Derselbe Monat ein Jahr später — das ist der Vorjahreswert. */
            $spaeter = date('Y-m', strtotime('+1 year', $stempel));
            if (isset($monate[$spaeter])) $monate[$spaeter]['vorher'] += $summe;
        }

        /* Nach Kategorie, nur für die letzten zwölf Monate. */
        if ($stempel < $von) continue;

        foreach ($b->get_items() as $posten) {
            $produkt = $posten->get_product();
            if (!$produkt) continue;

            $eltern = $produkt->get_parent_id() ?: $produkt->get_id();
            $begriffe = get_the_terms($eltern, 'product_cat');
            if (!$begriffe || is_wp_error($begriffe)) continue;

            $name = $begriffe[0]->name;
            if (!isset($kategorien[$name])) $kategorien[$name] = 0.0;
            $kategorien[$name] += (float) $posten->get_total();
        }
    }

    arsort($kategorien);

    $zahlen = [
        'offen'      => $offen,
        'jahr'       => $jahr,
        'monate'     => $monate,
        'summe'      => array_sum(array_column($monate, 'jetzt')),
        'vorsumme'   => array_sum(array_column($monate, 'vorher')),
        'kategorien' => array_slice($kategorien, 0, 6, true),
    ];

    set_transient($schluessel, $zahlen, 15 * MINUTE_IN_SECONDS);

    return $zahlen;
}

/** Nach jeder Bestellung stimmen die Zahlen nicht mehr. */
add_action('woocommerce_checkout_order_processed', function ($id) {
    $b = wc_get_order($id);
    if ($b) delete_transient('sz_konto_' . $b->get_customer_id());
});

/**
 * Die Zugänge des Betriebs.
 *
 * @return WP_User[]
 */
function sz_konto_zugaenge(): array
{
    $betrieb = function_exists('sz_betrieb_id') ? sz_betrieb_id() : 0;
    if (!$betrieb) return [];

    $gefunden = [];
    foreach (['b2bking_account_parent', 'b2bking_parent_account'] as $schluessel) {
        $leute = get_users(['meta_key' => $schluessel, 'meta_value' => $betrieb, 'number' => 25]);
        foreach ($leute as $l) $gefunden[$l->ID] = $l;
    }

    return array_values($gefunden);
}

/* ===================================================================
   Der Kontobereich
   =================================================================== */

/* Favoriten als eigener Punkt. */
add_action('init', function () {
    add_rewrite_endpoint('favoriten', EP_ROOT | EP_PAGES);

    /*
     * Ein neuer Endpunkt greift erst, wenn WordPress seine Adressregeln
     * neu schreibt. Sonst zeigt /mein-konto/favoriten/ einen 404, und
     * niemand kaeme darauf, dass die Ursache in den Permalinks liegt.
     * Einmal, dann nie wieder — die Option merkt sich die Fassung.
     */
    if (get_option('sz_endpunkte') !== SZ_NAMEN_FASSUNG) {
        flush_rewrite_rules(false);
        update_option('sz_endpunkte', SZ_NAMEN_FASSUNG);
    }
});

add_filter('woocommerce_account_menu_items', function ($punkte) {
    $neu = [];

    foreach ($punkte as $schluessel => $beschriftung) {
        $neu[$schluessel] = $beschriftung;

        /* Direkt nach der Übersicht kommt, was dem Betrieb gehört. */
        if ($schluessel === 'dashboard') {
            if (function_exists('sz_erfassung_url')) {
                $neu['sz-meine-artikel'] = __('Meine Artikel', 'sapelza-shop');
            }
            $neu['favoriten'] = __('Favoriten', 'sapelza-shop');
        }
    }

    return $neu;
});

add_filter('woocommerce_get_endpoint_url', function ($url, $endpunkt) {
    if ($endpunkt === 'sz-meine-artikel') {
        $seite = get_page_by_path('meine-artikel');
        if ($seite) return get_permalink($seite);
    }

    return $url;
}, 10, 2);

add_action('woocommerce_account_favoriten_endpoint', function () {
    $liste = function_exists('sz_favoriten') ? sz_favoriten() : [];

    if (!$liste) {
        echo '<p class="sz-hinweis">'
           . esc_html__('Noch nichts gemerkt. Das Herz an einer Artikelkachel legt ihn hier ab — für den ganzen Betrieb, nicht nur für Sie.', 'sapelza-shop')
           . '</p>';
        return;
    }

    echo '<div class="sz-reihen">';

    foreach ($liste as $id) {
        $produkt = wc_get_product($id);
        if (!$produkt) continue;

        printf(
            '<div class="sz-reihe sz-reihe--favorit">'
            . '<a class="sz-reihe__name" href="%1$s">%2$s</a>'
            . '<span class="sz-reihe__preis price">%3$s</span>'
            . '%4$s'
            . '<a class="sz-reihe__weg" href="%5$s">%6$s</a>'
            . '</div>',
            esc_url($produkt->get_permalink()),
            esc_html(function_exists('sz_anzeigename') ? sz_anzeigename($produkt->get_id(), $produkt->get_name()) : $produkt->get_name()),
            wp_kses_post($produkt->get_price_html()),
            wp_kses_post(sz_favorit_knopf($produkt->get_id())),
            esc_url($produkt->get_permalink()),
            esc_html__('Ansehen', 'sapelza-shop')
        );
    }

    echo '</div>';
});

/* ===================================================================
   Die Übersicht
   =================================================================== */

remove_action('woocommerce_account_dashboard', 'woocommerce_account_content');

add_action('woocommerce_account_dashboard', function () {
    $ich     = wp_get_current_user();
    $betrieb = function_exists('sz_betrieb_id') ? sz_betrieb_id() : $ich->ID;
    $chef    = ($betrieb === $ich->ID) ? $ich : get_userdata($betrieb);

    $name = trim((string) get_user_meta($betrieb, 'billing_company', true));
    if ($name === '') $name = $chef ? $chef->display_name : $ich->display_name;

    /* Die Preisgruppe steht bei B2BKing als Beitrag hinter einer ID. */
    $gruppe = '';
    $gid = (int) get_user_meta($betrieb, 'b2bking_customergroup', true);
    if ($gid > 0) {
        $g = get_post($gid);
        if ($g) $gruppe = $g->post_title;
    }

    $zahlen = sz_konto_kennzahlen();
    $favoriten = function_exists('sz_favoriten') ? sz_favoriten() : [];

    ?>
    <div class="sz-konto">

        <p class="kicker">
            <span class="kicker__punkt" aria-hidden="true"></span>
            <?php echo esc_html__('Guten Tag', 'sapelza-shop'); ?>
        </p>

        <h1 class="sz-konto__name"><?php echo esc_html($name); ?></h1>

        <?php if ($gruppe !== '') : ?>
            <p class="sz-konto__lage">
                <?php printf(esc_html__('Preisgruppe %s', 'sapelza-shop'), esc_html($gruppe)); ?>
            </p>
        <?php endif; ?>

        <div class="sz-konto__zahlen">
            <div class="sz-kennzahl">
                <span class="sz-kicker-klein"><?php echo esc_html__('Offen', 'sapelza-shop'); ?></span>
                <p class="sz-kennzahl__wert price"><?php echo wp_kses_post(wc_price($zahlen['offen'])); ?></p>
            </div>
            <div class="sz-kennzahl">
                <span class="sz-kicker-klein">
                    <?php printf(esc_html__('Bestellungen %s', 'sapelza-shop'), esc_html(date_i18n('Y'))); ?>
                </span>
                <p class="sz-kennzahl__wert price"><?php echo esc_html(number_format_i18n($zahlen['jahr'])); ?></p>
            </div>
            <div class="sz-kennzahl">
                <span class="sz-kicker-klein"><?php echo esc_html__('Gemerkte Artikel', 'sapelza-shop'); ?></span>
                <p class="sz-kennzahl__wert price" data-sz-favoritenzahl><?php echo esc_html(number_format_i18n(count($favoriten))); ?></p>
            </div>
        </div>

        <?php
        /* --- Letzte Bestellungen ------------------------------------ */
        $letzte = wc_get_orders(['customer_id' => $betrieb, 'limit' => 4, 'orderby' => 'date', 'order' => 'DESC']);

        if ($letzte) : ?>
            <div class="sz-konto__kopfzeile">
                <h2 class="sz-konto__titel"><?php echo esc_html__('Letzte Bestellungen', 'sapelza-shop'); ?></h2>
                <a class="sz-konto__weiter" href="<?php echo esc_url(wc_get_endpoint_url('orders')); ?>">
                    <?php echo esc_html__('Alle Bestellungen →', 'sapelza-shop'); ?>
                </a>
            </div>

            <div class="sz-reihen">
                <?php foreach ($letzte as $b) : ?>
                    <div class="sz-reihe">
                        <span class="sz-reihe__name"><?php echo esc_html('#' . $b->get_order_number()); ?></span>
                        <span class="sz-reihe__mono mono"><?php echo esc_html(wc_price($b->get_total(), ['decimals' => 2])); ?></span>
                        <span class="sz-reihe__mono mono">
                            <?php echo esc_html($b->get_date_created() ? $b->get_date_created()->date_i18n('d.m.') : ''); ?>
                            · <?php echo esc_html(wc_get_order_status_name($b->get_status())); ?>
                        </span>
                        <a class="sz-reihe__weg" href="<?php echo esc_url($b->get_view_order_url()); ?>">
                            <?php echo esc_html__('Ansehen', 'sapelza-shop'); ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php
        /* --- Die Ausgaben ------------------------------------------- */
        $hoechst = 0.0;
        foreach ($zahlen['monate'] as $m) $hoechst = max($hoechst, $m['jetzt'], $m['vorher']);

        if ($hoechst > 0) :
            $wandel = $zahlen['vorsumme'] > 0
                ? (($zahlen['summe'] - $zahlen['vorsumme']) / $zahlen['vorsumme']) * 100
                : null;
            ?>
            <div class="hairline sz-konto__trenner"></div>

            <div class="sz-konto__kopfzeile">
                <h2 class="sz-konto__titel"><?php echo esc_html__('Ihre Ausgaben', 'sapelza-shop'); ?></h2>
                <span class="sz-konto__spanne mono">
                    <?php
                    $erster = reset($zahlen['monate']);
                    $letzter = end($zahlen['monate']);
                    echo esc_html(date_i18n('M Y', $erster['stempel']) . ' – ' . date_i18n('M Y', $letzter['stempel']));
                    ?>
                </span>
            </div>

            <p class="sz-konto__summe">
                <span class="price"><?php echo wp_kses_post(wc_price($zahlen['summe'])); ?></span>
                <?php if ($zahlen['vorsumme'] > 0) : ?>
                    <span class="sz-konto__vorjahr">
                        <?php printf(esc_html__('Vorjahr %s', 'sapelza-shop'), wp_kses_post(wc_price($zahlen['vorsumme']))); ?>
                        <?php if ($wandel !== null) : ?>
                            · <span class="sz-konto__wandel"><?php
                                echo esc_html(($wandel >= 0 ? '+' : '−') . number_format_i18n(abs($wandel), 1) . ' %');
                            ?></span>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </p>

            <div class="sz-saeulen">
                <?php foreach ($zahlen['monate'] as $m) : ?>
                    <div class="sz-saeule">
                        <div class="sz-saeule__paar">
                            <span class="sz-saeule__vorher"
                                  style="height: <?php echo esc_attr(round($m['vorher'] / $hoechst * 100, 2)); ?>%"
                                  title="<?php printf(esc_attr__('Vorjahr: %s', 'sapelza-shop'), esc_attr(wp_strip_all_tags(wc_price($m['vorher'])))); ?>"></span>
                            <span class="sz-saeule__jetzt"
                                  style="height: <?php echo esc_attr(round($m['jetzt'] / $hoechst * 100, 2)); ?>%"
                                  title="<?php echo esc_attr(date_i18n('M Y', $m['stempel']) . ': ' . wp_strip_all_tags(wc_price($m['jetzt']))); ?>"></span>
                        </div>
                        <span class="sz-saeule__marke mono"><?php echo esc_html(date_i18n('M', $m['stempel'])); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($zahlen['kategorien']) :
                $groesste = max($zahlen['kategorien']);
                $gesamt = array_sum($zahlen['kategorien']); ?>
                <div class="sz-konto__anteile">
                    <span class="sz-kicker-klein"><?php echo esc_html__('Nach Kategorie', 'sapelza-shop'); ?></span>
                    <div class="sz-anteile">
                        <?php foreach ($zahlen['kategorien'] as $name => $wert) : ?>
                            <div class="sz-anteil">
                                <div class="sz-anteil__zeile">
                                    <span><?php echo esc_html($name); ?></span>
                                    <span class="price">
                                        <?php echo wp_kses_post(wc_price($wert)); ?>
                                        <span class="sz-anteil__prozent mono"><?php
                                            echo esc_html(round($wert / max($gesamt, 0.01) * 100) . ' %');
                                        ?></span>
                                    </span>
                                </div>
                                <span class="sz-anteil__balken">
                                    <span style="width: <?php echo esc_attr(round($wert / $groesste * 100, 2)); ?>%"></span>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php
        /* --- Fällt bald an ------------------------------------------ */
        $rhythmus = function_exists('sz_bezogene_artikel') ? sz_bezogene_artikel() : [];
        $faellig = [];

        foreach ($rhythmus as $eintrag) {
            if (empty($eintrag['rhythmus']) || empty($eintrag['zuletzt'])) continue;

            $tage = (int) floor((time() - (int) $eintrag['zuletzt']) / DAY_IN_SECONDS);
            if ($tage < (int) $eintrag['rhythmus'] - 4) continue;

            $produkt = wc_get_product($eintrag['id']);
            if (!$produkt) continue;

            $faellig[] = ['produkt' => $produkt, 'tage' => $tage, 'rhythmus' => (int) $eintrag['rhythmus']];
            if (count($faellig) >= 4) break;
        }

        if ($faellig) : ?>
            <div class="hairline sz-konto__trenner"></div>
            <h2 class="sz-konto__titel sz-konto__titel--klein"><?php echo esc_html__('Fällt vermutlich bald an', 'sapelza-shop'); ?></h2>

            <div class="sz-reihen">
                <?php foreach ($faellig as $f) : ?>
                    <div class="sz-reihe">
                        <a class="sz-reihe__name" href="<?php echo esc_url($f['produkt']->get_permalink()); ?>">
                            <?php echo esc_html(function_exists('sz_anzeigename') ? sz_anzeigename($f['produkt']->get_id(), $f['produkt']->get_name()) : $f['produkt']->get_name()); ?>
                        </a>
                        <span class="sz-reihe__mono mono">
                            <?php printf(esc_html__('alle %d Tage', 'sapelza-shop'), (int) $f['rhythmus']); ?>
                        </span>
                        <span class="sz-reihe__mono mono">
                            <?php printf(
                                esc_html(_n('letzte Lieferung vor %d Tag', 'letzte Lieferung vor %d Tagen', $f['tage'], 'sapelza-shop')),
                                (int) $f['tage']
                            ); ?>
                        </span>
                        <a class="sz-reihe__weg" href="<?php echo esc_url($f['produkt']->get_permalink()); ?>">
                            <?php echo esc_html__('Nachbestellen →', 'sapelza-shop'); ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="sz-konto__fein">
                <?php echo esc_html__('Steht nur hier im Konto — wir schicken deswegen keine E-Mails.', 'sapelza-shop'); ?>
            </p>
        <?php endif; ?>

        <?php
        /* --- Zugänge ------------------------------------------------ */
        $zugaenge = sz_konto_zugaenge();

        if ($zugaenge) : ?>
            <div class="hairline sz-konto__trenner"></div>
            <h2 class="sz-konto__titel"><?php echo esc_html__('Zugänge im Betrieb', 'sapelza-shop'); ?></h2>

            <p class="sz-konto__lead">
                <?php echo esc_html__('Jede Person im Betrieb hat einen eigenen Zugang mit eigener E-Mail und eigenem Passwort — kein geteiltes Konto. Bestellungen laufen über dasselbe Betriebskonto und bleiben dem Zugang zugeordnet, von dem sie kamen.', 'sapelza-shop'); ?>
            </p>

            <div class="sz-reihen">
                <?php foreach ($zugaenge as $z) : ?>
                    <div class="sz-reihe">
                        <span class="sz-reihe__name">
                            <?php echo esc_html($z->display_name); ?>
                            <span class="sz-reihe__mono mono"><?php echo esc_html($z->user_email); ?></span>
                        </span>
                        <span class="sz-reihe__mono mono"><?php echo esc_html(implode(', ', $z->roles)); ?></span>
                        <span></span>
                        <span></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="sz-konto__schluss">
            <span class="sz-konto__schluss-linie" aria-hidden="true"></span>
            <p class="sz-konto__schluss-satz">
                <?php echo esc_html__('Ihre Konditionen, Ihr Verlauf, Ihre Belege.', 'sapelza-shop'); ?>
            </p>
        </div>
    </div>
    <?php
});
