/*
 * Etiketten fuer das Regal.
 *
 * Auswahl in der Liste, Vorschau eines A4-Bogens, drucken. Auf dem
 * Etikett steht der eigene Name gross, die Artikelnummer klein, der QR
 * daneben.
 *
 * Im QR steht NUR die Artikelnummer — als Adresse verpackt, nie der Name.
 * Das ist die wichtigste Entscheidung an der ganzen Sache: Sie koennen
 * jederzeit umbenennen, ohne dass ein gedrucktes Etikett ungueltig wird.
 * Der Aufkleber zeigt dann zwar noch den alten Namen, funktioniert aber
 * weiter. Stuende der Name im Code, muesste nach jeder Umbenennung neu
 * gedruckt werden.
 */
( function () {
    'use strict';

    var liste = document.querySelector( '[data-sz-namen]' );
    var leiste = document.querySelector( '.sz-etiketten-leiste' );
    if ( ! liste || ! leiste || ! window.SZQR ) return;

    var basis    = liste.dataset.basis || '';
    var alle     = leiste.querySelector( '[data-sz-alle]' );
    var zaehler  = leiste.querySelector( '[data-sz-gewaehlt]' );
    var groesse  = leiste.querySelector( '[data-sz-groesse]' );
    var drucken  = leiste.querySelector( '[data-sz-drucken]' );

    /* Die beiden Bogenmasse. */
    var MASSE = {
        '70x37': { b: 70, h: 37, spalten: 3, zeilen: 8, qr: 26, name: '11pt' },
        '48x25': { b: 48, h: 25, spalten: 4, zeilen: 11, qr: 18, name: '8pt' }
    };

    function gewaehlte() {
        return Array.prototype.slice.call( liste.querySelectorAll( '[data-sz-wahl]:checked' ) )
            .map( function ( k ) {
                var zeile = k.closest( '[data-sz-artikel]' );
                var eigen = zeile.querySelector( '[data-sz-eigenname]' );
                var leer  = eigen.classList.contains( 'ist-leer' );
                return {
                    nummer: k.value,
                    name: leer ? zeile.querySelector( '.sz-katalogname' ).firstChild.textContent.trim()
                               : eigen.textContent.trim()
                };
            } )
            .filter( function ( e ) { return e.nummer; } );
    }

    function zaehlen() {
        var n = gewaehlte().length;
        zaehler.textContent = n + ( n === 1 ? ' gewählt' : ' gewählt' );
        drucken.disabled = n === 0;
    }

    liste.addEventListener( 'change', function ( e ) {
        if ( e.target.matches( '[data-sz-wahl]' ) ) zaehlen();
    } );

    alle.addEventListener( 'change', function () {
        liste.querySelectorAll( '[data-sz-wahl]' ).forEach( function ( k ) {
            k.checked = alle.checked;
        } );
        zaehlen();
    } );

    /* --- Einzelner Code, gross auf dem Schirm ---------------------------- */

    liste.addEventListener( 'click', function ( e ) {
        var k = e.target.closest( '[data-sz-qr]' );
        if ( ! k ) return;

        var zeile = k.closest( '[data-sz-artikel]' );
        var nummer = zeile.querySelector( '[data-sz-wahl]' ).value;
        if ( ! nummer ) return;

        var eigen = zeile.querySelector( '[data-sz-eigenname]' );
        var name = eigen.classList.contains( 'ist-leer' )
            ? zeile.querySelector( '.sz-katalogname' ).firstChild.textContent.trim()
            : eigen.textContent.trim();

        zeigen( name, nummer );
    } );

    function zeigen( name, nummer ) {
        var deckel = document.createElement( 'div' );
        deckel.className = 'sz-qr-deckel';
        deckel.innerHTML =
            '<div class="sz-qr-karte">' +
                '<p class="sz-qr-name"></p>' +
                '<div class="sz-qr-bild">' + window.SZQR.svg( adresse( nummer ) ) + '</div>' +
                '<p class="sz-qr-nummer mono"></p>' +
                '<button type="button" class="sz-erfassung__knopf">Schließen</button>' +
            '</div>';
        deckel.querySelector( '.sz-qr-name' ).textContent = name;
        deckel.querySelector( '.sz-qr-nummer' ).textContent = nummer;

        deckel.addEventListener( 'click', function ( e ) {
            if ( e.target === deckel || e.target.tagName === 'BUTTON' ) deckel.remove();
        } );

        document.body.appendChild( deckel );
    }

    function adresse( nummer ) {
        var b = basis || ( location.origin + '/schnellerfassung/' );
        return b + ( b.indexOf( '?' ) >= 0 ? '&' : '?' ) + 'nr=' + encodeURIComponent( nummer );
    }

    /* --- Der Bogen -------------------------------------------------------- */

    drucken.addEventListener( 'click', function () {
        var eintraege = gewaehlte();
        if ( ! eintraege.length ) return;

        var mass = MASSE[ groesse.value ] || MASSE[ '70x37' ];
        var alt = document.querySelector( '.sz-bogen' );
        if ( alt ) alt.remove();

        var bogen = document.createElement( 'div' );
        bogen.className = 'sz-bogen';
        bogen.style.setProperty( '--etikett-breite', mass.b + 'mm' );
        bogen.style.setProperty( '--etikett-hoehe', mass.h + 'mm' );
        bogen.style.setProperty( '--etikett-spalten', mass.spalten );
        bogen.style.setProperty( '--etikett-qr', mass.qr + 'mm' );
        bogen.style.setProperty( '--etikett-name', mass.name );

        var kopf = document.createElement( 'div' );
        kopf.className = 'sz-bogen__leiste';
        kopf.innerHTML =
            '<span>' + eintraege.length + ' Etiketten · ' + mass.b + ' × ' + mass.h + ' mm</span> ' +
            '<button type="button" class="sz-erfassung__knopf" data-drucken>Drucken</button> ' +
            '<button type="button" class="sz-bogen__zu" data-zu>Schließen</button>';
        bogen.appendChild( kopf );

        var blatt = document.createElement( 'div' );
        blatt.className = 'sz-bogen__blatt';

        eintraege.forEach( function ( e ) {
            var etikett = document.createElement( 'div' );
            etikett.className = 'sz-etikett';

            var text = document.createElement( 'div' );
            text.className = 'sz-etikett__text';

            var n = document.createElement( 'p' );
            n.className = 'sz-etikett__name';
            n.textContent = e.name;

            var nr = document.createElement( 'p' );
            nr.className = 'sz-etikett__nummer mono';
            nr.textContent = e.nummer;

            text.appendChild( n );
            text.appendChild( nr );

            var bild = document.createElement( 'div' );
            bild.className = 'sz-etikett__qr';
            try {
                bild.innerHTML = window.SZQR.svg( adresse( e.nummer ) );
            } catch ( x ) {
                bild.textContent = '—';
            }

            etikett.appendChild( text );
            etikett.appendChild( bild );
            blatt.appendChild( etikett );
        } );

        bogen.appendChild( blatt );
        document.body.appendChild( bogen );

        kopf.querySelector( '[data-drucken]' ).addEventListener( 'click', function () { window.print(); } );
        kopf.querySelector( '[data-zu]' ).addEventListener( 'click', function () { bogen.remove(); } );

        bogen.scrollIntoView( { behavior: 'smooth' } );
    } );

    zaehlen();
} )();
