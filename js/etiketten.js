/*
 * Meine Artikel — bestellen, filtern, Etiketten.
 *
 * Im QR steht NUR die Artikelnummer, als Adresse verpackt, nie der Name.
 * Das ist die wichtigste Entscheidung an der ganzen Sache: Sie koennen
 * jederzeit umbenennen, ohne dass ein gedrucktes Etikett ungueltig wird.
 * Stuende der Name im Code, muesste nach jeder Umbenennung neu gedruckt
 * werden.
 */
( function () {
    'use strict';

    var bereich = document.querySelector( '[data-sz-namen]' );
    if ( ! bereich ) return;

    var ziel  = bereich.dataset.ziel;
    var basis = bereich.dataset.basis || ( location.origin + '/schnellerfassung/' );

    function zeilen() {
        return Array.prototype.slice.call( bereich.querySelectorAll( '[data-sz-artikel]' ) );
    }

    function adresse( nummer ) {
        return basis + ( basis.indexOf( '?' ) >= 0 ? '&' : '?' ) + 'nr=' + encodeURIComponent( nummer );
    }

    function daten( zeile ) {
        var eigen = zeile.querySelector( '[data-sz-eigenname]' );
        var leer = eigen.classList.contains( 'ist-leer' );
        return {
            nummer: ( zeile.querySelector( '[data-sz-wahl]' ) || {} ).value || '',
            name: leer ? zeile.querySelector( '.sz-katalogname' ).textContent.trim()
                       : ( eigen.querySelector( '.sz-eigenname__wort' ) || eigen ).textContent.trim()
        };
    }

    /* ---------------------------------------------------------------
       Suchen in den eigenen Artikeln
       --------------------------------------------------------------- */

    var filter = bereich.querySelector( '[data-sz-filter]' );
    var nur = 'alle';

    /* Suchwort und Favoritenfilter greifen gemeinsam — wer nach
       "Spuel" sucht und Favoriten anhat, will beides. */
    function sieben() {
        var was = filter ? filter.value.trim().toLowerCase() : '';

        zeilen().forEach( function ( z ) {
            var passtWort = ! was || ( z.dataset.szSuchtext || '' ).indexOf( was ) >= 0;
            var passtStern = nur === 'alle' || z.dataset.szGemerkt === '1';
            z.hidden = ! ( passtWort && passtStern );
        } );
    }

    if ( filter ) filter.addEventListener( 'input', sieben );

    /* Der Stern kann sich aendern, waehrend gefiltert wird. */
    document.addEventListener( 'sz:favorit', sieben );

    bereich.querySelectorAll( '[data-sz-nur]' ).forEach( function ( knopf ) {
        knopf.addEventListener( 'click', function () {
            nur = knopf.getAttribute( 'data-sz-nur' );

            bereich.querySelectorAll( '[data-sz-nur]' ).forEach( function ( k ) {
                k.setAttribute( 'aria-pressed', String( k === knopf ) );
            } );

            sieben();
        } );
    } );

    /* ---------------------------------------------------------------
       Menge und in den Warenkorb
       --------------------------------------------------------------- */

    bereich.addEventListener( 'click', function ( e ) {
        var zeile = e.target.closest( '[data-sz-artikel]' );
        if ( ! zeile ) return;

        var feld = zeile.querySelector( '[data-sz-menge]' );

        if ( e.target.matches( '[data-sz-plus]' ) ) {
            feld.value = ( parseInt( feld.value, 10 ) || 1 ) + 1;
            return;
        }
        if ( e.target.matches( '[data-sz-minus]' ) ) {
            feld.value = Math.max( 1, ( parseInt( feld.value, 10 ) || 1 ) - 1 );
            return;
        }

        var knopf = e.target.closest( '[data-sz-inkorb]' );
        if ( ! knopf ) return;

        knopf.disabled = true;

        var post = new URLSearchParams();
        post.set( 'action', 'sz_erfassung_warenkorb' );
        post.set( '_wpnonce', bereich.dataset.erfassung );
        post.set( 'zeilen', JSON.stringify( [ {
            id: zeile.dataset.szArtikel,
            menge: feld.value
        } ] ) );

        fetch( ziel, { method: 'POST', body: post, credentials: 'same-origin' } )
            .then( function ( a ) { return a.json(); } )
            .then( function ( a ) {
                knopf.disabled = false;
                if ( a && a.success ) {
                    /* Kurze Bestaetigung an Ort und Stelle statt eines
                       Sprungs in den Warenkorb — wer nachbestellt, will
                       meist mehrere Zeilen hintereinander. */
                    knopf.classList.add( 'ist-drin' );
                    window.setTimeout( function () { knopf.classList.remove( 'ist-drin' ); }, 1400 );

                    /* Und die Zahl oben am Korb mitziehen. Ohne das blieb
                       sie stehen, bis jemand die Seite wechselte. */
                    if ( window.szWarenkorbZahl ) window.szWarenkorbZahl( a.data.korb );
                }
            } )
            .catch( function () { knopf.disabled = false; } );
    } );

    /* ---------------------------------------------------------------
       Etiketten
       --------------------------------------------------------------- */

    var kapitel = bereich.querySelector( '.sz-etikettenkapitel' );
    if ( ! kapitel ) return;

    /* ---------------------------------------------------------------
       Der QR-Code
       ---------------------------------------------------------------

       Bis 1.18.0 stand hier ein selbst geschriebener Erzeuger. Er war
       rechnerisch geprueft — Reed-Solomon, die BCH-Codes, die
       Formatbits —, aber die Anordnung der Module liess sich ohne
       Leser nicht pruefen. Ich hatte darauf hingewiesen und geraten,
       vor dem ersten Bogen ein Etikett vom Bildschirm zu scannen.

       Mit dem Scanner kam der Leser ins Haus, und damit die Antwort:
       die Codes waren nicht lesbar. Nicht einer, auch "TEST" nicht.
       ZXings eigener Code wurde im selben Versuch anstandslos gelesen.

       Also zeichnet jetzt ZXing. Weniger eigener Code, und einer, der
       nachweislich gelesen wird.
       --------------------------------------------------------------- */

    var zxingWeg = bereich.dataset.szZxing || '';
    var zxingGeladen = null;

    function zxingLaden() {
        if ( zxingGeladen ) return zxingGeladen;

        zxingGeladen = new Promise( function ( fertig, schief ) {
            if ( window.ZXingBrowser ) { fertig(); return; }
            if ( ! zxingWeg ) { schief(); return; }

            var s = document.createElement( 'script' );
            s.src = zxingWeg;
            s.onload = function () { window.ZXingBrowser ? fertig() : schief(); };
            s.onerror = schief;
            document.head.appendChild( s );
        } );

        return zxingGeladen;
    }

    function qrZeichnen( kasten, text ) {
        zxingLaden().then( function () {
            var schreiber = new window.ZXingBrowser.BrowserQRCodeSvgWriter();
            var svg = schreiber.write( text, 240, 240 );

            /* Die Groesse gibt das Etikett vor, nicht der Schreiber. */
            svg.removeAttribute( 'width' );
            svg.removeAttribute( 'height' );
            svg.setAttribute( 'preserveAspectRatio', 'xMidYMid meet' );

            kasten.innerHTML = '';
            kasten.appendChild( svg );
        } ).catch( function () {
            kasten.textContent = '\u2014';
        } );
    }

    var zaehler   = kapitel.querySelector( '[data-sz-gewaehlt]' );
    var drucken   = kapitel.querySelector( '[data-sz-drucken]' );
    var alleKnopf = kapitel.querySelector( '[data-sz-alle]' );
    var vorschau  = kapitel.querySelector( '[data-sz-vorschau]' );
    var vTitel    = kapitel.querySelector( '[data-sz-vorschautitel]' );

    var MASSE = {
        '70x37': { b: 70,   h: 37,   spalten: 3, zeilen: 8,  qr: 26, name: '11pt' },
        '48x25': { b: 48.5, h: 25.4, spalten: 4, zeilen: 11, qr: 17, name: '8pt' }
    };

    function aktuellesMass() {
        var k = kapitel.querySelector( '[data-sz-groesse][aria-pressed="true"]' );
        return MASSE[ k ? k.dataset.szGroesse : '70x37' ] || MASSE[ '70x37' ];
    }

    function gewaehlte() {
        return zeilen()
            .filter( function ( z ) {
                var k = z.querySelector( '[data-sz-wahl]' );
                return k && k.checked;
            } )
            .map( daten )
            .filter( function ( e ) { return e.nummer; } );
    }

    /* Ohne Auswahl: die fuenf zuletzt bestellten. Die Liste ist bereits
       nach letzter Lieferung sortiert, die ersten fuenf genuegen. */
    function anstoss() {
        return zeilen().slice( 0, 5 ).map( daten ).filter( function ( e ) { return e.nummer; } );
    }

    function etikett( eintrag, mass ) {
        var el = document.createElement( 'div' );
        el.className = 'sz-etikett';

        var text = document.createElement( 'div' );
        text.className = 'sz-etikett__text';

        var n = document.createElement( 'p' );
        n.className = 'sz-etikett__name';
        n.textContent = eintrag.name;

        var nr = document.createElement( 'p' );
        nr.className = 'sz-etikett__nummer mono';
        nr.textContent = eintrag.nummer;

        text.appendChild( n );
        text.appendChild( nr );

        var bild = document.createElement( 'div' );
        bild.className = 'sz-etikett__qr';
        qrZeichnen( bild, adresse( eintrag.nummer ) );

        el.appendChild( text );
        el.appendChild( bild );
        el.style.setProperty( '--etikett-breite', mass.b + 'mm' );
        el.style.setProperty( '--etikett-hoehe', mass.h + 'mm' );
        el.style.setProperty( '--etikett-qr', mass.qr + 'mm' );
        el.style.setProperty( '--etikett-name', mass.name );
        return el;
    }

    function vorschauZeichnen() {
        var wahl = gewaehlte();
        var zeigen = wahl.length ? wahl.slice( 0, 10 ) : anstoss();

        vTitel.textContent = wahl.length
            ? ( wahl.length > 10 ? 'Ihre Auswahl · erste 10 von ' + wahl.length : 'Ihre Auswahl' )
            : 'Zuletzt bestellt';

        var mass = aktuellesMass();
        vorschau.innerHTML = '';
        zeigen.forEach( function ( e ) { vorschau.appendChild( etikett( e, mass ) ); } );

        zaehler.textContent = wahl.length + ' ausgewählt';
        drucken.disabled = wahl.length === 0;
    }

    bereich.addEventListener( 'change', function ( e ) {
        if ( e.target.matches( '[data-sz-wahl]' ) ) vorschauZeichnen();
    } );

    kapitel.addEventListener( 'click', function ( e ) {
        var g = e.target.closest( '[data-sz-groesse]' );
        if ( g ) {
            kapitel.querySelectorAll( '[data-sz-groesse]' ).forEach( function ( k ) {
                k.setAttribute( 'aria-pressed', String( k === g ) );
            } );
            vorschauZeichnen();
        }
    } );

    alleKnopf.addEventListener( 'click', function () {
        var kaesten = bereich.querySelectorAll( '[data-sz-wahl]' );
        var allesAn = Array.prototype.every.call( kaesten, function ( k ) { return k.checked; } );
        kaesten.forEach( function ( k ) { k.checked = ! allesAn; } );
        alleKnopf.textContent = allesAn ? 'Alle auswählen' : 'Auswahl aufheben';
        vorschauZeichnen();
    } );

    /* --- Der Bogen -------------------------------------------------------- */

    drucken.addEventListener( 'click', function () {
        var eintraege = gewaehlte();
        if ( ! eintraege.length ) return;

        var mass = aktuellesMass();
        var alt = document.querySelector( '.sz-bogen' );
        if ( alt ) alt.remove();

        var bogen = document.createElement( 'div' );
        bogen.className = 'sz-bogen';
        bogen.style.setProperty( '--etikett-spalten', mass.spalten );

        var kopf = document.createElement( 'div' );
        kopf.className = 'sz-bogen__leiste';
        kopf.innerHTML =
            '<span></span>' +
            '<button type="button" class="sz-erfassung__knopf" data-drucken>Drucken</button> ' +
            '<button type="button" class="sz-bogen__zu" data-zu>Schließen</button>';
        kopf.firstChild.textContent =
            eintraege.length + ' Etiketten · ' + mass.b + ' × ' + mass.h + ' mm';
        bogen.appendChild( kopf );

        var blatt = document.createElement( 'div' );
        blatt.className = 'sz-bogen__blatt';
        eintraege.forEach( function ( e ) { blatt.appendChild( etikett( e, mass ) ); } );
        bogen.appendChild( blatt );

        document.body.appendChild( bogen );

        kopf.querySelector( '[data-drucken]' ).addEventListener( 'click', function () { window.print(); } );
        kopf.querySelector( '[data-zu]' ).addEventListener( 'click', function () { bogen.remove(); } );

        bogen.scrollIntoView( { behavior: 'smooth' } );
    } );

    vorschauZeichnen();
} )();
