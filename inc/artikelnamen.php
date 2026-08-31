<?php
/**
 * Eigene Artikelnamen.
 *
 * „Spüli grün" statt „Handspülmittel Konzentrat, 10 l". Wer am Regal
 * steht, denkt in eigenen Begriffen, nicht in Katalogbezeichnungen.
 *
 * Drei Entscheidungen tragen das Ganze:
 *
 * 1. EIGENE TABELLE, kein JSON im Benutzerkonto. Bei Subkonten würde sonst
 *    der letzte Schreibvorgang den Block des anderen überschreiben —
 *    Marco benennt um, Annas Änderung ist weg. Mit einer Tabelle betrifft
 *    jede Änderung genau eine Zeile.
 *
 * 2. DER NAME HÄNGT AM BETRIEB, nicht an der Person. Sonst heißt derselbe
 *    Kanister für Marco anders als für Anna. Bei B2BKing heißt das: die
 *    Nummer des übergeordneten Kontos speichern, nicht die des Angemeldeten.
 *
 * 3. AN DER VARIANTE, nicht am Produkt. „Spüli grün 10 l" und
 *    „Spüli grün 5 l" sind zwei Dinge; die Produkt-ID wäre für beide
 *    dieselbe.
 */

if (!defined('ABSPATH')) exit;

const SZ_NAMEN_FASSUNG = 1;

/**
 * Die Betriebsnummer des angemeldeten Benutzers.
 *
 * Subkonten geben die Nummer ihres übergeordneten Kontos zurück — daran
 * hängen die Namen. Wer ohne Subkonto arbeitet, ist sein eigener Betrieb.
 */
function sz_betrieb_id(): int
{
    $ich = get_current_user_id();
    if (!$ich) return 0;

    /*
     * B2BKing hat den Schlüssel im Laufe der Fassungen umbenannt. Beide
     * werden geprüft; ein Filter erlaubt eine andere Zuordnung, falls die
     * Betriebsstruktur später woanders steht.
     */
    foreach (['b2bking_account_parent', 'b2bking_parent_account'] as $schluessel) {
        $eltern = (int) get_user_meta($ich, $schluessel, true);
        if ($eltern > 0) return (int) apply_filters('sz_betrieb_id', $eltern, $ich);
    }

    return (int) apply_filters('sz_betrieb_id', $ich, $ich);
}

/** Der Name der Tabelle. */
function sz_namen_tabelle(): string
{
    global $wpdb;
    return $wpdb->prefix . 'sz_artikelnamen';
}

/**
 * Die Tabelle anlegen oder nachziehen.
 *
 * Läuft bei jedem Laden, tut aber nur etwas, wenn die Fassungsnummer sich
 * geändert hat — dbDelta bei jedem Aufruf wäre unnötige Arbeit.
 */
function sz_namen_tabelle_pruefen(): void
{
    if ((int) get_option('sz_namen_fassung') === SZ_NAMEN_FASSUNG) return;

    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $tabelle  = sz_namen_tabelle();
    $kollation = $wpdb->get_charset_collate();

    /*
     * Der Schlüssel ist (Betrieb, Produkt) — ein Betrieb hat je Artikel
     * genau einen Namen. Damit ist ein Doppeleintrag technisch unmöglich.
     */
    dbDelta("CREATE TABLE {$tabelle} (
        betrieb_id BIGINT UNSIGNED NOT NULL,
        produkt_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(60) NOT NULL DEFAULT '',
        geaendert_von BIGINT UNSIGNED NOT NULL DEFAULT 0,
        geaendert_am DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (betrieb_id, produkt_id),
        KEY betrieb (betrieb_id)
    ) {$kollation};");

    update_option('sz_namen_fassung', SZ_NAMEN_FASSUNG);
}

add_action('init', 'sz_namen_tabelle_pruefen', 5);

/* ===================================================================
   Lesen
   =================================================================== */

/**
 * Alle eigenen Namen des Betriebs, als produkt_id => name.
 *
 * In einem Zug geholt und im Aufruf gemerkt: die Liste „Meine Artikel"
 * fragt sonst je Zeile einmal die Datenbank.
 *
 * @return array<int,string>
 */
function sz_eigene_namen(): array
{
    static $merker = null;
    if ($merker !== null) return $merker;

    $betrieb = sz_betrieb_id();
    if (!$betrieb) return $merker = [];

    global $wpdb;
    $tabelle = sz_namen_tabelle();

    $zeilen = $wpdb->get_results(
        $wpdb->prepare("SELECT produkt_id, name FROM {$tabelle} WHERE betrieb_id = %d", $betrieb),
        ARRAY_A
    );

    $merker = [];
    foreach ((array) $zeilen as $z) {
        if ($z['name'] !== '') $merker[(int) $z['produkt_id']] = $z['name'];
    }

    return $merker;
}

/** Der eigene Name eines Artikels, oder ''. */
function sz_eigener_name(int $produkt_id): string
{
    $alle = sz_eigene_namen();
    return $alle[$produkt_id] ?? '';
}

/**
 * Der Name, der angezeigt werden soll.
 *
 * Eigener Name, wenn einer gesetzt ist — sonst der Katalogname.
 */
function sz_anzeigename(WC_Product $artikel): string
{
    $eigen = sz_eigener_name($artikel->get_id());
    return $eigen !== '' ? $eigen : $artikel->get_name();
}

/**
 * Produkt-IDs, deren eigener Name zu einem Suchbegriff passt.
 *
 * Ohne das wäre der eigene Name die halbe Miete wert: wer „Spüli" tippt,
 * muss den Artikel finden.
 *
 * @return int[]
 */
function sz_namen_suchen(string $begriff): array
{
    $begriff = trim($begriff);
    $betrieb = sz_betrieb_id();
    if ($begriff === '' || !$betrieb) return [];

    global $wpdb;
    $tabelle = sz_namen_tabelle();

    $treffer = $wpdb->get_col($wpdb->prepare(
        "SELECT produkt_id FROM {$tabelle} WHERE betrieb_id = %d AND name LIKE %s",
        $betrieb,
        '%' . $wpdb->esc_like($begriff) . '%'
    ));

    return array_map('intval', (array) $treffer);
}

/* ===================================================================
   Schreiben
   =================================================================== */

/**
 * Einen Namen setzen oder löschen.
 *
 * Ein leerer Name LÖSCHT den Eintrag — so macht man es rückgängig, ohne
 * einen zweiten Knopf zu brauchen. Danach gilt wieder der Katalogname.
 */
function sz_namen_setzen(int $produkt_id, string $name): bool
{
    $betrieb = sz_betrieb_id();
    if (!$betrieb || $produkt_id <= 0) return false;

    global $wpdb;
    $tabelle = sz_namen_tabelle();

    /* Vierzig Zeichen, nicht aus Sparsamkeit: er muss aufs Regaletikett
       passen. */
    $name = trim(wp_strip_all_tags($name));
    if (function_exists('mb_substr')) $name = mb_substr($name, 0, 40);
    else $name = substr($name, 0, 40);

    if ($name === '') {
        return false !== $wpdb->delete($tabelle, [
            'betrieb_id' => $betrieb,
            'produkt_id' => $produkt_id,
        ], ['%d', '%d']);
    }

    return false !== $wpdb->replace($tabelle, [
        'betrieb_id'    => $betrieb,
        'produkt_id'    => $produkt_id,
        'name'          => $name,
        'geaendert_von' => get_current_user_id(),
        'geaendert_am'  => current_time('mysql'),
    ], ['%d', '%d', '%s', '%d', '%s']);
}

/**
 * Wer den Namen zuletzt geändert hat.
 *
 * Transparenz statt Sperre: jeder mit Bestellrecht darf umbenennen, aber
 * es steht dabei, wer es war. Das passt dazu, dass die Freigabe von
 * Bestellungen bewusst gestrichen wurde.
 */
function sz_name_geaendert_von(int $produkt_id): string
{
    $betrieb = sz_betrieb_id();
    if (!$betrieb) return '';

    global $wpdb;
    $tabelle = sz_namen_tabelle();

    $wer = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT geaendert_von FROM {$tabelle} WHERE betrieb_id = %d AND produkt_id = %d",
        $betrieb,
        $produkt_id
    ));

    if (!$wer) return '';
    $nutzer = get_userdata($wer);
    return $nutzer ? $nutzer->display_name : '';
}

/* ===================================================================
   Der Endpunkt
   =================================================================== */

add_action('wp_ajax_sz_name_setzen', function () {
    check_ajax_referer('sz_namen');

    if (!is_user_logged_in()) {
        wp_send_json_error(['meldung' => __('Bitte melden Sie sich an.', 'sapelza-shop')], 403);
    }

    $produkt = isset($_POST['produkt']) ? (int) $_POST['produkt'] : 0;
    $name    = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

    $artikel = wc_get_product($produkt);
    if (!$artikel instanceof WC_Product) {
        wp_send_json_error(['meldung' => __('Artikel nicht gefunden.', 'sapelza-shop')], 404);
    }

    sz_namen_setzen($produkt, $name);

    /* Der Merker in sz_eigene_namen() haelt nur diesen Aufruf lang —
       die Antwort liest deshalb frisch. */
    $eigen = trim($name);
    wp_send_json_success([
        'eigen'   => $eigen,
        'katalog' => $artikel->get_name(),
        'wer'     => wp_get_current_user()->display_name,
    ]);
});

/**
 * Das Skript, nur wo die Liste auch steht.
 */
add_action('wp_enqueue_scripts', function () {
    if (!is_singular()) return;
    $inhalt = get_post_field('post_content', get_queried_object_id());
    if (!is_string($inhalt) || !has_shortcode($inhalt, 'sz_meine_artikel')) return;

    $pfad = SZ_SHOP_PFAD . 'sapelza-shop.php';
    $f = '1.13.0';

    wp_enqueue_script('sapelza-namen', plugins_url('js/namen.js', $pfad), [], $f, true);
    wp_enqueue_script('sapelza-qr', plugins_url('js/qr.js', $pfad), [], $f, true);
    wp_enqueue_script('sapelza-etiketten', plugins_url('js/etiketten.js', $pfad), ['sapelza-qr'], $f, true);
}, 30);

/* ===================================================================
   Auslaufregel — Schritt 3
   =================================================================== */

/**
 * Der Nachfolger eines ausgelaufenen Artikels.
 *
 * Gedruckte Etiketten überleben den Katalog. Wenn ein Artikel ausläuft,
 * klebt der Aufkleber noch zwei Jahre am Regal. Der QR darf dann nicht auf
 * eine Fehlerseite führen, sondern muss sagen, was an seiner Stelle steht.
 * Das ist der Unterschied zwischen einem Werkzeug und einem Ärgernis.
 *
 * Hinterlegt wird die Artikelnummer des Nachfolgers am alten Produkt.
 */
function sz_nachfolger(WC_Product $artikel): ?WC_Product
{
    $nummer = trim((string) $artikel->get_meta('_sz_nachfolger'));
    if ($nummer === '') return null;

    $id = wc_get_product_id_by_sku($nummer);
    if (!$id) return null;

    $neu = wc_get_product($id);
    return $neu instanceof WC_Product ? $neu : null;
}

/**
 * Das Feld dafür in der Produktverwaltung.
 *
 * Bewusst im Reiter „Versand" nicht, sondern bei den allgemeinen Daten —
 * es gehört zum Artikel, nicht zum Versand.
 */
add_action('woocommerce_product_options_inventory_product_data', function () {
    woocommerce_wp_text_input([
        'id'          => '_sz_nachfolger',
        'label'       => __('Nachfolger (Art.-Nr.)', 'sapelza-shop'),
        'description' => __('Läuft dieser Artikel aus: die Artikelnummer des Nachfolgers. Wer ein altes Regaletikett scannt, wird dann dorthin geführt statt auf eine Fehlerseite.', 'sapelza-shop'),
        'desc_tip'    => true,
    ]);
});

add_action('woocommerce_process_product_meta', function ($post_id) {
    $wert = isset($_POST['_sz_nachfolger'])
        ? sanitize_text_field(wp_unslash($_POST['_sz_nachfolger']))
        : '';

    $artikel = wc_get_product($post_id);
    if (!$artikel instanceof WC_Product) return;

    $artikel->update_meta_data('_sz_nachfolger', $wert);
    $artikel->save();
});
