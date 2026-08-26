# SAPELZA Shop — Plugin

Das Verhalten des B2B-Shops von [sapelzashop.com](https://sapelzashop.com),
bewusst getrennt vom Child-Theme
[sapelza-child](https://github.com/sapelza/sapelza-child).

| Datei | Inhalt |
| --- | --- |
| `inc/shop-regeln.php` | keine Gutscheine, Zustellzone, Zahlarten, Bereichswahl |
| `inc/meine-artikel.php` | bereits bezogene Artikel — ohne Vorschlagswesen |
| `inc/wunschtermin.php` | der Betrieb wählt Liefertag und Zeitfenster selbst |

## Warum ein Plugin und kein Theme-Bestandteil

Weil es Entscheidungen sind, keine Gestaltung. Läge das im Theme, verschwände
es bei einem Theme-Wechsel — und Bestellungen gingen danach still wieder ohne
Liefertermin durch.

## Voraussetzung

WooCommerce. Fehlt es, meldet das Plugin das im Backend und bleibt untätig,
statt wirkungslos Haken zu setzen.

## Installation

Erstinstallation als ZIP über Plugins → Installieren → Plugin hochladen.
Danach übernimmt [Git Updater](https://git-updater.com) die Aktualisierung.

## Aktualisieren

Git Updater erkennt neue Versionen an **Git-Tags**. Version im Kopf von
`sapelza-shop.php` erhöhen und einen gleichlautenden Tag setzen:

    git add -A && git commit -m "..." && git tag 1.0.1 && git push && git push origin 1.0.1

Ohne Tag erscheint nie ein Update.

**Immer zuerst auf Staging einspielen, dann Live.**
