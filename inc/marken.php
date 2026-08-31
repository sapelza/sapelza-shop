<?php

/**
 * Marken erkennen.
 *
 * Der Shop führt die Taxonomie product_brand — WooCommerce bringt sie
 * selbst mit —, aber sie ist leer. Ohne Begriffe keine Markenleiste,
 * kein Filtern nach Marke, kein Markenabschnitt auf der Startseite.
 *
 * 288 Artikel von Hand zuzuordnen ist ein Tagwerk. Diese Seite liest
 * stattdessen aus jedem Artikelnamen das erste Wort heraus, fasst
 * gleiche zusammen und schlägt sie als Marke vor. Sie sehen die Liste
 * durch, streichen was keine Marke ist, bessern Schreibweisen aus und
 * weisen den Rest in einem Zug zu.
 *
 * Zwei Regeln, an die sich die Seite hält:
 *
 * 1. Sie schlägt vor, sie handelt nicht. Ohne Klick auf "Zuweisen"
 *    wird nichts geschrieben.
 * 2. Sie fügt nur hinzu. Eine bestehende Markenzuordnung wird nie
 *    überschrieben und nie gelöscht.
 *
 * Das erste Wort ist eine Vermutung, keine Wahrheit: bei "Algae —
 * Algenmittel Pool" ist es der Artikel, nicht die Marke. Deshalb die
 * Durchsicht.
 *
 * @package sapelza-shop
 */

defined('ABSPATH') || exit;

const SZ_MARKEN_SEITE = 'sz-marken-erkennen';

/**
 * Der Markenvorschlag aus einem Artikelnamen.
 *
 * Das erste Wort, ohne Klammerzusätze und ohne reine Zahlen.
 */
function sz_marke_raten(string $name): string
{
    $name = trim(wp_strip_all_tags($name));
    if ($name === '') return '';

    /* Am ersten Trenner abschneiden: Gedankenstrich, Bindestrich mit
       Leerzeichen, Komma. Was davor steht, traegt die Marke. */
    $kopf = preg_split('/\s[\x{2013}\x{2014}\-–—]\s|,/u', $name)[0];
    $kopf = trim($kopf);

    $woerter = preg_split('/\s+/u', $kopf);
    $erstes  = $woerter[0] ?? '';

    /* Reine Zahlen und Masse sind keine Marken. */
    if ($erstes === '' || preg_match('/^[\d.,]+$/u', $erstes)) return '';
    if (mb_strlen($erstes) < 2) return '';

    return $erstes;
}

/**
 * Alle Vorschläge, nach Häufigkeit.
 *
 * @return array<string, int[]> Marke => Produkt-IDs
 */
function sz_marken_vorschlaege(): array
{
    $artikel = get_posts([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 500,
        'fields'         => 'ids',
    ]);

    $taxonomie = function_exists('sz_marken_taxonomie') ? sz_marken_taxonomie() : '';
    if ($taxonomie === '') $taxonomie = taxonomy_exists('product_brand') ? 'product_brand' : '';

    $gefunden = [];

    foreach ($artikel as $id) {
        /* Was schon eine Marke hat, bleibt unangetastet. */
        if ($taxonomie !== '') {
            $hat = wp_get_object_terms($id, $taxonomie, ['fields' => 'ids']);
            if (!is_wp_error($hat) && $hat) continue;
        }

        $marke = sz_marke_raten(get_the_title($id));
        if ($marke === '') continue;

        $schluessel = mb_strtolower($marke);
        if (!isset($gefunden[$schluessel])) $gefunden[$schluessel] = ['name' => $marke, 'ids' => []];
        $gefunden[$schluessel]['ids'][] = $id;
    }

    uasort($gefunden, static fn($a, $b) => count($b['ids']) <=> count($a['ids']));

    return $gefunden;
}

/* ===================================================================
   Die Seite
   =================================================================== */

add_action('admin_menu', function () {
    if (!taxonomy_exists('product_brand') && !taxonomy_exists('pa_marke')) return;

    add_submenu_page(
        'edit.php?post_type=product',
        __('Marken erkennen', 'sapelza-shop'),
        __('Marken erkennen', 'sapelza-shop'),
        'manage_woocommerce',
        SZ_MARKEN_SEITE,
        'sz_marken_seite'
    );
});

/** Die Zuordnung ausführen. */
function sz_marken_zuweisen(array $wahl): array
{
    $taxonomie = taxonomy_exists('product_brand') ? 'product_brand'
        : (taxonomy_exists('pa_marke') ? 'pa_marke' : '');

    if ($taxonomie === '') return ['marken' => 0, 'artikel' => 0];

    $marken = 0;
    $artikel = 0;

    foreach ($wahl as $name => $ids) {
        $name = trim($name);
        if ($name === '' || !$ids) continue;

        $begriff = term_exists($name, $taxonomie);
        if (!$begriff) {
            $begriff = wp_insert_term($name, $taxonomie);
            if (is_wp_error($begriff)) continue;
            $marken++;
        }

        $begriff_id = (int) $begriff['term_id'];

        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0 || get_post_type($id) !== 'product') continue;

            /* append = true: nichts ueberschreiben, nichts loeschen. */
            $ergebnis = wp_set_object_terms($id, [$begriff_id], $taxonomie, true);
            if (!is_wp_error($ergebnis)) $artikel++;
        }
    }

    return ['marken' => $marken, 'artikel' => $artikel];
}

/** Die Oberfläche. */
function sz_marken_seite(): void
{
    if (!current_user_can('manage_woocommerce')) return;

    $getan = null;

    if (isset($_POST['sz_marken_nonce']) && wp_verify_nonce(sanitize_key($_POST['sz_marken_nonce']), 'sz_marken')) {
        $wahl = [];

        $namen = isset($_POST['name']) && is_array($_POST['name']) ? $_POST['name'] : [];
        $gewaehlt = isset($_POST['nehmen']) && is_array($_POST['nehmen']) ? $_POST['nehmen'] : [];

        foreach ($gewaehlt as $schluessel => $ja) {
            $schluessel = sanitize_key($schluessel);
            $name = isset($namen[$schluessel]) ? sanitize_text_field(wp_unslash($namen[$schluessel])) : '';
            $ids  = isset($_POST['ids'][$schluessel]) ? array_map('intval', explode(',', sanitize_text_field(wp_unslash($_POST['ids'][$schluessel])))) : [];

            if ($name !== '' && $ids) $wahl[$name] = $ids;
        }

        $getan = sz_marken_zuweisen($wahl);
    }

    $vorschlaege = sz_marken_vorschlaege();

    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Marken erkennen', 'sapelza-shop'); ?></h1>

        <?php if ($getan !== null) : ?>
            <div class="notice notice-success">
                <p><?php printf(
                    esc_html__('%1$d Marken angelegt, %2$d Zuordnungen geschrieben.', 'sapelza-shop'),
                    (int) $getan['marken'],
                    (int) $getan['artikel']
                ); ?></p>
            </div>
        <?php endif; ?>

        <p style="max-width: 46em;">
            <?php echo esc_html__('Aus jedem Artikelnamen ist das erste Wort herausgelesen und mit gleichen zusammengefasst. Das ist eine Vermutung, keine Wahrheit — bei „Algae – Algenmittel Pool" ist das erste Wort der Artikel und nicht die Marke. Streichen Sie, was keine Marke ist, bessern Sie Schreibweisen aus, und weisen Sie den Rest zu.', 'sapelza-shop'); ?>
        </p>

        <p style="max-width: 46em;">
            <strong><?php echo esc_html__('Es wird nur hinzugefügt.', 'sapelza-shop'); ?></strong>
            <?php echo esc_html__('Artikel, die schon eine Marke tragen, stehen hier gar nicht erst — bestehende Zuordnungen werden nie überschrieben und nie gelöscht.', 'sapelza-shop'); ?>
        </p>

        <?php if (!$vorschlaege) : ?>
            <p><?php echo esc_html__('Nichts vorzuschlagen. Entweder tragen bereits alle Artikel eine Marke, oder aus den Namen lässt sich keine herauslesen.', 'sapelza-shop'); ?></p>
            <?php return;
        endif; ?>

        <form method="post">
            <?php wp_nonce_field('sz_marken', 'sz_marken_nonce'); ?>

            <table class="wp-list-table widefat striped" style="max-width: 60em;">
                <thead>
                    <tr>
                        <td style="width: 2em;"></td>
                        <th style="width: 16em;"><?php echo esc_html__('Marke', 'sapelza-shop'); ?></th>
                        <th style="width: 6em;"><?php echo esc_html__('Artikel', 'sapelza-shop'); ?></th>
                        <th><?php echo esc_html__('Zum Beispiel', 'sapelza-shop'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($vorschlaege as $schluessel => $eintrag) :
                    $sicher = sanitize_key($schluessel);
                    $beispiele = array_slice($eintrag['ids'], 0, 3);
                    ?>
                    <tr>
                        <td><input type="checkbox" name="nehmen[<?php echo esc_attr($sicher); ?>]" value="1"
                                   <?php checked(count($eintrag['ids']) > 1); ?>></td>
                        <td>
                            <input type="text" name="name[<?php echo esc_attr($sicher); ?>]"
                                   value="<?php echo esc_attr($eintrag['name']); ?>" class="regular-text">
                            <input type="hidden" name="ids[<?php echo esc_attr($sicher); ?>]"
                                   value="<?php echo esc_attr(implode(',', $eintrag['ids'])); ?>">
                        </td>
                        <td><?php echo esc_html(number_format_i18n(count($eintrag['ids']))); ?></td>
                        <td style="color:#666;">
                            <?php
                            $namen = array_map(static fn($id) => get_the_title($id), $beispiele);
                            echo esc_html(implode(' · ', $namen));
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <p>
                <button type="submit" class="button button-primary">
                    <?php echo esc_html__('Angehakte Marken zuweisen', 'sapelza-shop'); ?>
                </button>
            </p>

            <p style="color:#666; max-width: 46em;">
                <?php echo esc_html__('Vorausgewählt sind alle Vorschläge mit mehr als einem Artikel — ein einzelner Artikel ist meist kein Markenname, sondern ein Produktname.', 'sapelza-shop'); ?>
            </p>
        </form>
    </div>
    <?php
}
