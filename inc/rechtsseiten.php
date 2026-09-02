<?php
/**
 * Die Rechtsseiten.
 *
 * Der Shop verkauft seit Monaten ohne Impressum und ohne
 * Datenschutzerklärung. Beides ist keine Formsache: wer in Italien
 * gewerblich verkauft, muss Anbieterkennzeichnung und
 * Datenschutzinformation vorhalten, und im Fuß muss man sie finden.
 *
 * Dazu kommt, dass hier nicht nur Betriebe bestellen: Bauern und
 * Privathaushalte kaufen ohne MwSt.-Nummer. Damit gilt der Codice del
 * Consumo — Widerrufsbelehrung und Verbraucher-Gewährleistung sind
 * dann keine Kür. Wer nicht ordnungsgemäß über den Widerruf belehrt,
 * dem läuft die Frist nicht vierzehn Tage, sondern zwölf Monate und
 * vierzehn Tage (Art. 53 Codice del Consumo).
 *
 * Die Texte selbst stehen in inc/rechtstexte.php — sie sind länger
 * geworden als die Werkstatt, die sie einsetzt.
 *
 * Was diese Datei tut und was nicht
 * ---------------------------------
 *
 * Sie legt **Entwürfe** an — nie veröffentlichte Seiten. Der Text darin
 * ist ein Gerüst mit ausgewiesenen Lücken, kein fertiger Rechtstext. Die
 * Angaben, die hineingehören (Handelsregister, REA-Nummer, gesetzlicher
 * Vertreter), kenne ich nicht, und die Rechtslage zu beurteilen ist
 * nicht meine Aufgabe. Ein Gerüst mit ehrlichen Lücken ist mehr wert als
 * ein glatter Text, der Falsches behauptet.
 *
 * Sie überschreibt nichts. Eine Seite, die es schon gibt, bleibt
 * unberührt — auch dann, wenn sie leer ist.
 *
 * Und sie veröffentlicht nichts. Der Schritt von "Entwurf" zu
 * "veröffentlicht" gehört dem Betreiber, nicht dem Plugin.
 *
 * @package sapelza-shop
 */

if (!defined('ABSPATH')) exit;

/**
 * Welche Seiten der Shop braucht, und woran man sie erkennt.
 *
 * Die Liste steht hier im Plugin, weil sie zum Verhalten gehört: das
 * Theme fragt sie nur ab, um die Links im Fuß zu setzen, und kommt auch
 * ohne Plugin zurecht (siehe sz_theme_rechtsseiten() in functions.php).
 *
 * Mehrere Kennungen je Seite, weil verschiedene Werkzeuge verschiedene
 * Kennungen anlegen: WordPress selbst nennt die Datenschutzseite gern
 * "datenschutzerklaerung", von Hand angelegt heißt sie meist
 * "datenschutz".
 *
 * @return array<string, array{titel: string, kennungen: string[], pflicht: bool}>
 */
function sz_rechtsseiten(): array
{
    return [
        'impressum' => [
            'titel'     => __('Impressum', 'sapelza-shop'),
            'kennungen' => ['impressum', 'anbieterkennzeichnung', 'note-legali'],
            'pflicht'   => true,
        ],
        'datenschutz' => [
            'titel'     => __('Datenschutzerklärung', 'sapelza-shop'),
            'kennungen' => ['datenschutz', 'datenschutzerklaerung', 'privacy', 'privacy-policy'],
            'pflicht'   => true,
        ],
        'agb' => [
            'titel'     => __('Allgemeine Geschäftsbedingungen', 'sapelza-shop'),
            'kennungen' => ['agb', 'geschaeftsbedingungen'],
            'pflicht'   => true,
        ],
        'widerruf' => [
            'titel'     => __('Widerrufsbelehrung', 'sapelza-shop'),
            'kennungen' => ['widerruf', 'widerrufsbelehrung', 'widerrufsrecht'],
            'pflicht'   => true,
        ],
        'versand' => [
            'titel'     => __('Lieferung und Zahlung', 'sapelza-shop'),
            'kennungen' => ['lieferung-und-zahlung', 'versand', 'versand-und-zahlung'],
            'pflicht'   => false,
        ],
    ];
}

/**
 * Die Seite zu einer Kennung — oder null.
 *
 * Sucht der Reihe nach alle Kennungen ab und nimmt die erste, die es
 * gibt. Entwürfe zählen mit: sie sind angelegt, nur noch nicht fertig,
 * und ein zweites Mal anlegen wäre falsch.
 */
function sz_rechtsseite_finden(string $schluessel): ?WP_Post
{
    $alle = sz_rechtsseiten();
    if (!isset($alle[$schluessel])) return null;

    /* Die von WordPress bestimmte Datenschutzseite hat Vorrang vor jeder
       Kennung — sie ist die, auf die auch der Anmeldebildschirm zeigt. */
    if ($schluessel === 'datenschutz') {
        $id = (int) get_option('wp_page_for_privacy_policy');
        if ($id > 0) {
            $seite = get_post($id);
            if ($seite instanceof WP_Post && $seite->post_status !== 'trash') return $seite;
        }
    }

    foreach ($alle[$schluessel]['kennungen'] as $kennung) {
        $seite = get_page_by_path($kennung, OBJECT, 'page');
        if ($seite instanceof WP_Post && $seite->post_status !== 'trash') return $seite;
    }

    return null;
}

/**
 * Die Adresse einer Rechtsseite, wenn es sie gibt und sie sichtbar ist.
 *
 * Entwürfe geben keine Adresse zurück: ein Link im Fuß, der Besucher auf
 * eine leere Seite oder in einen 404 führt, ist schlimmer als gar kein
 * Link — er sieht aus, als wäre die Pflicht erfüllt.
 */
function sz_rechtsseite_adresse(string $schluessel): string
{
    $seite = sz_rechtsseite_finden($schluessel);
    if (!$seite || $seite->post_status !== 'publish') return '';

    return (string) get_permalink($seite);
}

/* ===================================================================
   Die Werkstattseite
   =================================================================== */

add_action('admin_menu', static function (): void {
    add_options_page(
        __('Rechtsseiten', 'sapelza-shop'),
        __('Rechtsseiten', 'sapelza-shop'),
        'manage_options',
        'sz-rechtsseiten',
        'sz_rechtsseiten_werkstatt'
    );
});

/**
 * Anlegen — als Entwurf, nie veröffentlicht, nie überschreibend.
 */
function sz_rechtsseiten_anlegen(array $schluessel): array
{
    $alle    = sz_rechtsseiten();
    $bericht = [];

    foreach ($schluessel as $s) {
        if (!isset($alle[$s])) continue;

        if (sz_rechtsseite_finden($s)) {
            $bericht[] = sprintf(
                /* translators: %s ist der Name der Seite. */
                __('%s gibt es schon — unberührt gelassen.', 'sapelza-shop'),
                $alle[$s]['titel']
            );
            continue;
        }

        $id = wp_insert_post([
            'post_type'    => 'page',
            'post_status'  => 'draft',
            'post_title'   => $alle[$s]['titel'],
            'post_name'    => $alle[$s]['kennungen'][0],
            'post_content' => sz_recht_text($s),
        ], true);

        if (is_wp_error($id)) {
            $bericht[] = sprintf(
                /* translators: 1: Name der Seite, 2: Fehlermeldung. */
                __('%1$s ließ sich nicht anlegen: %2$s', 'sapelza-shop'),
                $alle[$s]['titel'],
                $id->get_error_message()
            );
            continue;
        }

        /*
         * Die Datenschutzseite wird WordPress auch als solche bekannt
         * gemacht — davon hängt der Link auf dem Anmeldebildschirm ab.
         * Nur, wenn noch keine bestimmt ist: eine bestehende Zuordnung
         * zu überschreiben wäre ein Eingriff, den niemand verlangt hat.
         */
        if ($s === 'datenschutz' && !get_option('wp_page_for_privacy_policy')) {
            update_option('wp_page_for_privacy_policy', $id);
        }

        $bericht[] = sprintf(
            /* translators: %s ist der Name der Seite. */
            __('%s als Entwurf angelegt.', 'sapelza-shop'),
            $alle[$s]['titel']
        );
    }

    return $bericht;
}

function sz_rechtsseiten_werkstatt(): void
{
    if (!current_user_can('manage_options')) return;

    $bericht = [];

    if (isset($_POST['sz_recht_anlegen'])) {
        check_admin_referer('sz_recht');
        $wahl = isset($_POST['seiten']) && is_array($_POST['seiten'])
            ? array_map('sanitize_key', wp_unslash($_POST['seiten']))
            : [];
        $bericht = sz_rechtsseiten_anlegen($wahl);
    }

    $alle = sz_rechtsseiten();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Rechtsseiten', 'sapelza-shop'); ?></h1>

        <p style="max-width:44rem">
            <?php echo esc_html__('Impressum und Datenschutzerklärung sind für einen Shop, der gewerblich verkauft, verpflichtend — und im Fuß der Seite muss man sie finden. Solange sie fehlen, erscheint dort auch kein Link: ein Link, der ins Leere führt, sieht aus, als wäre die Pflicht erfüllt.', 'sapelza-shop'); ?>
        </p>

        <p style="max-width:44rem">
            <?php echo esc_html__('Weil hier auch Privatkunden ohne MwSt.-Nummer bestellen — Bauern, Haushalte —, gilt zusätzlich das Verbraucherrecht: Widerrufsbelehrung und Verbraucher-Gewährleistung. Eine unvollständige Widerrufsbelehrung verlängert die Frist von vierzehn Tagen auf zwölf Monate und vierzehn Tage. Diese Seite ist deshalb die, die am gründlichsten geprüft gehört.', 'sapelza-shop'); ?>
        </p>

        <p style="max-width:44rem">
            <strong><?php echo esc_html__('Was dieser Knopf tut:', 'sapelza-shop'); ?></strong>
            <?php echo esc_html__('Er legt die fehlenden Seiten als Entwurf an, mit einem Gerüst und ausgewiesenen Lücken. Er veröffentlicht nichts und überschreibt nichts. Der Text ist kein fertiger Rechtstext — die Angaben müssen ergänzt und der Inhalt muss von jemandem geprüft werden, der die Rechtslage kennt.', 'sapelza-shop'); ?>
        </p>

        <?php foreach ($bericht as $zeile) : ?>
            <div class="notice notice-info"><p><?php echo esc_html($zeile); ?></p></div>
        <?php endforeach; ?>

        <form method="post">
            <?php wp_nonce_field('sz_recht'); ?>
            <table class="widefat striped" style="max-width:60rem">
                <thead>
                    <tr>
                        <th style="width:2rem"></th>
                        <th><?php echo esc_html__('Seite', 'sapelza-shop'); ?></th>
                        <th><?php echo esc_html__('Stand', 'sapelza-shop'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alle as $sz_s => $sz_d) :
                        $sz_seite = sz_rechtsseite_finden($sz_s); ?>
                        <tr>
                            <td>
                                <?php if (!$sz_seite) : ?>
                                    <input type="checkbox" name="seiten[]" value="<?php echo esc_attr($sz_s); ?>" checked>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html($sz_d['titel']); ?></strong>
                                <?php if ($sz_d['pflicht']) : ?>
                                    <span style="color:#b52f36"> · <?php echo esc_html__('verpflichtend', 'sapelza-shop'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$sz_seite) : ?>
                                    <?php echo esc_html__('fehlt', 'sapelza-shop'); ?>
                                <?php elseif ($sz_seite->post_status === 'publish') : ?>
                                    <?php echo esc_html__('veröffentlicht', 'sapelza-shop'); ?> ·
                                    <a href="<?php echo esc_url(get_edit_post_link($sz_seite->ID)); ?>"><?php echo esc_html__('bearbeiten', 'sapelza-shop'); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html__('Entwurf — noch nicht sichtbar', 'sapelza-shop'); ?> ·
                                    <a href="<?php echo esc_url(get_edit_post_link($sz_seite->ID)); ?>"><?php echo esc_html__('bearbeiten', 'sapelza-shop'); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p>
                <button type="submit" name="sz_recht_anlegen" value="1" class="button button-primary">
                    <?php echo esc_html__('Fehlende Seiten als Entwurf anlegen', 'sapelza-shop'); ?>
                </button>
            </p>
        </form>

        <h2><?php echo esc_html__('Die Vorlage, die schon da ist', 'sapelza-shop'); ?></h2>
        <p style="max-width:44rem">
            <?php
            printf(
                /* translators: %s ist ein Link zu den Datenschutz-Einstellungen. */
                esc_html__('Für die Datenschutzerklärung hält WordPress selbst einen ausführlichen Entwurf bereit — unter %s. Dort tragen auch WooCommerce und andere Plugins ihre Abschnitte ein, etwa zu Konto, Bestellungen und Zahlung. Dieser Text ist die bessere Grundlage als jedes Muster von außen, weil er beschreibt, was auf dieser Installation tatsächlich läuft.', 'sapelza-shop'),
                '<a href="' . esc_url(admin_url('options-privacy.php')) . '">' . esc_html__('Einstellungen → Datenschutz', 'sapelza-shop') . '</a>'
            );
            ?>
        </p>
    </div>
    <?php
}
