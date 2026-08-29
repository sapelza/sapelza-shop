<?php
/**
 * „Meine Artikel" — der betriebseigene Katalog.
 *
 * Kein Vorschlagswesen: die Liste zeigt ausschließlich, was der Betrieb
 * schon einmal bezogen hat, und sie steht nur da, wo der Kunde sie selbst
 * aufruft. Keine E-Mails, keine Einblendungen im Warenkorb.
 *
 * Einsatz: eine Seite anlegen und [sz_meine_artikel] hineinschreiben.
 */

if (!defined('ABSPATH')) exit;

/**
 * Alle je bezogenen Produkte des angemeldeten Kunden, neueste zuerst.
 *
 * @return array<int, array{id:int, anzahl:int, zuletzt:int}>
 */
function sz_bezogene_artikel(int $grenze = 200): array
{
    if (!is_user_logged_in()) return [];

    $bestellungen = wc_get_orders([
        'customer_id' => get_current_user_id(),
        'limit'       => 60,
        'orderby'     => 'date',
        'order'       => 'DESC',
        'status'      => ['wc-processing', 'wc-completed', 'wc-on-hold'],
    ]);

    $artikel = [];
    foreach ($bestellungen as $bestellung) {
        $wann = $bestellung->get_date_created() ? $bestellung->get_date_created()->getTimestamp() : 0;
        foreach ($bestellung->get_items() as $posten) {
            $id = $posten->get_variation_id() ?: $posten->get_product_id();
            if (!$id) continue;
            if (!isset($artikel[$id])) {
                $artikel[$id] = ['id' => $id, 'anzahl' => 0, 'zuletzt' => $wann];
            }
            $artikel[$id]['anzahl'] += (int) $posten->get_quantity();
            $artikel[$id]['zuletzt'] = max($artikel[$id]['zuletzt'], $wann);
        }
    }

    uasort($artikel, fn($a, $b) => $b['zuletzt'] <=> $a['zuletzt']);
    return array_slice($artikel, 0, $grenze, true);
}

add_shortcode('sz_meine_artikel', function () {
    if (!is_user_logged_in()) {
        return '<p class="sz-hinweis">Bitte melden Sie sich an, um Ihre bisher bezogenen Artikel zu sehen.</p>';
    }

    $artikel = sz_bezogene_artikel();
    if (!$artikel) {
        return '<p class="sz-hinweis">Hier stehen die Artikel, die Sie schon einmal bezogen haben. '
             . 'Sobald die erste Bestellung ausgeliefert ist, füllt sich die Liste von selbst.</p>';
    }

    $basis = function_exists('sz_erfassung_url') ? sz_erfassung_url() : home_url('/schnellerfassung/');

    /*
     * Erst sammeln, dann zeichnen. Nur so ist vor der Kopfzeile bekannt,
     * ob ueberhaupt ein Artikel einen Bestand fuehrt — eine Spalte voller
     * Striche sieht aus, als fehlte etwas.
     */
    $posten      = [];
    $mit_bestand = false;

    foreach ($artikel as $eintrag) {
        $produkt = wc_get_product($eintrag['id']);
        if (!$produkt || !$produkt->is_purchasable()) continue;

        if ($produkt->managing_stock()) $mit_bestand = true;
        $posten[] = ['produkt' => $produkt, 'zuletzt' => (int) $eintrag['zuletzt']];
    }

    if (!$posten) {
        return '<p class="sz-hinweis">Ihre bisherigen Artikel sind derzeit nicht bestellbar.</p>';
    }

    $stift = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>';

    ob_start();
    ?>
    <div class="sz-artikelbereich"
         data-sz-namen
         data-nonce="<?php echo esc_attr(wp_create_nonce('sz_namen')); ?>"
         data-erfassung="<?php echo esc_attr(wp_create_nonce('sz_erfassung')); ?>"
         data-ziel="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
         data-basis="<?php echo esc_url($basis); ?>">

        <p class="sz-kapitelmarke">
            <span class="sz-kapitelmarke__nr mono">02</span>
            <span class="hairline"></span>
            <span class="sz-kapitelmarke__kicker mono"><?php echo esc_html__('Ihr Katalog', 'sapelza-shop'); ?></span>
        </p>

        <h1 class="sz-artikeltitel"><?php echo esc_html__('Meine Artikel', 'sapelza-shop'); ?></h1>

        <p class="sz-artikellead">
            <?php echo esc_html__('Ihr eigener Katalog aus allem, was Sie bisher bezogen haben, auf Wunsch mit Ihren internen Bezeichnungen statt unseren. Was regelmäßig gebraucht wird, liegt in zwei Klicks wieder im Warenkorb.', 'sapelza-shop'); ?>
        </p>

        <label class="sz-artikelsuche">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path>
            </svg>
            <span class="screen-reader-text"><?php echo esc_html__('In Ihren Artikeln suchen', 'sapelza-shop'); ?></span>
            <input type="search" data-sz-filter
                   placeholder="<?php echo esc_attr__('In Ihren Artikeln suchen …', 'sapelza-shop'); ?>">
        </label>

        <div class="sz-artikeltafel">
            <table class="sz-artikeltabelle sz-meine-artikel<?php echo $mit_bestand ? '' : ' ist-ohne-bestand'; ?>">
                <thead>
                    <tr>
                        <th></th>
                        <th class="sz-z-name"><?php echo esc_html__('Ihre Bezeichnung', 'sapelza-shop'); ?></th>
                        <th class="sz-z-artikel"><?php echo esc_html__('Artikel', 'sapelza-shop'); ?></th>
                        <th class="sz-z-bestand sz-spalte-bestand"><?php echo esc_html__('Bestand', 'sapelza-shop'); ?></th>
                        <th class="sz-z-preis"><?php echo esc_html__('Preis', 'sapelza-shop'); ?></th>
                        <th class="sz-z-menge"><?php echo esc_html__('Menge', 'sapelza-shop'); ?></th>
                        <th class="sz-z-korb"></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $lfd = 0;
                foreach ($posten as $eintrag) {
                    $produkt = $eintrag['produkt'];
                    $lfd++;

                    $vorrat = $produkt->managing_stock() ? (int) $produkt->get_stock_quantity() : null;
                    $eigen  = function_exists('sz_eigener_name') ? sz_eigener_name($produkt->get_id()) : '';
                    $wer    = ($eigen !== '' && function_exists('sz_name_geaendert_von'))
                            ? sz_name_geaendert_von($produkt->get_id()) : '';

                    $marke = '';
                    if (function_exists('sz_marken_taxonomie')) {
                        $tax = sz_marken_taxonomie();
                        if ($tax !== '') {
                            $b = get_the_terms($produkt->get_id(), $tax);
                            if ($b && !is_wp_error($b)) $marke = $b[0]->name;
                        }
                    }

                    $tage = (int) floor((time() - $eintrag['zuletzt']) / DAY_IN_SECONDS);
                    $such = mb_strtolower($eigen . ' ' . $produkt->get_name() . ' ' . $produkt->get_sku());
                    ?>
                    <tr class="sz-artikelzeile"
                        data-sz-artikel="<?php echo esc_attr((string) $produkt->get_id()); ?>"
                        data-sz-lfd="<?php echo esc_attr((string) $lfd); ?>"
                        data-sz-suchtext="<?php echo esc_attr($such); ?>">

                        <td class="sz-artikelwahl">
                            <input type="checkbox" data-sz-wahl
                                   value="<?php echo esc_attr((string) $produkt->get_sku()); ?>"
                                   aria-label="<?php
                                       printf(esc_attr__('%s für den Etikettenbogen auswählen', 'sapelza-shop'),
                                              esc_attr($eigen !== '' ? $eigen : $produkt->get_name())); ?>">
                        </td>

                        <td class="sz-z-name">
                            <button type="button" class="sz-eigenname<?php echo $eigen === '' ? ' ist-leer' : ''; ?>"
                                    data-sz-eigenname>
                                <span class="sz-eigenname__wort"><?php echo $eigen !== ''
                                    ? esc_html($eigen)
                                    : esc_html__('Eigenen Namen vergeben', 'sapelza-shop'); ?></span>
                                <?php echo $stift; ?>
                            </button>
                            <?php if ($eigen !== '') : ?>
                                <span class="sz-eigenname__katalog"><?php echo esc_html($produkt->get_name()); ?></span>
                            <?php endif; ?>
                            <?php if ($wer !== '') : ?>
                                <span class="sz-eigenname__wer">
                                    <?php printf(esc_html__('zuletzt geändert von %s', 'sapelza-shop'), esc_html($wer)); ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="sz-z-artikel">
                            <?php if ($marke) : ?>
                                <span class="sz-artikelmarke"><?php echo esc_html($marke); ?></span>
                            <?php endif; ?>
                            <a class="sz-katalogname" href="<?php echo esc_url($produkt->get_permalink()); ?>">
                                <?php echo esc_html($produkt->get_name()); ?>
                            </a>
                            <span class="sz-artikelrhythmus">
                                <?php
                                printf(
                                    /* translators: %d ist die Zahl der Tage seit der letzten Lieferung. */
                                    esc_html(_n('letzte Lieferung vor %d Tag', 'letzte Lieferung vor %d Tagen', $tage, 'sapelza-shop')),
                                    (int) $tage
                                );
                                ?>
                            </span>
                        </td>

                        <td class="sz-z-bestand sz-spalte-bestand">
                            <?php if ($vorrat !== null) : ?>
                                <span class="sz-artikelbestand">
                                    <span class="sz-punkt" aria-hidden="true"></span>
                                    <?php printf(esc_html__('%d Stk.', 'sapelza-shop'), $vorrat); ?>
                                </span>
                            <?php else : ?>
                                <span class="sz-artikelbestand">—</span>
                            <?php endif; ?>
                        </td>

                        <td class="sz-z-preis">
                            <span class="sz-artikelpreis"><?php echo wp_kses_post($produkt->get_price_html()); ?></span>
                        </td>

                        <td class="sz-z-menge">
                            <span class="sz-menge">
                                <button type="button" data-sz-minus aria-label="<?php echo esc_attr__('Menge verringern', 'sapelza-shop'); ?>">−</button>
                                <input type="number" min="1" value="1" data-sz-menge
                                       aria-label="<?php echo esc_attr__('Menge', 'sapelza-shop'); ?>">
                                <button type="button" data-sz-plus aria-label="<?php echo esc_attr__('Menge erhöhen', 'sapelza-shop'); ?>">+</button>
                            </span>
                        </td>

                        <td class="sz-z-korb">
                            <button type="button" class="sz-inkorb" data-sz-inkorb
                                    aria-label="<?php echo esc_attr__('In den Warenkorb', 'sapelza-shop'); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 5v14M5 12h14"></path>
                                </svg>
                            </button>
                        </td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
        </div>

        <p class="sz-artikelfuss">
            <span><?php printf(esc_html(_n('%d Artikel', '%d Artikel', $lfd, 'sapelza-shop')), (int) $lfd); ?></span>
            <span><?php echo esc_html__('Auf den Namen klicken, um ihn zu ändern — leer heißt zurück zum Katalognamen', 'sapelza-shop'); ?></span>
        </p>

        <div class="hairline sz-artikeltrenner"></div>

        <?php /* --- Kapitel: Etiketten ------------------------------------ */ ?>
        <section class="sz-etikettenkapitel">
            <p class="kicker">
                <span class="kicker__punkt" aria-hidden="true"></span>
                <?php echo esc_html__('Am Regal', 'sapelza-shop'); ?>
            </p>

            <h2 class="sz-etiketten__titel"><?php echo esc_html__('Etiketten zum Aufkleben', 'sapelza-shop'); ?></h2>

            <p class="sz-etiketten__lead">
                <?php echo esc_html__('Wählen Sie oben aus, was ans Regal soll. Auf dem Etikett steht Ihre Bezeichnung — im Code selbst steht nur die Artikelnummer. Deshalb bleiben gedruckte Etiketten gültig, auch wenn Sie einen Artikel später anders nennen.', 'sapelza-shop'); ?>
            </p>

            <div class="sz-etiketten__wahl">
                <span class="sz-etiketten-zahl mono" data-sz-gewaehlt>0 ausgewählt</span>

                <div class="sz-etiketten__groessen" role="group"
                     aria-label="<?php echo esc_attr__('Etikettengröße', 'sapelza-shop'); ?>">
                    <button type="button" class="sz-groesse" data-sz-groesse="70x37" aria-pressed="true">
                        <?php echo esc_html__('70 × 37 mm · 24 Stück', 'sapelza-shop'); ?>
                    </button>
                    <button type="button" class="sz-groesse" data-sz-groesse="48x25" aria-pressed="false">
                        <?php echo esc_html__('48,5 × 25,4 mm · 44 Stück', 'sapelza-shop'); ?>
                    </button>
                </div>

                <button type="button" class="sz-erfassung__knopf" data-sz-drucken disabled>
                    <?php echo esc_html__('Bogen erzeugen', 'sapelza-shop'); ?>
                </button>
                <button type="button" class="sz-bogen__zu" data-sz-alle>
                    <?php echo esc_html__('Alle auswählen', 'sapelza-shop'); ?>
                </button>
            </div>

            <?php
            /*
             * Die Vorschau steht im Entwurf nicht, sie kam auf Wunsch dazu:
             * ohne Auswahl die fuenf zuletzt bestellten Artikel als Anstoss,
             * mit Auswahl deren erste zehn.
             */
            ?>
            <p class="sz-etiketten__vorschautitel mono" data-sz-vorschautitel>
                <?php echo esc_html__('Zuletzt bestellt', 'sapelza-shop'); ?>
            </p>
            <div class="sz-etiketten__vorschau" data-sz-vorschau></div>
        </section>

        <div class="sz-artikelschluss">
            <span class="sz-artikelschluss__linie" aria-hidden="true"></span>
            <p class="sz-artikelschluss__satz">
                <?php echo esc_html__('Ihre Bezeichnung, nicht unsere Artikelnummer.', 'sapelza-shop'); ?>
            </p>
        </div>
    </div>
    <?php
    return ob_get_clean();
});
