/*
 * Schnellerfassung.
 *
 * Eine Zeile ist erst fertig, wenn ein Artikel gefunden wurde. Danach
 * oeffnet sich die naechste von selbst — das ist der ganze Sinn: tippen,
 * Tab, tippen, Tab, ohne einen Klick.
 */
( function () {
    'use strict';

    var wurzel = document.querySelector( '[data-sz-erfassung]' );
    if ( ! wurzel ) return;

    var koerper   = wurzel.querySelector( '[data-sz-zeilen]' );
    var summeFeld = wurzel.querySelector( '[data-sz-summe]' );
    var knopf     = wurzel.querySelector( '[data-sz-uebernehmen]' );
    var ziel      = wurzel.dataset.ziel;
    var nonce     = wurzel.dataset.nonce;

    /* Die Waehrungsausgabe von WooCommerce ist serverseitig; hier reicht
       eine schlichte Darstellung mit Komma. */
    function geld( n ) {
        return '€ ' + n.toFixed( 2 ).replace( '.', ',' );
    }

    function zeilen() {
        return Array.prototype.slice.call( koerper.querySelectorAll( 'tr' ) );
    }

    function summeRechnen() {
        var summe = 0;
        var voll  = 0;

        zeilen().forEach( function ( tr ) {
            var preis = parseFloat( tr.dataset.preis || '0' );
            var menge = parseInt( tr.querySelector( '[data-sz-menge]' ).value, 10 ) || 0;
            if ( tr.dataset.id ) { summe += preis * menge; voll++; }
            tr.querySelector( '[data-sz-zeilensumme]' ).textContent = tr.dataset.id ? geld( preis * menge ) : '—';
        } );

        summeFeld.textContent = geld( summe );
        knopf.disabled = voll === 0;
    }

    function neueZeile( fokus ) {
        var tr = document.createElement( 'tr' );

        /*
         * Solange nichts erfasst ist, traegt die Zeile ist-leer. Auf dem
         * Telefon blendet die Gestaltung daran alles aus, was noch nichts
         * zu sagen hat: Bestand, Menge, Summe, Entfernen. Vorher stand
         * dort ein Mengenwaehler und ein Kreuz, bevor ueberhaupt etwas
         * eingegeben war — das las sich wie eine Bestellung, die schon
         * drinsteht.
         */
        tr.className = 'sz-erfassung__zeile ist-leer';

        tr.innerHTML =
            '<td><input type="text" class="sz-erfassung__nummer" data-sz-nummer ' +
                'placeholder="Art.-Nr. oder EAN" autocomplete="off" spellcheck="false"></td>' +
            '<td class="sz-erfassung__artikel sz-erfassung__was" data-sz-artikel><em>noch nichts erfasst</em></td>' +
            '<td class="sz-erfassung__bestand mono" data-sz-bestand>—</td>' +
            '<td><span class="sz-menge">' +
                '<button type="button" data-sz-minus aria-label="weniger">−</button>' +
                '<input type="number" min="1" value="1" data-sz-menge>' +
                '<button type="button" data-sz-plus aria-label="mehr">+</button>' +
            '</span></td>' +
            '<td class="sz-erfassung__zeilensumme mono" data-sz-zeilensumme>—</td>' +
            '<td><button type="button" class="sz-erfassung__weg-knopf" data-sz-loeschen ' +
                'aria-label="Zeile entfernen">×</button></td>';

        koerper.appendChild( tr );
        if ( fokus ) tr.querySelector( '[data-sz-nummer]' ).focus();
        return tr;
    }

    function suchen( tr ) {
        var feld = tr.querySelector( '[data-sz-nummer]' );
        var wert = feld.value.trim();
        if ( ! wert ) return;

        var zelle = tr.querySelector( '[data-sz-artikel]' );
        zelle.innerHTML = '<em>wird gesucht …</em>';

        var daten = new URLSearchParams();
        daten.set( 'action', 'sz_suchen' );
        daten.set( '_wpnonce', nonce );
        daten.set( 'nummer', wert );

        fetch( ziel, { method: 'POST', body: daten, credentials: 'same-origin' } )
            .then( function ( a ) { return a.json(); } )
            .then( function ( a ) {
                if ( ! a || ! a.success ) {
                    zelle.innerHTML = '<span class="sz-erfassung__fehler"></span>';
                    zelle.firstChild.textContent = ( a && a.data && a.data.meldung ) || 'Nicht gefunden.';
                    tr.dataset.id = '';
                    tr.dataset.preis = '0';
                    summeRechnen();
                    return;
                }

                var d = a.data;
                tr.dataset.id = d.id;
                tr.dataset.preis = d.preis;
                tr.classList.remove( 'ist-leer' );

                zelle.innerHTML = '';
                if ( d.marke ) {
                    var m = document.createElement( 'span' );
                    m.className = 'sz-erfassung__marke mono';
                    m.textContent = d.marke;
                    zelle.appendChild( m );
                }
                var n = document.createElement( 'span' );
                n.className = 'sz-erfassung__name';
                n.textContent = d.name;
                zelle.appendChild( n );

                tr.querySelector( '[data-sz-bestand]' ).textContent =
                    d.bestand === null ? '—' : d.bestand + ' Stk.';

                summeRechnen();

                /* Die naechste Zeile oeffnet sich nur, wenn diese die
                   letzte war — sonst springt der Fokus mitten im Erfassen
                   ans Ende. */
                if ( tr === koerper.lastElementChild ) neueZeile( true );
            } )
            .catch( function () {
                zelle.innerHTML = '<span class="sz-erfassung__fehler">Verbindung unterbrochen.</span>';
            } );
    }

    /* --- Bedienung ---------------------------------------------------- */

    koerper.addEventListener( 'keydown', function ( e ) {
        if ( e.key !== 'Enter' ) return;
        if ( ! e.target.matches( '[data-sz-nummer]' ) ) return;
        e.preventDefault();
        suchen( e.target.closest( 'tr' ) );
    } );

    koerper.addEventListener( 'change', function ( e ) {
        if ( e.target.matches( '[data-sz-nummer]' ) ) { suchen( e.target.closest( 'tr' ) ); return; }
        if ( e.target.matches( '[data-sz-menge]' ) ) summeRechnen();
    } );

    koerper.addEventListener( 'click', function ( e ) {
        var tr = e.target.closest( 'tr' );
        if ( ! tr ) return;

        if ( e.target.matches( '[data-sz-loeschen]' ) ) {
            tr.remove();
            if ( ! koerper.children.length ) neueZeile( false );
            summeRechnen();
            return;
        }

        var feld = tr.querySelector( '[data-sz-menge]' );
        if ( e.target.matches( '[data-sz-plus]' ) )  { feld.value = ( parseInt( feld.value, 10 ) || 1 ) + 1; summeRechnen(); }
        if ( e.target.matches( '[data-sz-minus]' ) ) { feld.value = Math.max( 1, ( parseInt( feld.value, 10 ) || 1 ) - 1 ); summeRechnen(); }
    } );

    knopf.addEventListener( 'click', function () {
        var liste = zeilen()
            .filter( function ( tr ) { return tr.dataset.id; } )
            .map( function ( tr ) {
                return { id: tr.dataset.id, menge: tr.querySelector( '[data-sz-menge]' ).value };
            } );

        if ( ! liste.length ) return;

        knopf.disabled = true;
        knopf.textContent = 'wird übernommen …';

        var daten = new URLSearchParams();
        daten.set( 'action', 'sz_erfassung_warenkorb' );
        daten.set( '_wpnonce', nonce );
        daten.set( 'zeilen', JSON.stringify( liste ) );

        fetch( ziel, { method: 'POST', body: daten, credentials: 'same-origin' } )
            .then( function ( a ) { return a.json(); } )
            .then( function ( a ) {
                if ( a && a.success ) { window.location.href = a.data.ziel; return; }
                knopf.disabled = false;
                knopf.textContent = 'In den Warenkorb';
                window.alert( ( a && a.data && a.data.meldung ) || 'Übernahme fehlgeschlagen.' );
            } )
            .catch( function () {
                knopf.disabled = false;
                knopf.textContent = 'In den Warenkorb';
            } );
    } );

    /* --- Tippen / Scannen --------------------------------------------- */

    wurzel.addEventListener( 'click', function ( e ) {
        var w = e.target.closest( '[data-sz-weg]' );
        if ( ! w ) return;

        var welcher = w.dataset.szWeg;
        wurzel.querySelectorAll( '[data-sz-weg]' ).forEach( function ( k ) {
            k.setAttribute( 'aria-selected', String( k === w ) );
        } );
        wurzel.querySelectorAll( '[data-sz-bereich]' ).forEach( function ( b ) {
            if ( b.dataset.szBereich === welcher ) b.removeAttribute( 'hidden' );
            else b.setAttribute( 'hidden', '' );
        } );
    } );

    /* --- Scannen ------------------------------------------------------- */

    var scanKnopf  = wurzel.querySelector( '[data-sz-scanstart]' );
    var scanStatus = wurzel.querySelector( '[data-sz-scanstatus]' );
    var video      = wurzel.querySelector( '[data-sz-video]' );

    /*
     * Ohne Bibliothek: der Browser bringt BarcodeDetector mit, oder eben
     * nicht. Safari und iOS koennen es bis heute nicht — dort wird das
     * ehrlich gesagt, statt eine Kamera zu oeffnen, die nichts erkennt.
     */
    var kannScannen = ( 'BarcodeDetector' in window ) &&
                      !! ( navigator.mediaDevices && navigator.mediaDevices.getUserMedia );

    if ( ! kannScannen ) {
        /*
         * Safari und iOS bringen BarcodeDetector nicht mit. Hier aber
         * nicht bloss "geht nicht" sagen: der Weg, der auf JEDEM Telefon
         * funktioniert, ist das Regaletikett mit der Kamera-App. Im QR
         * steht eine Adresse, und die oeffnet iOS von selbst.
         */
        /*
         * Die Erklaerung stand unter dem Beschreibungstext — auf dem
         * Telefon weit unterhalb des Bildschirms. Zu sehen war nur ein
         * leerer roter Rahmen, der nichts tat. Also die Buehne weg und
         * die Erklaerung an ihre Stelle: dorthin schaut man zuerst.
         */
        var buehne = wurzel.querySelector( ".sz-scan__buehne" );
        if ( buehne ) buehne.hidden = true;

        var oben = document.createElement( "p" );
        oben.className = "sz-scan__kasten sz-scan__kasten--oben";
        oben.innerHTML =
            "<strong>Auf diesem Gerät scannen Sie mit der Kamera-App.</strong><br>" +
            "Halten Sie sie auf das QR-Etikett am Regal — es erscheint ein Banner, " +
            "antippen, und der Artikel steht hier in der Zeile. " +
            "Den Strichcode auf der Packung kann dieser Browser nicht lesen; " +
            "dafür tippen Sie die Nummer ein.";

        if ( buehne && buehne.parentNode ) buehne.parentNode.insertBefore( oben, buehne );

        scanStatus.hidden = true;
        scanKnopf.hidden = true;
    }

    if ( scanKnopf ) {
        scanKnopf.addEventListener( 'click', function () {
            if ( ! kannScannen ) return;

            navigator.mediaDevices.getUserMedia( { video: { facingMode: 'environment' } } )
                .then( function ( strom ) {
                    video.srcObject = strom;
                    video.play();
                    scanStatus.textContent = 'Kamera läuft. Barcode ins Feld halten.';

                    var leser = new window.BarcodeDetector();

                    function lesen() {
                        if ( ! video.srcObject ) return;
                        leser.detect( video )
                            .then( function ( treffer ) {
                                if ( treffer.length ) {
                                    var code = treffer[ 0 ].rawValue;
                                    strom.getTracks().forEach( function ( t ) { t.stop(); } );
                                    video.srcObject = null;
                                    scanStatus.textContent = 'Erkannt: ' + code;

                                    /* Zurueck ins Tippen-Feld, Zeile fuellen. */
                                    wurzel.querySelector( '[data-sz-weg="tippen"]' ).click();
                                    var letzte = koerper.lastElementChild || neueZeile( false );
                                    letzte.querySelector( '[data-sz-nummer]' ).value = code;
                                    suchen( letzte );
                                    return;
                                }
                                window.requestAnimationFrame( lesen );
                            } )
                            .catch( function () { window.requestAnimationFrame( lesen ); } );
                    }

                    lesen();
                } )
                .catch( function () {
                    scanStatus.textContent = 'Kein Zugriff auf die Kamera. Bitte im Browser erlauben.';
                } );
        } );
    }

    /* ------------------------------------------------------------------
       Vom Regaletikett hereinspaziert: ?nr=SP-K-10

       Das ist der Weg, der auf JEDEM Telefon funktioniert. Im QR steht
       eine Adresse, keine nackte Nummer — die Kamera-App von iOS und
       Android oeffnet sie einfach. Deshalb braucht es hier keinen
       Scanner im Browser und keine Berechtigung.

       Der Cursor landet in der MENGE, nicht in der Nummer: am Regal
       steht die Frage "wie viele", nicht "welcher".
       ------------------------------------------------------------------ */
    function vomEtikett() {
        var nr = new URLSearchParams( window.location.search ).get( "nr" );
        if ( ! nr ) return false;

        var tr = neueZeile( false );
        tr.querySelector( "[data-sz-nummer]" ).value = nr;

        var mengeFeld = tr.querySelector( "[data-sz-menge]" );
        suchen( tr );
        mengeFeld.focus();
        mengeFeld.select();
        return true;
    }

    if ( ! vomEtikett() ) neueZeile( false );
    summeRechnen();
} )();
