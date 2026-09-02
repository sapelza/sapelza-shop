<?php
/**
 * Die Rechtsseiten.
 *
 * Der Shop verkauft seit Monaten ohne Impressum und ohne
 * Datenschutzerklärung. Beides ist keine Formsache: wer in Italien
 * gewerblich verkauft, muss Anbieterkennzeichnung und
 * Datenschutzinformation vorhalten, und im Fuß muss man sie finden.
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
 * ohne Plugin zurecht (siehe sz_fuss_recht() in footer.php).
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
            'pflicht'   => false,
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
   Die Gerüsttexte
   ===================================================================

   Bewusst mit sichtbaren Lücken in eckigen Klammern. Wer den Entwurf
   öffnet, sieht sofort, was noch fehlt — und niemand hält ihn für
   fertig. Der Kasten oben sagt dasselbe noch einmal in Worten.
   =================================================================== */

/**
 * Der Hinweis, der über jedem Entwurf steht.
 */
function sz_recht_hinweis(): string
{
    return '<!-- wp:paragraph --><p><strong>' . esc_html__('Entwurf — noch nicht veröffentlichen.', 'sapelza-shop')
         . '</strong> ' . esc_html__('Dieser Text ist ein Gerüst. Die Angaben in eckigen Klammern fehlen und müssen ergänzt werden; ob der Inhalt für Ihren Betrieb vollständig und richtig ist, muss jemand beurteilen, der die Rechtslage kennt — Steuerberater, Wirtschaftsverband oder Anwalt. Diesen Absatz danach löschen.', 'sapelza-shop')
         . '</p><!-- /wp:paragraph -->';
}

/**
 * Impressum — Anbieterkennzeichnung nach italienischem Recht.
 *
 * Die Pflichtangaben eines italienischen Unternehmens unterscheiden sich
 * von den deutschen: neben Anschrift und MwSt-Nummer gehören die
 * Eintragung im Registro Imprese und die REA-Nummer der Handelskammer
 * dazu, bei Kapitalgesellschaften außerdem das Gesellschaftskapital.
 *
 * Was ich nicht hineinschreibe: die Zahlen. Eine erfundene REA-Nummer
 * wäre schlimmer als eine offene Lücke.
 */
function sz_recht_text_impressum(): string
{
    $absatz = static fn(string $t): string => '<!-- wp:paragraph --><p>' . $t . '</p><!-- /wp:paragraph -->';
    $titel  = static fn(string $t): string => '<!-- wp:heading {"level":2} --><h2>' . esc_html($t) . '</h2><!-- /wp:heading -->';

    return sz_recht_hinweis()

        . $titel(__('Anbieter', 'sapelza-shop'))
        . $absatz(
            '[' . esc_html__('Firmenbezeichnung laut Handelsregister', 'sapelza-shop') . ']<br>'
            . '[' . esc_html__('Rechtsform, z. B. Einzelunternehmen / OHG / GmbH', 'sapelza-shop') . ']<br>'
            . '[' . esc_html__('Straße und Hausnummer', 'sapelza-shop') . ']<br>'
            . esc_html__('39034 Toblach (BZ), Italien', 'sapelza-shop')
        )

        . $titel(__('Kontakt', 'sapelza-shop'))
        . $absatz(
            esc_html__('Telefon: +39 0474 972205', 'sapelza-shop') . '<br>'
            . esc_html__('E-Mail: info@sapelza.it', 'sapelza-shop') . '<br>'
            . '[' . esc_html__('PEC-Adresse, falls vorhanden', 'sapelza-shop') . ']'
        )

        . $titel(__('Steuer- und Registerangaben', 'sapelza-shop'))
        . $absatz(
            esc_html__('MwSt.-Nummer (Partita IVA):', 'sapelza-shop') . ' [IT…]<br>'
            . esc_html__('Steuernummer (Codice fiscale):', 'sapelza-shop') . ' […]<br>'
            . esc_html__('Eintragung im Handelsregister Bozen (Registro Imprese):', 'sapelza-shop') . ' […]<br>'
            . esc_html__('REA-Nummer der Handelskammer Bozen:', 'sapelza-shop') . ' [BZ-…]<br>'
            . '[' . esc_html__('Gesellschaftskapital — nur bei Kapitalgesellschaften', 'sapelza-shop') . ']'
        )

        . $titel(__('Vertretungsberechtigt', 'sapelza-shop'))
        . $absatz('[' . esc_html__('Vor- und Nachname', 'sapelza-shop') . ']')

        . $titel(__('Verantwortlich für den Inhalt', 'sapelza-shop'))
        . $absatz(
            '[' . esc_html__('Vor- und Nachname', 'sapelza-shop') . ']<br>'
            . '[' . esc_html__('Anschrift, falls abweichend', 'sapelza-shop') . ']'
        )

        . $titel(__('Streitbeilegung', 'sapelza-shop'))
        . $absatz(
            '[' . esc_html__('Mit Ihrem Berater klären: Welcher Hinweis gilt für Ihren Betrieb? Wenn Sie ausschließlich an Unternehmen mit MwSt.-Nummer verkaufen, gelten die Verbraucher-Regeln nicht — dann gehört hier ein anderer oder gar kein Text hin.', 'sapelza-shop') . ']'
        );
}

/**
 * Datenschutzerklärung — bewusst nur ein Gerüst.
 *
 * WordPress bringt selbst einen ausführlichen Entwurf mit: unter
 * Einstellungen → Datenschutz legt es eine Seite an, in die auch
 * WooCommerce seine Abschnitte einträgt (Konto, Bestellungen, Zahlung).
 * Dieser Text hier nennt deshalb vor allem, was an diesem Shop besonders
 * ist, und verweist auf jenen Entwurf.
 */
function sz_recht_text_datenschutz(): string
{
    $absatz = static fn(string $t): string => '<!-- wp:paragraph --><p>' . $t . '</p><!-- /wp:paragraph -->';
    $titel  = static fn(string $t): string => '<!-- wp:heading {"level":2} --><h2>' . esc_html($t) . '</h2><!-- /wp:heading -->';

    return sz_recht_hinweis()

        . $absatz(
            '<em>' . esc_html__('Hinweis für Sie, nicht für Besucher: WordPress hält unter Einstellungen → Datenschutz einen ausführlichen Entwurf bereit, in den auch WooCommerce seine Abschnitte zu Konto, Bestellungen und Zahlung einträgt. Führen Sie beide zusammen und löschen Sie diesen Absatz.', 'sapelza-shop') . '</em>'
        )

        . $titel(__('Verantwortlicher', 'sapelza-shop'))
        . $absatz(
            '[' . esc_html__('Firmenbezeichnung, Anschrift, MwSt.-Nummer — wie im Impressum', 'sapelza-shop') . ']<br>'
            . esc_html__('E-Mail: info@sapelza.it', 'sapelza-shop')
        )

        . $titel(__('Hosting', 'sapelza-shop'))
        . $absatz(
            '[' . esc_html__('Diese Seite läuft bei Raidboxes. Name, Anschrift und Auftragsverarbeitungsvertrag des Anbieters eintragen — der Vertrag muss vorliegen.', 'sapelza-shop') . ']'
        )

        . $titel(__('Kundenkonto und Bestellungen', 'sapelza-shop'))
        . $absatz(
            esc_html__('Für ein Geschäftskonto werden Firmenname, Anschrift, MwSt.-Nummer, Ansprechpartner und Kontaktdaten gespeichert. Bestellungen werden mit Artikeln, Mengen, Preisen und dem gewählten Liefertag aufbewahrt.', 'sapelza-shop') . ' '
            . '[' . esc_html__('Aufbewahrungsfristen ergänzen — steuerrechtlich in Italien in der Regel zehn Jahre; bitte bestätigen lassen.', 'sapelza-shop') . ']'
        )

        . $titel(__('Zahlung und Versand', 'sapelza-shop'))
        . $absatz('[' . esc_html__('Welche Zahlungsarten gibt es, und welche Daten gehen dabei an wen? Ausliefern tun Sie selbst — das ist ein Vorteil und gehört hierher.', 'sapelza-shop') . ']')

        . $titel(__('Cookies', 'sapelza-shop'))
        . $absatz(
            esc_html__('Für Anmeldung und Warenkorb sind Cookies technisch nötig.', 'sapelza-shop') . ' '
            . '[' . esc_html__('Kommen weitere hinzu — Statistik, Karten, eingebettete Videos —, gehören sie hierher, und dann braucht es eine Einwilligung.', 'sapelza-shop') . ']'
        )

        . $titel(__('Ihre Rechte', 'sapelza-shop'))
        . $absatz(
            esc_html__('Auskunft, Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit und Widerspruch — zu richten an die oben genannte Anschrift.', 'sapelza-shop') . ' '
            . '[' . esc_html__('Zuständige Aufsichtsbehörde in Italien ist der Garante per la protezione dei dati personali; Anschrift ergänzen.', 'sapelza-shop') . ']'
        );
}

/**
 * AGB und Lieferbedingungen — die Fragen, nicht die Antworten.
 *
 * Hier ein Muster einzusetzen wäre der Punkt, an dem ein Gerüst
 * gefährlich wird: die Bedingungen eines Hauses sind das, was es
 * tatsächlich zusagt. Also stehen hier die Fragen, die zu beantworten
 * sind.
 */
function sz_recht_text_bedingungen(string $was): string
{
    $absatz = static fn(string $t): string => '<!-- wp:paragraph --><p>' . $t . '</p><!-- /wp:paragraph -->';
    $liste  = static function (array $punkte): string {
        $li = '';
        foreach ($punkte as $p) $li .= '<li>' . esc_html($p) . '</li>';
        return '<!-- wp:list --><ul>' . $li . '</ul><!-- /wp:list -->';
    };

    if ($was === 'versand') {
        return sz_recht_hinweis()
            . $absatz(esc_html__('Zu beantworten:', 'sapelza-shop'))
            . $liste([
                __('In welchem Gebiet wird geliefert, und was gilt außerhalb?', 'sapelza-shop'),
                __('Ab welchem Bestellwert ist die Lieferung frei, und was kostet sie darunter?', 'sapelza-shop'),
                __('Bis wann muss bestellt sein, damit am gewählten Tag geliefert wird?', 'sapelza-shop'),
                __('Was geschieht, wenn beim Liefern niemand da ist?', 'sapelza-shop'),
                __('Welche Zahlungsarten gibt es, und mit welchem Zahlungsziel?', 'sapelza-shop'),
                __('Wie wird bei Transportschäden oder Fehlmengen verfahren?', 'sapelza-shop'),
            ]);
    }

    return sz_recht_hinweis()
        . $absatz(esc_html__('Zu beantworten — und zwar zuerst die Grundfrage, weil alles Weitere daran hängt:', 'sapelza-shop'))
        . $liste([
            __('Verkaufen Sie ausschließlich an Unternehmen mit MwSt.-Nummer, oder können auch Privatpersonen bestellen? Bei Privatpersonen gelten Widerrufsrecht und die Informationspflichten für Verbraucher — bei reinem B2B nicht.', 'sapelza-shop'),
            __('Wann kommt der Vertrag zustande: mit der Bestellung oder mit Ihrer Bestätigung?', 'sapelza-shop'),
            __('Was gilt, wenn ein Artikel nicht lieferbar ist?', 'sapelza-shop'),
            __('Sind die Preise netto oder brutto, und wann sind sie fällig?', 'sapelza-shop'),
            __('Eigentumsvorbehalt bis zur vollständigen Zahlung?', 'sapelza-shop'),
            __('Wie lange und in welcher Form werden Mängel gerügt?', 'sapelza-shop'),
            __('Gerichtsstand und anwendbares Recht.', 'sapelza-shop'),
        ]);
}

/**
 * Der Gerüsttext zu einer Seite.
 */
function sz_recht_text(string $schluessel): string
{
    switch ($schluessel) {
        case 'impressum':   return sz_recht_text_impressum();
        case 'datenschutz': return sz_recht_text_datenschutz();
        case 'versand':     return sz_recht_text_bedingungen('versand');
        default:            return sz_recht_text_bedingungen('agb');
    }
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
