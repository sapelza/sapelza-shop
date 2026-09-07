/*
 * Die gewaehlte Sprache reist in der Adresse mit.
 *
 * Der Shop merkt sich die Sprache im Cookie. Der Zwischenspeicher auf
 * dem Server kennt Cookies aber nicht: einem Gast, der ueber einen
 * nackten Link weitergeht, gaebe er die gespeicherte deutsche Seite.
 * Also haengt dieses Skript ?lang=… an jeden Link im Haus und an jedes
 * GET-Formular (Suche, Sortierung, je Seite) — im Moment des Klicks,
 * damit auch Links greifen, die erst spaeter in die Seite kommen.
 *
 * Es laeuft nur, wenn eine andere als die Standardsprache gewaehlt ist;
 * PHP bindet es sonst gar nicht ein. Deutsche Adressen bleiben sauber.
 */
( function () {
    'use strict';

    var s = window.szSprache;
    if ( ! s || ! s.code || s.code === s.standard ) return;

    /* Die Adresse mit Parameter — oder null, wenn sie keinen bekommt:
       fremde Seiten, das Backend, Sprungmarken, mailto und tel. */
    function mit( roh ) {
        if ( ! roh || roh.charAt( 0 ) === '#' || /^(mailto|tel|javascript):/i.test( roh ) ) return null;

        var u;
        try { u = new URL( roh, window.location.href ); } catch ( e ) { return null; }

        if ( u.origin !== window.location.origin ) return null;
        if ( u.pathname.indexOf( '/wp-admin' ) === 0 || u.pathname.indexOf( '/wp-login' ) === 0 ) return null;
        if ( u.searchParams.has( 'lang' ) ) return null;

        u.searchParams.set( 'lang', s.code );
        return u.href;
    }

    document.addEventListener( 'click', function ( e ) {
        var a = e.target && e.target.closest ? e.target.closest( 'a[href]' ) : null;
        if ( ! a ) return;

        var neu = mit( a.getAttribute( 'href' ) );
        if ( neu ) a.href = neu;
    }, true );

    document.addEventListener( 'submit', function ( e ) {
        var f = e.target;
        if ( ! ( f instanceof HTMLFormElement ) ) return;
        if ( ( f.getAttribute( 'method' ) || 'get' ).toLowerCase() !== 'get' ) return;
        if ( f.querySelector( 'input[name="lang"]' ) ) return;

        var ziel = f.getAttribute( 'action' ) || window.location.href;
        try {
            if ( new URL( ziel, window.location.href ).origin !== window.location.origin ) return;
        } catch ( err ) { return; }

        var feld = document.createElement( 'input' );
        feld.type = 'hidden';
        feld.name = 'lang';
        feld.value = s.code;
        f.appendChild( feld );
    }, true );
} )();
