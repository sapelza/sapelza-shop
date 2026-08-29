/*
 * Eigene Artikelnamen — umbenennen an Ort und Stelle.
 *
 * Der Name IST die Schaltflaeche. Klick, das Feld oeffnet sich, Text ist
 * markiert, Enter speichert, Escape verwirft. Kein Fenster, kein
 * Speichern-Knopf, keine Bearbeiten-Ansicht.
 *
 * Leeres Feld loescht den eigenen Namen — so macht man es rueckgaengig,
 * ohne dass es dafuer einen zweiten Knopf braucht.
 */
( function () {
    'use strict';

    var wurzel = document.querySelector( '[data-sz-namen]' );
    if ( ! wurzel ) return;

    var ziel  = wurzel.dataset.ziel;
    var nonce = wurzel.dataset.nonce;
    var leerText = 'Eigenen Namen vergeben';

    /* Nur das Wort ersetzen, nicht den ganzen Knopf: darin steckt auch
       der Stift, und textContent auf dem Knopf wuerde ihn mitloeschen. */
    function wort( knopf ) {
        return knopf.querySelector( '.sz-eigenname__wort' ) || knopf;
    }

    function schliessen( feld, knopf, text, leer ) {
        wort( knopf ).textContent = text;
        knopf.classList.toggle( 'ist-leer', leer );
        knopf.hidden = false;
        feld.remove();
        knopf.focus();
    }

    function speichern( knopf, wert ) {
        var zeile = knopf.closest( '[data-sz-artikel]' );
        var id = zeile ? zeile.dataset.szArtikel : '';
        if ( ! id ) return Promise.resolve( null );

        var daten = new URLSearchParams();
        daten.set( 'action', 'sz_name_setzen' );
        daten.set( '_wpnonce', nonce );
        daten.set( 'produkt', id );
        daten.set( 'name', wert );

        return fetch( ziel, { method: 'POST', body: daten, credentials: 'same-origin' } )
            .then( function ( a ) { return a.json(); } );
    }

    function oeffnen( knopf ) {
        if ( knopf.hidden ) return;

        var istLeer = knopf.classList.contains( 'ist-leer' );
        var alt = istLeer ? '' : wort( knopf ).textContent.trim();

        var feld = document.createElement( 'input' );
        feld.type = 'text';
        feld.className = 'sz-eigenname__feld';
        feld.value = alt;
        feld.maxLength = 40;   /* er muss aufs Regaletikett passen */
        feld.setAttribute( 'aria-label', 'Eigener Name' );

        knopf.hidden = true;
        knopf.parentNode.insertBefore( feld, knopf );
        feld.focus();
        feld.select();

        var fertig = false;

        function abschliessen( speichernJa ) {
            if ( fertig ) return;
            fertig = true;

            if ( ! speichernJa ) {
                schliessen( feld, knopf, istLeer ? leerText : alt, istLeer );
                return;
            }

            var wert = feld.value.trim();
            feld.disabled = true;

            speichern( knopf, wert ).then( function ( a ) {
                if ( a && a.success ) {
                    var eigen = a.data.eigen;
                    schliessen( feld, knopf, eigen || leerText, ! eigen );

                    /* Die Zeile "zuletzt geaendert von" nachziehen. */
                    var zeile = knopf.closest( '[data-sz-artikel]' );
                    var wer = zeile.querySelector( '.sz-eigenname__wer' );
                    if ( eigen && a.data.wer ) {
                        if ( ! wer ) {
                            wer = document.createElement( 'span' );
                            wer.className = 'sz-eigenname__wer';
                            knopf.parentNode.appendChild( wer );
                        }
                        wer.textContent = 'zuletzt geändert von ' + a.data.wer;
                    } else if ( wer ) {
                        wer.remove();
                    }
                    return;
                }
                /* Fehlgeschlagen: den alten Stand wiederherstellen, statt
                   einen Namen zu zeigen, der nirgends gespeichert ist. */
                schliessen( feld, knopf, istLeer ? leerText : alt, istLeer );
            } ).catch( function () {
                schliessen( feld, knopf, istLeer ? leerText : alt, istLeer );
            } );
        }

        feld.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Enter' )  { e.preventDefault(); abschliessen( true ); }
            if ( e.key === 'Escape' ) { e.preventDefault(); abschliessen( false ); }
        } );

        feld.addEventListener( 'blur', function () { abschliessen( true ); } );
    }

    wurzel.addEventListener( 'click', function ( e ) {
        var knopf = e.target.closest( '[data-sz-eigenname]' );
        if ( knopf ) oeffnen( knopf );
    } );
} )();
