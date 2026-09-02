<?php
/**
 * Die Gerüsttexte der Rechtsseiten.
 *
 * Sitz in Italien, Verkauf in Italien, an Betriebe **und** an
 * Privatkunden. Das zweite ist der Grund, warum hier so viel steht:
 * sobald jemand ohne MwSt.-Nummer bestellen kann — ein Bauer, ein
 * Privathaushalt —, gilt der Codice del Consumo, und der verlangt
 * Widerrufsbelehrung, Verbraucher-Gewährleistung und eine Reihe von
 * Pflichtangaben, die es im reinen Geschäftsverkehr nicht braucht.
 *
 * Was diese Texte sind
 * --------------------
 *
 * Entwürfe, die einem Anwalt vorgelegt werden. Sie sind so weit
 * ausgearbeitet, wie ich es verantworten kann, und an jeder Stelle, an
 * der ich die Norm kenne, ist sie genannt — nicht zur Zierde, sondern
 * damit die Prüfung schnell geht: wer die Fundstelle danebenstehen hat,
 * braucht sie nicht zu suchen.
 *
 * Was sie nicht sind
 * ------------------
 *
 * Rechtsberatung. Ich bin kein Anwalt, und an mehreren Stellen hängt
 * der richtige Text an Tatsachen, die ich nicht kenne — Rechtsform,
 * Registernummern, Zahlungsarten, Gerichtsstand. Die stehen als Lücke
 * in eckigen Klammern. Eine plausibel aussehende, aber erfundene
 * REA-Nummer wäre schlimmer als eine offene Lücke: die Lücke sieht man.
 *
 * Warum ohne __()
 * ---------------
 *
 * Diese Texte werden einmal in die Datenbank geschrieben und danach in
 * wp-admin bearbeitet. Eine Übersetzungsschicht wäre hier kein Gewinn,
 * sondern eine zweite Fassung, die niemand pflegt. Die Oberfläche der
 * Werkstattseite ist übersetzbar, der Seiteninhalt nicht.
 *
 * @package sapelza-shop
 */

if (!defined('ABSPATH')) exit;

/* ===================================================================
   Bausteine für Blockmarkup
   =================================================================== */

function sz_recht_p(string $t): string
{
    return '<!-- wp:paragraph --><p>' . $t . '</p><!-- /wp:paragraph -->';
}

function sz_recht_h(string $t, int $stufe = 2): string
{
    return '<!-- wp:heading {"level":' . $stufe . '} --><h' . $stufe . '>' . $t
         . '</h' . $stufe . '><!-- /wp:heading -->';
}

function sz_recht_ul(array $zeilen): string
{
    $li = '';
    foreach ($zeilen as $z) $li .= '<li>' . $z . '</li>';
    return '<!-- wp:list --><ul class="wp-block-list">' . $li . '</ul><!-- /wp:list -->';
}

/**
 * Ein Hinweiskasten für den Betreiber, der vor dem Veröffentlichen weg muss.
 */
function sz_recht_kasten(string $t): string
{
    return '<!-- wp:paragraph {"className":"sz-entwurfshinweis"} -->'
         . '<p class="sz-entwurfshinweis"><em>' . $t . '</em></p><!-- /wp:paragraph -->';
}

/**
 * Der Hinweis, der über jedem Entwurf steht.
 */
function sz_recht_hinweis(): string
{
    return sz_recht_kasten(
        '<strong>Entwurf — noch nicht veröffentlichen.</strong> Dieser Text ist ein Gerüst für die '
        . 'anwaltliche Prüfung. Die Angaben in eckigen Klammern fehlen und müssen ergänzt werden. '
        . 'Die genannten Rechtsnormen sind als Prüfhilfe gedacht und ersetzen die Prüfung nicht. '
        . 'Diesen Absatz vor dem Veröffentlichen löschen.'
    );
}

/**
 * Der Firmenblock, der in mehreren Texten gebraucht wird.
 */
function sz_recht_firma(): string
{
    return '[Firmenbezeichnung laut Handelsregister]<br>'
         . '[Rechtsform — Einzelunternehmen / SNC / SAS / SRL]<br>'
         . '[Straße und Hausnummer]<br>'
         . '39034 Toblach (BZ), Italien<br>'
         . 'Telefon: +39 0474 972205<br>'
         . 'E-Mail: info@sapelza.it';
}

/* ===================================================================
   Impressum
   ===================================================================

   Grundlage der Pflichtangaben im elektronischen Geschäftsverkehr ist
   Art. 7 D.lgs. 70/2003 (Umsetzung der E-Commerce-Richtlinie). Für
   eingetragene Gesellschaften kommen die Angaben nach Art. 2250 Codice
   Civile hinzu — Register, REA-Nummer, bei Kapitalgesellschaften das
   Gesellschaftskapital und ob es sich um eine Einpersonengesellschaft
   handelt.

   Ein Einzelunternehmen (ditta individuale) braucht kein
   Gesellschaftskapital anzugeben, die Registerdaten aber sehr wohl.
   Welche Zeilen bleiben, entscheidet die Rechtsform.
   =================================================================== */

function sz_recht_text_impressum(): string
{
    return sz_recht_hinweis()

        . sz_recht_h('Anbieter')
        . sz_recht_p(sz_recht_firma())

        . sz_recht_h('Steuer- und Registerangaben')
        . sz_recht_p(
            'MwSt.-Nummer (Partita IVA): [IT…]<br>'
            . 'Steuernummer (Codice fiscale): […]<br>'
            . 'Eingetragen im Handelsregister der Handelskammer Bozen (Registro delle Imprese di Bolzano) '
            . 'unter der Nummer […]<br>'
            . 'REA-Nummer: [BZ-…]<br>'
            . '[Nur bei Kapitalgesellschaften: Gesellschaftskapital € […], vollständig eingezahlt. '
            . 'Bei einer Einpersonengesellschaft ist das anzugeben.]'
        )
        . sz_recht_kasten(
            'Prüfhilfe: Art. 7 D.lgs. 70/2003 für die Angaben im elektronischen Geschäftsverkehr, '
            . 'Art. 2250 Codice Civile für Register, REA und Gesellschaftskapital. Bei einem '
            . 'Einzelunternehmen entfallen die Zeilen zum Kapital.'
        )

        . sz_recht_h('Vertretungsberechtigt')
        . sz_recht_p('[Vor- und Nachname]')

        . sz_recht_h('Verantwortlich für den Inhalt')
        . sz_recht_p('[Vor- und Nachname]<br>[Anschrift, falls abweichend]')

        . sz_recht_h('PEC')
        . sz_recht_p('[Zertifizierte E-Mail-Adresse, falls vorhanden — für eingetragene Unternehmen in Italien Pflicht]')

        . sz_recht_h('Berufsrechtliche Angaben')
        . sz_recht_p(
            '[Nur ausfüllen, falls für einzelne Warengruppen eine Zulassung oder Aufsicht besteht — '
            . 'etwa bei Lebensmitteln, Bioziden oder Gefahrstoffen. Sonst diesen Abschnitt löschen.]'
        )

        . sz_recht_h('Streitbeilegung')
        . sz_recht_p(
            'Für Streitigkeiten mit Verbrauchern stehen die Schlichtungsstellen nach Art. 141 ff. '
            . 'Codice del Consumo zur Verfügung, unter anderem die Schlichtungsstelle der Handelskammer '
            . 'Bozen. [Angeben, ob und bei welcher Stelle Sie zur Teilnahme bereit oder verpflichtet sind.]'
        )
        . sz_recht_kasten(
            'Prüfhilfe: Die europäische ODR-Plattform wurde 2025 eingestellt. Den früher üblichen Link '
            . 'dorthin bitte nicht aufnehmen — ein Verweis auf eine abgeschaltete Stelle ist schlechter '
            . 'als keiner. Ob stattdessen ein Hinweis auf eine nationale Stelle verpflichtend ist, bitte '
            . 'prüfen lassen.'
        )

        . sz_recht_h('Bildnachweis')
        . sz_recht_p('[Falls Fotos von Dritten verwendet werden, hier die Nachweise. Sonst löschen.]');
}

/* ===================================================================
   Datenschutzerklärung
   ===================================================================

   DSGVO in Verbindung mit dem italienischen Codice Privacy
   (D.lgs. 196/2003 in der Fassung des D.lgs. 101/2018).

   Zwei Besonderheiten dieses Shops gehören ausdrücklich hinein: die
   elektronische Rechnung über das SdI der Agenzia delle Entrate, die
   in Italien Pflicht ist und Daten an eine staatliche Stelle
   weitergibt, und die Tatsache, dass hier selbst ausgeliefert wird —
   es geht also gerade keine Adresse an einen Paketdienst.
   =================================================================== */

function sz_recht_text_datenschutz(): string
{
    return sz_recht_hinweis()

        . sz_recht_kasten(
            'Hinweis für Sie, nicht für Besucher: WordPress hält unter Einstellungen → Datenschutz einen '
            . 'eigenen Entwurf bereit, in den auch WooCommerce und andere Plugins ihre Abschnitte '
            . 'eintragen. Bitte vergleichen und alles übernehmen, was dort an Diensten genannt ist und '
            . 'hier fehlt. Diesen Absatz danach löschen.'
        )

        . sz_recht_h('Verantwortlicher')
        . sz_recht_p(
            'Verantwortlich für die Verarbeitung Ihrer Daten im Sinne von Art. 4 Nr. 7 DSGVO ist:<br><br>'
            . sz_recht_firma()
        )
        . sz_recht_p('[Falls ein Datenschutzbeauftragter bestellt ist: Name und Kontaktdaten. Sonst diesen Satz löschen.]')

        . sz_recht_h('Wofür wir Daten verarbeiten')

        . sz_recht_h('Aufruf der Seite', 3)
        . sz_recht_p(
            'Beim Aufruf werden vom Server technische Daten festgehalten: IP-Adresse, Zeitpunkt, '
            . 'aufgerufene Adresse, übertragene Datenmenge, Browser und Betriebssystem. Das ist nötig, '
            . 'um die Seite auszuliefern und Angriffe abzuwehren. Rechtsgrundlage ist unser berechtigtes '
            . 'Interesse an einem sicheren Betrieb (Art. 6 Abs. 1 lit. f DSGVO). '
            . '[Speicherdauer der Serverprotokolle beim Hoster erfragen und hier eintragen.]'
        )

        . sz_recht_h('Kundenkonto', 3)
        . sz_recht_p(
            'Für ein Konto speichern wir Firmenname oder Namen, Anschrift, Kontaktdaten, gegebenenfalls '
            . 'MwSt.-Nummer und Steuernummer sowie die von Ihnen angelegten Artikellisten und Favoriten. '
            . 'Rechtsgrundlage ist die Erfüllung des Vertrags und die Durchführung vorvertraglicher '
            . 'Maßnahmen (Art. 6 Abs. 1 lit. b DSGVO). Sie können das Konto jederzeit löschen lassen; '
            . 'davon unberührt bleiben Unterlagen, die wir gesetzlich aufbewahren müssen.'
        )

        . sz_recht_h('Bestellung und Lieferung', 3)
        . sz_recht_p(
            'Zur Ausführung einer Bestellung verarbeiten wir die bestellten Artikel, Mengen, Preise, die '
            . 'Lieferanschrift und den von Ihnen gewählten Liefertag. Rechtsgrundlage ist Art. 6 Abs. 1 '
            . 'lit. b DSGVO. Wir liefern selbst aus; Ihre Anschrift wird deshalb nicht an einen '
            . 'Paketdienst weitergegeben.'
        )

        . sz_recht_h('Rechnung, Buchhaltung und elektronische Rechnung', 3)
        . sz_recht_p(
            'Rechnungsdaten verarbeiten wir zur Erfüllung unserer steuer- und handelsrechtlichen '
            . 'Pflichten (Art. 6 Abs. 1 lit. c DSGVO). In Italien werden Rechnungen über das '
            . 'Austauschsystem der Agenzia delle Entrate (Sistema di Interscambio) übermittelt; dabei '
            . 'gelangen die Rechnungsdaten an diese Behörde und an Ihren Steuerberater oder '
            . 'Rechnungsvermittler. Buchhaltungsunterlagen bewahren wir zehn Jahre auf '
            . '(Art. 2220 Codice Civile).'
        )

        . sz_recht_h('Zahlung', 3)
        . sz_recht_p(
            '[Welche Zahlungsarten gibt es? Bei Überweisung: Bankverbindung und Bank nennen. Bei '
            . 'Kartenzahlung oder Zahlungsdienstleistern: Name und Anschrift des Anbieters, welche Daten '
            . 'dorthin gelangen und wo dessen Datenschutzerklärung steht. Bei Zahlung bei Lieferung: '
            . 'auch das gehört hierher.]'
        )

        . sz_recht_h('Anfragen', 3)
        . sz_recht_p(
            'Wenn Sie uns schreiben oder anrufen, verarbeiten wir Ihre Angaben, um die Anfrage zu '
            . 'beantworten — je nach Anlass auf Grundlage von Art. 6 Abs. 1 lit. b oder lit. f DSGVO.'
        )

        . sz_recht_h('Cookies')
        . sz_recht_p(
            'Für Anmeldung, Warenkorb und die Wahl zwischen hellem und dunklem Erscheinungsbild sind '
            . 'Cookies technisch notwendig. Sie werden ohne Einwilligung gesetzt, weil ohne sie der Shop '
            . 'nicht benutzbar wäre.'
        )
        . sz_recht_p(
            '[Kommen weitere hinzu — Statistik, Karten, eingebettete Videos, Werbenetzwerke —, gehören '
            . 'sie einzeln hierher, mit Anbieter, Zweck und Laufzeit. Für sie braucht es vorher eine '
            . 'Einwilligung und einen Cookie-Banner, der auch ein Ablehnen zulässt.]'
        )
        . sz_recht_kasten(
            'Prüfhilfe: Leitlinien des Garante zu Cookies vom 10. Juni 2021. Technisch notwendige '
            . 'Cookies sind einwilligungsfrei, alles andere nicht — auch nicht Statistik-Cookies, wenn '
            . 'sie nicht anonymisiert sind.'
        )

        . sz_recht_h('Wer Ihre Daten außerdem erhält')
        . sz_recht_ul([
            'Unser Hoster: [Name und Anschrift des Anbieters — diese Seite läuft bei Raidboxes]. '
            . 'Mit ihm besteht ein Auftragsverarbeitungsvertrag nach Art. 28 DSGVO. '
            . '[Vertrag prüfen, dass er vorliegt.]',
            'Unser Steuerberater sowie die Agenzia delle Entrate im Rahmen der elektronischen Rechnung.',
            '[Weitere Dienstleister — Wartung, Zahlungsanbieter, Newsletter-Versand —, jeweils mit Name, '
            . 'Anschrift und Zweck.]',
        ])
        . sz_recht_p(
            'Eine Übermittlung in Länder außerhalb der Europäischen Union findet '
            . '[nicht statt / nur an folgende Empfänger statt: …] .'
        )

        . sz_recht_h('Wie lange wir Daten aufbewahren')
        . sz_recht_ul([
            'Kontodaten: solange das Konto besteht.',
            'Bestell- und Rechnungsdaten: zehn Jahre nach Art. 2220 Codice Civile.',
            'Serverprotokolle: [Dauer beim Hoster erfragen].',
            'Anfragen ohne Vertragsbezug: [Dauer festlegen, üblich sind zwei Jahre].',
        ])

        . sz_recht_h('Ihre Rechte')
        . sz_recht_p(
            'Sie haben das Recht auf Auskunft (Art. 15 DSGVO), Berichtigung (Art. 16), Löschung '
            . '(Art. 17), Einschränkung der Verarbeitung (Art. 18), Datenübertragbarkeit (Art. 20) und '
            . 'Widerspruch gegen Verarbeitungen, die auf einem berechtigten Interesse beruhen (Art. 21). '
            . 'Eine erteilte Einwilligung können Sie jederzeit für die Zukunft widerrufen. Wenden Sie '
            . 'sich dafür an die oben genannte Anschrift oder an info@sapelza.it.'
        )
        . sz_recht_p(
            'Wenn Sie meinen, dass wir Ihre Daten nicht rechtmäßig verarbeiten, können Sie sich bei der '
            . 'Aufsichtsbehörde beschweren:<br><br>'
            . 'Garante per la protezione dei dati personali<br>'
            . 'Piazza Venezia 11, 00187 Roma<br>'
            . 'www.garanteprivacy.it'
        )

        . sz_recht_h('Keine automatisierte Entscheidungsfindung')
        . sz_recht_p(
            'Wir treffen keine Entscheidungen, die allein auf einer automatisierten Verarbeitung beruhen '
            . 'und Ihnen gegenüber rechtliche Wirkung entfalten (Art. 22 DSGVO).'
        )

        . sz_recht_h('Änderungen')
        . sz_recht_p('Stand dieser Erklärung: [Datum]. Wir passen sie an, wenn sich die Verarbeitung ändert.');
}

/* ===================================================================
   AGB
   ===================================================================

   Der Shop verkauft an Betriebe und an Privatpersonen. Deshalb muss
   der Text an mehreren Stellen unterscheiden: Gewährleistung,
   Gefahrübergang und Widerruf laufen für Verbraucher nach dem Codice
   del Consumo, für Unternehmer nach dem Codice Civile.

   Die Abschnitte sind deshalb bewusst getrennt beschriftet. Das liest
   sich sperriger als ein Text für alle — aber ein Text für alle wäre
   für die eine Hälfte der Kunden falsch.
   =================================================================== */

function sz_recht_text_agb(): string
{
    return sz_recht_hinweis()

        . sz_recht_h('§ 1 Geltungsbereich und Begriffe')
        . sz_recht_p(
            'Diese Bedingungen gelten für alle Bestellungen, die über sapelzashop.com bei '
            . '[Firmenbezeichnung] geschlossen werden.'
        )
        . sz_recht_p(
            '<strong>Verbraucher</strong> ist, wer bestellt, ohne dabei gewerblich oder beruflich zu '
            . 'handeln (Art. 3 Abs. 1 lit. a Codice del Consumo). <strong>Unternehmer</strong> ist, wer '
            . 'in Ausübung seiner gewerblichen, handwerklichen, land- oder forstwirtschaftlichen oder '
            . 'beruflichen Tätigkeit bestellt.'
        )
        . sz_recht_p(
            'Wir beliefern beide. Wo dieser Text zwischen Verbrauchern und Unternehmern unterscheidet, '
            . 'ist das eigens gekennzeichnet.'
        )
        . sz_recht_kasten(
            'Prüfhilfe: Ein Landwirt bestellt in aller Regel als Unternehmer, auch wenn er keine '
            . 'MwSt.-Nummer angibt — maßgeblich ist der Zweck, nicht die Steuernummer. Bitte prüfen '
            . 'lassen, ob und wie im Bestellvorgang danach gefragt werden sollte, damit die Zuordnung '
            . 'nachweisbar ist.'
        )

        . sz_recht_h('§ 2 Vertragsschluss')
        . sz_recht_p(
            'Die Darstellung der Waren im Shop ist kein bindendes Angebot, sondern eine Aufforderung, '
            . 'eine Bestellung abzugeben. Mit dem Absenden der Bestellung geben Sie ein verbindliches '
            . 'Angebot ab. Wir bestätigen den Eingang unverzüglich; diese Bestätigung ist noch keine '
            . 'Annahme. Der Vertrag kommt zustande, wenn wir die Annahme erklären oder die Ware '
            . 'ausliefern.'
        )
        . sz_recht_p('Die Vertragssprache ist Deutsch. [Falls auch italienisch bestellt werden kann, hier ergänzen.]')

        . sz_recht_h('§ 3 Preise')
        . sz_recht_p(
            '[Diesen Abschnitt an die tatsächliche Anzeige anpassen.] Gegenüber Unternehmern verstehen '
            . 'sich die Preise als Nettopreise zuzüglich der gesetzlichen Mehrwertsteuer. Gegenüber '
            . 'Verbrauchern sind die Preise Endpreise einschließlich Mehrwertsteuer.'
        )
        . sz_recht_kasten(
            'Prüfhilfe: Gegenüber Verbrauchern müssen Endpreise einschließlich Steuern und aller '
            . 'Preisbestandteile angegeben werden (Art. 49 Codice del Consumo). Der Shop zeigt Preise '
            . 'derzeit erst nach der Anmeldung — bitte prüfen lassen, ob das für Verbraucher zulässig '
            . 'ist und was vor der Anmeldung sichtbar sein muss.'
        )
        . sz_recht_p('Über Liefer- und Zahlungskosten informiert die Seite [Lieferung und Zahlung].')

        . sz_recht_h('§ 4 Zahlung')
        . sz_recht_p('[Welche Zahlungsarten gibt es, und welche stehen wem offen?]')
        . sz_recht_p(
            '[Zahlungsziel angeben.] Gerät ein Unternehmer in Verzug, gelten die Zinsen nach '
            . 'D.lgs. 231/2002 über Zahlungsverzug im Geschäftsverkehr. Gegenüber Verbrauchern gelten '
            . 'die gesetzlichen Verzugszinsen nach Art. 1284 Codice Civile.'
        )

        . sz_recht_h('§ 5 Lieferung')
        . sz_recht_p(
            'Wir liefern selbst aus, im Hochpustertal von Welsberg bis Winnebach sowie in den Seitentälern '
            . 'Gsies, Prags und Sexten. Außerhalb dieses Gebiets liefern wir nicht. Den Liefertag wählen '
            . 'Sie beim Bestellen aus den angebotenen Terminen.'
        )
        . sz_recht_p('[Bestellschluss, Mindestbestellwert und Lieferkosten hier oder auf der Seite „Lieferung und Zahlung" nennen.]')
        . sz_recht_p(
            '<strong>Gefahrübergang.</strong> Gegenüber Verbrauchern geht die Gefahr des Untergangs erst '
            . 'über, wenn die Ware Ihnen oder einer von Ihnen benannten Person übergeben wird '
            . '(Art. 63 Codice del Consumo). Gegenüber Unternehmern geht die Gefahr mit der Übergabe '
            . 'über.'
        )

        . sz_recht_h('§ 6 Eigentumsvorbehalt')
        . sz_recht_p(
            'Die Ware bleibt bis zur vollständigen Bezahlung unser Eigentum. '
            . '[Bitte prüfen lassen, ob der Eigentumsvorbehalt gegenüber Unternehmern in der '
            . 'gewünschten Form nach Art. 1523 ff. Codice Civile wirksam vereinbart ist und ob es dafür '
            . 'einer schriftlichen Vereinbarung mit sicherem Datum bedarf.]'
        )

        . sz_recht_h('§ 7 Gewährleistung gegenüber Verbrauchern')
        . sz_recht_p(
            'Für Verbraucher gilt die gesetzliche Konformitätsgarantie nach Art. 128 ff. Codice del '
            . 'Consumo. Zeigt die Ware innerhalb von zwei Jahren ab Lieferung einen Mangel, haben Sie '
            . 'Anspruch auf Nachbesserung oder Ersatz und, wenn das nicht gelingt, auf Minderung oder '
            . 'Rücktritt. Die Rechte verjähren nach den gesetzlichen Fristen.'
        )
        . sz_recht_kasten(
            'Prüfhilfe: Art. 128 ff. Codice del Consumo in der Fassung des D.lgs. 170/2021. Die früher '
            . 'geltende Pflicht, den Mangel binnen zwei Monaten anzuzeigen, ist für Verträge ab dem '
            . '1. Jänner 2022 entfallen — bitte bestätigen lassen und keinesfalls eine solche Frist in '
            . 'die Bedingungen schreiben.'
        )

        . sz_recht_h('§ 8 Gewährleistung gegenüber Unternehmern')
        . sz_recht_p(
            'Unternehmer haben die Ware bei Erhalt zu prüfen. Offene Mängel sind binnen acht Tagen ab '
            . 'Empfang, verborgene binnen acht Tagen ab Entdeckung schriftlich anzuzeigen; sonst gilt '
            . 'die Ware als angenommen (Art. 1495 Codice Civile). Die Ansprüche verjähren in einem Jahr '
            . 'ab Lieferung.'
        )

        . sz_recht_h('§ 9 Widerrufsrecht für Verbraucher')
        . sz_recht_p(
            'Verbraucher können den Vertrag binnen vierzehn Tagen ohne Angabe von Gründen widerrufen. '
            . 'Die Einzelheiten und die Ausnahmen stehen in der [Widerrufsbelehrung]. Unternehmern steht '
            . 'ein Widerrufsrecht nicht zu.'
        )

        . sz_recht_h('§ 10 Haftung')
        . sz_recht_p(
            '[Diesen Abschnitt vom Anwalt formulieren lassen. Eine Haftungsbeschränkung, die gegenüber '
            . 'Unternehmern wirksam wäre, ist gegenüber Verbrauchern in weiten Teilen unwirksam '
            . '(Art. 33 ff. Codice del Consumo, missbräuchliche Klauseln). Ein Standardtext, der beides '
            . 'zugleich versucht, wird meist für beide unbrauchbar.]'
        )

        . sz_recht_h('§ 11 Streitbeilegung')
        . sz_recht_p(
            'Für Streitigkeiten mit Verbrauchern stehen die Schlichtungsstellen nach Art. 141 ff. '
            . 'Codice del Consumo zur Verfügung, unter anderem bei der Handelskammer Bozen. '
            . '[Angeben, ob Sie zur Teilnahme bereit oder verpflichtet sind.]'
        )

        . sz_recht_h('§ 12 Anwendbares Recht und Gerichtsstand')
        . sz_recht_p(
            'Es gilt italienisches Recht. Gegenüber Verbrauchern ist das Gericht am Wohnsitz oder '
            . 'gewöhnlichen Aufenthalt des Verbrauchers zuständig; diese Zuständigkeit kann nicht zu '
            . 'seinem Nachteil abbedungen werden (Art. 33 Abs. 2 lit. u Codice del Consumo). Gegenüber '
            . 'Unternehmern ist Gerichtsstand [Bozen — bitte bestätigen lassen].'
        )

        . sz_recht_h('§ 13 Schlussbestimmungen')
        . sz_recht_p(
            'Sollte eine Bestimmung unwirksam sein, bleibt der Vertrag im Übrigen wirksam. '
            . 'Stand dieser Bedingungen: [Datum].'
        );
}

/* ===================================================================
   Widerrufsbelehrung
   ===================================================================

   Art. 52 ff. Codice del Consumo. Die Ausnahmen in Art. 59 sind für
   diesen Shop nicht Beiwerk: versiegelte Hygieneartikel und schnell
   verderbliche Ware gehoeren zum Sortiment.

   Wer nicht ordnungsgemaess belehrt, dem laeuft die Frist nicht 14
   Tage, sondern zwoelf Monate und 14 Tage. Deshalb ist gerade dieser
   Text einer, den ein Anwalt Wort fuer Wort ansehen sollte.
   =================================================================== */

function sz_recht_text_widerruf(): string
{
    return sz_recht_hinweis()

        . sz_recht_kasten(
            'Prüfhilfe: Art. 52 ff. Codice del Consumo, Ausnahmen in Art. 59, Musterbelehrung und '
            . 'Musterformular in Anlage I Teil A und B. Eine unvollständige Belehrung verlängert die '
            . 'Widerrufsfrist auf zwölf Monate und vierzehn Tage (Art. 53) — bitte Wort für Wort prüfen '
            . 'lassen.'
        )

        . sz_recht_h('Widerrufsrecht')
        . sz_recht_p(
            'Wenn Sie Verbraucher sind, können Sie diesen Vertrag binnen vierzehn Tagen ohne Angabe von '
            . 'Gründen widerrufen. Die Frist beträgt vierzehn Tage ab dem Tag, an dem Sie oder ein von '
            . 'Ihnen benannter Dritter, der nicht der Beförderer ist, die Ware in Besitz genommen haben. '
            . 'Bei mehreren Waren aus einer Bestellung, die getrennt geliefert werden, beginnt die Frist '
            . 'mit der letzten Ware.'
        )
        . sz_recht_p(
            'Um das Widerrufsrecht auszuüben, müssen Sie uns mittels einer eindeutigen Erklärung über '
            . 'Ihren Entschluss unterrichten:<br><br>' . sz_recht_firma() . '<br><br>'
            . 'Sie können dafür das beigefügte Musterformular verwenden, das ist aber nicht '
            . 'vorgeschrieben. Zur Wahrung der Frist genügt es, dass Sie die Mitteilung vor Ablauf der '
            . 'Frist absenden.'
        )

        . sz_recht_h('Folgen des Widerrufs')
        . sz_recht_p(
            'Wenn Sie widerrufen, erstatten wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, '
            . 'einschließlich der Lieferkosten — mit Ausnahme der zusätzlichen Kosten, die sich daraus '
            . 'ergeben, dass Sie eine andere Art der Lieferung als die von uns angebotene günstigste '
            . 'gewählt haben. Wir zahlen unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag '
            . 'zurück, an dem Ihre Mitteilung bei uns eingegangen ist. Wir verwenden dasselbe '
            . 'Zahlungsmittel, das Sie eingesetzt haben, sofern nichts anderes vereinbart ist; Entgelte '
            . 'entstehen Ihnen dadurch nicht.'
        )
        . sz_recht_p(
            'Wir können die Rückzahlung verweigern, bis wir die Ware zurückerhalten haben oder Sie den '
            . 'Nachweis der Rücksendung erbracht haben — je nachdem, was früher eintritt.'
        )
        . sz_recht_p(
            'Sie haben die Ware unverzüglich und spätestens binnen vierzehn Tagen ab der Mitteilung '
            . 'zurückzusenden oder zu übergeben. [Wer trägt die Rücksendekosten? Sollen die Kosten beim '
            . 'Kunden liegen, muss das hier ausdrücklich stehen — sonst tragen wir sie '
            . '(Art. 57 Codice del Consumo). Da wir im Tal selbst zustellen, kommt auch eine Abholung '
            . 'in Betracht; dann gehört das hierher.]'
        )
        . sz_recht_p(
            'Für einen Wertverlust müssen Sie nur aufkommen, wenn er auf einen Umgang mit der Ware '
            . 'zurückzuführen ist, der über das hinausgeht, was zur Prüfung von Beschaffenheit, '
            . 'Eigenschaften und Funktionsweise nötig war.'
        )

        . sz_recht_h('Wann das Widerrufsrecht nicht besteht')
        . sz_recht_p('Ein Widerrufsrecht besteht unter anderem nicht bei:')
        . sz_recht_ul([
            'Waren, die schnell verderben können oder deren Verfallsdatum schnell überschritten würde;',
            'versiegelten Waren, die aus Gründen des Gesundheitsschutzes oder der Hygiene nicht zur '
            . 'Rückgabe geeignet sind, wenn die Versiegelung nach der Lieferung entfernt wurde;',
            'Waren, die nach Ihren Angaben angefertigt oder eindeutig auf Ihre persönlichen Bedürfnisse '
            . 'zugeschnitten sind;',
            'Waren, die nach der Lieferung mit anderen Gütern untrennbar vermischt wurden.',
        ])
        . sz_recht_kasten(
            'Prüfhilfe: vollständige Liste in Art. 59 Codice del Consumo. Für diesen Shop sind besonders '
            . 'die versiegelten Hygieneartikel und die verderblichen Waren einschlägig. Bitte prüfen '
            . 'lassen, welche weiteren Ausnahmen für das Sortiment gelten und ob im Bestellvorgang auf '
            . 'sie hingewiesen werden muss.'
        )

        . sz_recht_h('Muster-Widerrufsformular')
        . sz_recht_p(
            '<em>Wenn Sie den Vertrag widerrufen wollen, füllen Sie bitte dieses Formular aus und senden '
            . 'Sie es zurück.</em>'
        )
        . sz_recht_p(
            'An [Firmenbezeichnung], [Anschrift], info@sapelza.it<br><br>'
            . 'Hiermit widerrufe(n) ich/wir den von mir/uns abgeschlossenen Vertrag über den Kauf der '
            . 'folgenden Waren:<br><br>'
            . '_______________________________________________<br><br>'
            . 'Bestellt am: ____________  Erhalten am: ____________<br><br>'
            . 'Name des/der Verbraucher(s): _________________________<br><br>'
            . 'Anschrift des/der Verbraucher(s): ____________________<br><br>'
            . 'Unterschrift (nur bei Mitteilung auf Papier): ________<br><br>'
            . 'Datum: ____________'
        );
}

/* ===================================================================
   Lieferung und Zahlung
   =================================================================== */

function sz_recht_text_versand(): string
{
    return sz_recht_hinweis()

        . sz_recht_h('Wohin wir liefern')
        . sz_recht_p(
            'Wir liefern selbst aus, mit dem eigenen Wagen: im Pustertal von Welsberg bis Winnebach '
            . 'sowie in den Seitentälern Gsies, Prags und Sexten. Außerhalb dieses Gebiets liefern wir '
            . 'nicht. Kein Paketdienst, keine Sendungsnummer.'
        )

        . sz_recht_h('Wann wir liefern')
        . sz_recht_p(
            'Den Liefertag wählen Sie beim Bestellen aus den angebotenen Terminen — an dem Tag, an dem '
            . 'der Betrieb offen ist und jemand die Ware annehmen kann.'
        )
        . sz_recht_p('[Bis wann muss bestellt sein, damit am gewählten Tag geliefert wird?]')
        . sz_recht_p('[Was geschieht, wenn beim Liefern niemand da ist?]')

        . sz_recht_h('Was die Lieferung kostet')
        . sz_recht_p('[Mindestbestellwert? Ab welchem Betrag ist die Lieferung frei? Was kostet sie darunter?]')

        . sz_recht_h('Wie bezahlt wird')
        . sz_recht_p('[Welche Zahlungsarten gibt es, und mit welchem Zahlungsziel?]')
        . sz_recht_p(
            'Rechnungen stellen wir elektronisch über das Austauschsystem der Agenzia delle Entrate aus. '
            . 'Geschäftskunden geben dafür bitte Empfängerkennung (Codice destinatario) oder PEC-Adresse '
            . 'an.'
        )

        . sz_recht_h('Wenn etwas fehlt oder beschädigt ist')
        . sz_recht_p(
            'Melden Sie sich bitte gleich bei uns: +39 0474 972205 oder info@sapelza.it. Weil wir selbst '
            . 'zustellen, ist das in aller Regel schnell erledigt. Welche Fristen gelten, steht in den '
            . '[Allgemeinen Geschäftsbedingungen] — für Geschäftskunden gelten kürzere als für '
            . 'Privatkunden.'
        );
}

/**
 * Der Gerüsttext zu einer Seite.
 */
function sz_recht_text(string $schluessel): string
{
    switch ($schluessel) {
        case 'impressum':   return sz_recht_text_impressum();
        case 'datenschutz': return sz_recht_text_datenschutz();
        case 'agb':         return sz_recht_text_agb();
        case 'widerruf':    return sz_recht_text_widerruf();
        case 'versand':     return sz_recht_text_versand();
        default:            return sz_recht_hinweis();
    }
}
