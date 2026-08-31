/*
 * Das Herz an der Kachel.
 *
 * Der Zustand kippt sofort und wird erst danach gespeichert. Wer merkt,
 * will nicht auf den Server warten — geht das Speichern schief, kippt
 * das Herz zurueck, und der Fehler ist dort zu sehen, wo er passiert
 * ist, statt in einer Meldung am Seitenrand.
 */
( function () {
    'use strict';

    if ( ! window.szFavoriten ) return;

    document.addEventListener( 'click', function ( e ) {
        var knopf = e.target.closest( '[data-sz-favorit]' );
        if ( ! knopf ) return;

        e.preventDefault();
        if ( knopf.dataset.laeuft === '1' ) return;

        var an = knopf.getAttribute( 'aria-pressed' ) !== 'true';

        /* Sofort umlegen. */
        knopf.setAttribute( 'aria-pressed', String( an ) );
        knopf.classList.toggle( 'ist-gemerkt', an );
        knopf.dataset.laeuft = '1';

        var daten = new URLSearchParams();
        daten.set( 'action', 'sz_favorit' );
        daten.set( '_wpnonce', window.szFavoriten.nonce );
        daten.set( 'produkt', knopf.getAttribute( 'data-sz-favorit' ) );
        daten.set( 'an', an ? '1' : '0' );

        fetch( window.szFavoriten.ziel, { method: 'POST', body: daten, credentials: 'same-origin' } )
            .then( function ( a ) { return a.json(); } )
            .then( function ( a ) {
                knopf.dataset.laeuft = '';

                if ( ! a || ! a.success ) { zurueck(); return; }

                /* Der Server hat das letzte Wort — zwei Zugaenge im selben
                   Betrieb koennen sich gegenseitig ueberholen. */
                knopf.setAttribute( 'aria-pressed', String( a.data.gemerkt ) );
                knopf.classList.toggle( 'ist-gemerkt', a.data.gemerkt );

                var zaehler = document.querySelector( '[data-sz-favoritenzahl]' );
                if ( zaehler ) zaehler.textContent = a.data.anzahl;
            } )
            .catch( zurueck );

        function zurueck() {
            knopf.dataset.laeuft = '';
            knopf.setAttribute( 'aria-pressed', String( ! an ) );
            knopf.classList.toggle( 'ist-gemerkt', ! an );
        }
    } );
} )();
