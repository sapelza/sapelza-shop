/*
 * QR-Erzeugung, im Browser, ohne Fremdcode.
 *
 * Nur so viel, wie die Regaletiketten brauchen: Byte-Modus, Fehlerklasse M,
 * Fassungen 1 bis 10. Das reicht fuer rund 200 Zeichen — eine Adresse wie
 * https://sapelzashop.com/schnellerfassung/?nr=SP-K-10 hat 51.
 *
 * Kein Fremddienst: bei einem externen QR-Erzeuger wandern Ihre Adressen
 * ueber dessen Server, und wenn er abgeschaltet wird, drucken Sie leere
 * Etiketten.
 *
 * WICHTIG: am Ende steht eine Selbstpruefung (SZQR.pruefen). Sie prueft
 * die Bauteile, die man von aussen nicht sieht — Format-Bits, Suchmuster,
 * Taktlinien. Bevor ein ganzer Bogen gedruckt wird, sollte trotzdem EIN
 * Etikett vom Bildschirm gescannt werden. Ein falscher Code faellt sonst
 * erst am Regal auf.
 */
window.SZQR = ( function () {
    'use strict';

    /* --- Rechnen im Galoisfeld GF(256) -------------------------------- */

    var EXP = new Uint8Array( 512 );
    var LOG = new Uint8Array( 256 );

    ( function () {
        var x = 1;
        for ( var i = 0; i < 255; i++ ) {
            EXP[ i ] = x;
            LOG[ x ] = i;
            x <<= 1;
            if ( x & 0x100 ) x ^= 0x11d;   /* das Standardpolynom */
        }
        for ( var j = 255; j < 512; j++ ) EXP[ j ] = EXP[ j - 255 ];
    } )();

    function mal( a, b ) {
        if ( a === 0 || b === 0 ) return 0;
        return EXP[ LOG[ a ] + LOG[ b ] ];
    }

    /* Das Generatorpolynom fuer n Fehlerkorrekturstellen. */
    function generator( n ) {
        var g = [ 1 ];
        for ( var i = 0; i < n; i++ ) {
            var neu = new Array( g.length + 1 ).fill( 0 );
            for ( var j = 0; j < g.length; j++ ) {
                neu[ j ] ^= mal( g[ j ], EXP[ i ] );
                neu[ j + 1 ] ^= g[ j ];
            }
            g = neu;
        }
        return g;
    }

    function fehlerstellen( daten, n ) {
        var g = generator( n );
        var rest = daten.concat( new Array( n ).fill( 0 ) );

        for ( var i = 0; i < daten.length; i++ ) {
            var f = rest[ i ];
            if ( f === 0 ) continue;
            for ( var j = 0; j < g.length; j++ ) rest[ i + j ] ^= mal( g[ j ], f );
        }

        return rest.slice( daten.length );
    }

    /* --- Was jede Fassung fasst ---------------------------------------
       [ Gesamtstellen, Fehlerstellen je Block, Bloecke Gruppe 1,
         Bloecke Gruppe 2 ] fuer Fehlerklasse M. */

    var FASSUNGEN = {
        1:  [ 26,  10, 1, 0 ],
        2:  [ 44,  16, 1, 0 ],
        3:  [ 70,  26, 1, 0 ],
        4:  [ 100, 18, 2, 0 ],
        5:  [ 134, 24, 2, 0 ],
        6:  [ 172, 16, 4, 0 ],
        7:  [ 196, 18, 4, 0 ],
        8:  [ 242, 22, 2, 2 ],
        9:  [ 292, 22, 3, 2 ],
        10: [ 346, 26, 4, 1 ]
    };

    /* Lagen der Ausrichtungsmuster je Fassung. */
    var AUSRICHTUNG = {
        1: [], 2: [ 6, 18 ], 3: [ 6, 22 ], 4: [ 6, 26 ], 5: [ 6, 30 ],
        6: [ 6, 34 ], 7: [ 6, 22, 38 ], 8: [ 6, 24, 42 ],
        9: [ 6, 26, 46 ], 10: [ 6, 28, 50 ]
    };

    function datenstellen( fassung ) {
        var f = FASSUNGEN[ fassung ];
        return f[ 0 ] - f[ 1 ] * ( f[ 2 ] + f[ 3 ] );
    }

    /* Byte-Modus: 8 Bit Laengenfeld bis Fassung 9, danach 16. */
    function laengenbits( fassung ) { return fassung < 10 ? 8 : 16; }

    function passendeFassung( anzahlBytes ) {
        for ( var v = 1; v <= 10; v++ ) {
            var bits = datenstellen( v ) * 8;
            if ( bits >= 4 + laengenbits( v ) + anzahlBytes * 8 ) return v;
        }
        return 0;   /* laenger nehmen wir bewusst nicht an */
    }

    /* --- Bitstrom ------------------------------------------------------ */

    function bitstrom( text, fassung ) {
        var bytes = [];
        for ( var i = 0; i < text.length; i++ ) {
            var c = text.charCodeAt( i );
            /* Reine ASCII-Adressen; alles darueber wird UTF-8 kodiert. */
            if ( c < 128 ) bytes.push( c );
            else if ( c < 2048 ) { bytes.push( 192 | ( c >> 6 ), 128 | ( c & 63 ) ); }
            else { bytes.push( 224 | ( c >> 12 ), 128 | ( ( c >> 6 ) & 63 ), 128 | ( c & 63 ) ); }
        }

        var bits = [];
        function schieben( wert, anzahl ) {
            for ( var k = anzahl - 1; k >= 0; k-- ) bits.push( ( wert >> k ) & 1 );
        }

        schieben( 4, 4 );                                   /* Byte-Modus */
        schieben( bytes.length, laengenbits( fassung ) );
        bytes.forEach( function ( b ) { schieben( b, 8 ); } );

        var platz = datenstellen( fassung ) * 8;
        var ende = Math.min( 4, platz - bits.length );
        schieben( 0, ende );                                /* Abschluss */
        while ( bits.length % 8 ) bits.push( 0 );

        var stellen = [];
        for ( var p = 0; p < bits.length; p += 8 ) {
            var w = 0;
            for ( var q = 0; q < 8; q++ ) w = ( w << 1 ) | bits[ p + q ];
            stellen.push( w );
        }

        /* Auffuellen mit den vorgeschriebenen Fuellbytes. */
        var fuell = [ 0xec, 0x11 ], z = 0;
        while ( stellen.length < datenstellen( fassung ) ) stellen.push( fuell[ z++ % 2 ] );

        return stellen;
    }

    /* --- Bloecke verschraenken ----------------------------------------- */

    function stellenfolge( daten, fassung ) {
        var f = FASSUNGEN[ fassung ];
        var ecJeBlock = f[ 1 ], g1 = f[ 2 ], g2 = f[ 3 ];
        var gesamtBloecke = g1 + g2;
        var proBlock1 = Math.floor( daten.length / gesamtBloecke );

        var bloecke = [], ec = [], pos = 0;
        for ( var b = 0; b < gesamtBloecke; b++ ) {
            var laenge = proBlock1 + ( b >= g1 ? 1 : 0 );
            var teil = daten.slice( pos, pos + laenge );
            pos += laenge;
            bloecke.push( teil );
            ec.push( fehlerstellen( teil, ecJeBlock ) );
        }

        var raus = [], i;
        var maxD = Math.max.apply( null, bloecke.map( function ( x ) { return x.length; } ) );
        for ( i = 0; i < maxD; i++ ) {
            bloecke.forEach( function ( bl ) { if ( i < bl.length ) raus.push( bl[ i ] ); } );
        }
        for ( i = 0; i < ecJeBlock; i++ ) {
            ec.forEach( function ( e ) { raus.push( e[ i ] ); } );
        }

        return raus;
    }

    /* --- BCH fuer Format- und Fassungsangabe ---------------------------- */

    function bch( wert, g, stellen ) {
        var rest = wert << ( stellen - 1 );
        var grad = 0, t = g;
        while ( t ) { grad++; t >>= 1; }

        var laenge = 0, u = rest;
        while ( u ) { laenge++; u >>= 1; }
        while ( laenge >= grad ) {
            rest ^= g << ( laenge - grad );
            laenge = 0; u = rest;
            while ( u ) { laenge++; u >>= 1; }
        }
        return rest;
    }

    function formatbits( maske ) {
        /* Fehlerklasse M ist 00 in der Formatangabe. */
        var wert = ( 0 << 3 ) | maske;
        return ( ( wert << 10 ) | bch( wert, 0x537, 11 ) ) ^ 0x5412;
    }

    function fassungsbits( fassung ) {
        return ( fassung << 12 ) | bch( fassung, 0x1f25, 13 );
    }

    /* --- Das Feld ------------------------------------------------------- */

    function leeresFeld( groesse ) {
        var f = [];
        for ( var i = 0; i < groesse; i++ ) f.push( new Array( groesse ).fill( null ) );
        return f;
    }

    function suchmuster( feld, x, y ) {
        for ( var dy = -1; dy <= 7; dy++ ) {
            for ( var dx = -1; dx <= 7; dx++ ) {
                var px = x + dx, py = y + dy;
                if ( px < 0 || py < 0 || px >= feld.length || py >= feld.length ) continue;
                var innen = dx >= 0 && dx <= 6 && dy >= 0 && dy <= 6;
                var ring  = dx === 0 || dx === 6 || dy === 0 || dy === 6;
                var kern  = dx >= 2 && dx <= 4 && dy >= 2 && dy <= 4;
                feld[ py ][ px ] = innen && ( ring || kern ) ? 1 : 0;
            }
        }
    }

    function bauen( text ) {
        var bytes = 0;
        for ( var i = 0; i < text.length; i++ ) {
            var c = text.charCodeAt( i );
            bytes += c < 128 ? 1 : ( c < 2048 ? 2 : 3 );
        }

        var fassung = passendeFassung( bytes );
        if ( ! fassung ) throw new Error( 'Text zu lang für dieses Etikett.' );

        var groesse = 17 + fassung * 4;
        var feld = leeresFeld( groesse );

        /* Suchmuster in drei Ecken. */
        suchmuster( feld, 0, 0 );
        suchmuster( feld, groesse - 7, 0 );
        suchmuster( feld, 0, groesse - 7 );

        /* Taktlinien. */
        for ( var t = 8; t < groesse - 8; t++ ) {
            feld[ 6 ][ t ] = t % 2 === 0 ? 1 : 0;
            feld[ t ][ 6 ] = t % 2 === 0 ? 1 : 0;
        }

        /* Ausrichtungsmuster, ausser wo sie Suchmuster ueberdecken. */
        var lagen = AUSRICHTUNG[ fassung ];
        lagen.forEach( function ( ax ) {
            lagen.forEach( function ( ay ) {
                var beiSuch = ( ax <= 8 && ay <= 8 ) ||
                              ( ax >= groesse - 9 && ay <= 8 ) ||
                              ( ax <= 8 && ay >= groesse - 9 );
                if ( beiSuch ) return;
                for ( var dy = -2; dy <= 2; dy++ ) {
                    for ( var dx = -2; dx <= 2; dx++ ) {
                        var rand = Math.max( Math.abs( dx ), Math.abs( dy ) );
                        feld[ ay + dy ][ ax + dx ] = ( rand === 1 ) ? 0 : 1;
                    }
                }
            } );
        } );

        /* Die dunkle Stelle. */
        feld[ 4 * fassung + 9 ][ 8 ] = 1;

        /* Plaetze fuer die Formatangabe freihalten. */
        for ( var k = 0; k < 9; k++ ) {
            if ( feld[ 8 ][ k ] === null ) feld[ 8 ][ k ] = 2;
            if ( feld[ k ][ 8 ] === null ) feld[ k ][ 8 ] = 2;
        }
        for ( var m = 0; m < 8; m++ ) {
            if ( feld[ 8 ][ groesse - 1 - m ] === null ) feld[ 8 ][ groesse - 1 - m ] = 2;
            if ( feld[ groesse - 1 - m ][ 8 ] === null ) feld[ groesse - 1 - m ][ 8 ] = 2;
        }

        /* Fassungsangabe ab Fassung 7. */
        if ( fassung >= 7 ) {
            var fb = fassungsbits( fassung );
            for ( var b = 0; b < 18; b++ ) {
                var bit = ( fb >> b ) & 1;
                var r = Math.floor( b / 3 ), c = b % 3;
                feld[ r ][ groesse - 11 + c ] = bit;
                feld[ groesse - 11 + c ][ r ] = bit;
            }
        }

        /*
         * Musterkarte JETZT merken — vor dem Einlegen der Daten.
         *
         * Danach waere jede Stelle belegt und alles gaelte als Muster;
         * es wuerde ueberhaupt nicht maskiert, und ein QR ohne
         * Maskierung ist unlesbar.
         */
        var muster = feld.map( function ( z ) {
            return z.map( function ( v ) { return v === null ? 0 : 1; } );
        } );

        /* Daten einlegen, im Zickzack von rechts unten. */
        var folge = stellenfolge( bitstrom( text, fassung ), fassung );
        var bits = [];
        folge.forEach( function ( w ) {
            for ( var s = 7; s >= 0; s-- ) bits.push( ( w >> s ) & 1 );
        } );

        var zeiger = 0, aufwaerts = true;
        for ( var spalte = groesse - 1; spalte > 0; spalte -= 2 ) {
            if ( spalte === 6 ) spalte--;   /* die senkrechte Taktlinie */
            for ( var schritt = 0; schritt < groesse; schritt++ ) {
                var zeile = aufwaerts ? groesse - 1 - schritt : schritt;
                for ( var versatz = 0; versatz < 2; versatz++ ) {
                    var sp = spalte - versatz;
                    if ( feld[ zeile ][ sp ] !== null ) continue;
                    feld[ zeile ][ sp ] = zeiger < bits.length ? bits[ zeiger++ ] : 0;
                }
            }
            aufwaerts = ! aufwaerts;
        }

        return { feld: feld, groesse: groesse, fassung: fassung, muster: muster };
    }

    /* --- Maskieren und bewerten ------------------------------------------ */

    var MASKEN = [
        function ( r, c ) { return ( r + c ) % 2 === 0; },
        function ( r )    { return r % 2 === 0; },
        function ( r, c ) { return c % 3 === 0; },
        function ( r, c ) { return ( r + c ) % 3 === 0; },
        function ( r, c ) { return ( Math.floor( r / 2 ) + Math.floor( c / 3 ) ) % 2 === 0; },
        function ( r, c ) { return ( r * c ) % 2 + ( r * c ) % 3 === 0; },
        function ( r, c ) { return ( ( r * c ) % 2 + ( r * c ) % 3 ) % 2 === 0; },
        function ( r, c ) { return ( ( r + c ) % 2 + ( r * c ) % 3 ) % 2 === 0; }
    ];

    function anwenden( roh, maske ) {
        var g = roh.groesse;
        var feld = roh.feld.map( function ( z ) { return z.slice(); } );

        /* Formatangabe setzen. */
        var fb = formatbits( maske );
        for ( var i = 0; i < 15; i++ ) {
            var bit = ( fb >> i ) & 1;
            if ( i < 6 )        feld[ i ][ 8 ] = bit;
            else if ( i === 6 ) feld[ 7 ][ 8 ] = bit;
            else if ( i === 7 ) feld[ 8 ][ 8 ] = bit;
            else if ( i === 8 ) feld[ 8 ][ 7 ] = bit;
            else                feld[ 8 ][ 14 - i ] = bit;

            if ( i < 8 ) feld[ 8 ][ g - 1 - i ] = bit;
            else         feld[ g - 15 + i ][ 8 ] = bit;
        }

        /* Nur Datenstellen maskieren — Muster bleiben, wie sie sind. */
        for ( var r = 0; r < g; r++ ) {
            for ( var c = 0; c < g; c++ ) {
                if ( roh.feld[ r ][ c ] === null || roh.feld[ r ][ c ] === 2 ) continue;
                if ( istMuster( roh, r, c ) ) continue;
                if ( MASKEN[ maske ]( r, c ) ) feld[ r ][ c ] ^= 1;
            }
        }

        /* Was als Platzhalter (2) uebrig blieb, ist hell. */
        for ( var rr = 0; rr < g; rr++ ) {
            for ( var cc = 0; cc < g; cc++ ) if ( feld[ rr ][ cc ] === 2 ) feld[ rr ][ cc ] = 0;
        }

        return feld;
    }

    /* Muster sind alles, was VOR dem Einlegen der Daten gesetzt wurde. */
    function istMuster( roh, r, c ) {
        return roh.muster[ r ][ c ] === 1;
    }

    function strafe( feld ) {
        var g = feld.length, s = 0, r, c, i;

        /* Regel 1: fuenf und mehr gleiche in Reihe. */
        function reihen( holen ) {
            for ( r = 0; r < g; r++ ) {
                var lauf = 1;
                for ( c = 1; c < g; c++ ) {
                    if ( holen( r, c ) === holen( r, c - 1 ) ) lauf++;
                    else { if ( lauf >= 5 ) s += 3 + ( lauf - 5 ); lauf = 1; }
                }
                if ( lauf >= 5 ) s += 3 + ( lauf - 5 );
            }
        }
        reihen( function ( a, b ) { return feld[ a ][ b ]; } );
        reihen( function ( a, b ) { return feld[ b ][ a ]; } );

        /* Regel 2: gleichfarbige Zweierfelder. */
        for ( r = 0; r < g - 1; r++ ) {
            for ( c = 0; c < g - 1; c++ ) {
                var v = feld[ r ][ c ];
                if ( v === feld[ r ][ c + 1 ] && v === feld[ r + 1 ][ c ] && v === feld[ r + 1 ][ c + 1 ] ) s += 3;
            }
        }

        /* Regel 3: das suchmusteraehnliche Muster. */
        var muster = [ 1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0 ];
        function sucheMuster( holen ) {
            for ( r = 0; r < g; r++ ) {
                for ( c = 0; c <= g - 11; c++ ) {
                    var treffer = true, rueck = true;
                    for ( i = 0; i < 11; i++ ) {
                        if ( holen( r, c + i ) !== muster[ i ] ) treffer = false;
                        if ( holen( r, c + i ) !== muster[ 10 - i ] ) rueck = false;
                    }
                    if ( treffer || rueck ) s += 40;
                }
            }
        }
        sucheMuster( function ( a, b ) { return feld[ a ][ b ]; } );
        sucheMuster( function ( a, b ) { return feld[ b ][ a ]; } );

        /* Regel 4: Verhaeltnis dunkel zu hell. */
        var dunkel = 0;
        for ( r = 0; r < g; r++ ) for ( c = 0; c < g; c++ ) dunkel += feld[ r ][ c ];
        var anteil = dunkel * 100 / ( g * g );
        s += Math.floor( Math.abs( anteil - 50 ) / 5 ) * 10;

        return s;
    }

    /* --- Nach aussen ------------------------------------------------------ */

    function erzeugen( text ) {
        var roh = bauen( text );

        var bestes = null, besteStrafe = Infinity;
        for ( var m = 0; m < 8; m++ ) {
            var feld = anwenden( roh, m );
            var p = strafe( feld );
            if ( p < besteStrafe ) { besteStrafe = p; bestes = feld; }
        }

        return { feld: bestes, groesse: roh.groesse, fassung: roh.fassung };
    }

    /**
     * Der Code als SVG-Zeichenkette.
     *
     * Ohne Rand waere er von vielen Kameras nicht lesbar — die vier
     * Module Ruhezone gehoeren dazu.
     */
    function svg( text, groesseInMm ) {
        var q = erzeugen( text );
        var rand = 4;
        var n = q.groesse + rand * 2;

        var pfad = '';
        for ( var r = 0; r < q.groesse; r++ ) {
            for ( var c = 0; c < q.groesse; c++ ) {
                if ( q.feld[ r ][ c ] ) {
                    pfad += 'M' + ( c + rand ) + ' ' + ( r + rand ) + 'h1v1h-1z';
                }
            }
        }

        var mm = groesseInMm ? ' width="' + groesseInMm + 'mm" height="' + groesseInMm + 'mm"' : '';
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + n + ' ' + n + '"' + mm +
               ' shape-rendering="crispEdges" role="img">' +
               '<rect width="' + n + '" height="' + n + '" fill="#fff"/>' +
               '<path d="' + pfad + '" fill="#000"/></svg>';
    }

    /**
     * Selbstpruefung.
     *
     * Prueft die Bauteile, die man von aussen nicht sieht. Faellt eine
     * Pruefung durch, stimmt der Code sicher nicht — besteht sie, ist er
     * wahrscheinlich richtig. Sicher weiss man es erst, wenn ein Etikett
     * vom Bildschirm gescannt wurde.
     */
    function pruefen() {
        var funde = [];
        var q = erzeugen( 'https://sapelzashop.com/schnellerfassung/?nr=SP-K-10' );
        var f = q.feld, g = q.groesse;

        if ( g !== 17 + q.fassung * 4 ) funde.push( 'Feldgroesse passt nicht zur Fassung' );

        /* Suchmuster: die drei Ecken muessen den dunklen Ring tragen. */
        [ [ 0, 0 ], [ g - 7, 0 ], [ 0, g - 7 ] ].forEach( function ( e ) {
            if ( f[ e[ 1 ] ][ e[ 0 ] ] !== 1 || f[ e[ 1 ] + 3 ][ e[ 0 ] + 3 ] !== 1 ) {
                funde.push( 'Suchmuster bei ' + e.join( ',' ) + ' fehlerhaft' );
            }
        } );

        /* Taktlinien wechseln sich ab. */
        for ( var t = 8; t < g - 8; t++ ) {
            if ( f[ 6 ][ t ] !== ( t % 2 === 0 ? 1 : 0 ) ) { funde.push( 'Taktlinie waagrecht gestoert' ); break; }
        }

        /* Die dunkle Stelle. */
        if ( f[ 4 * q.fassung + 9 ][ 8 ] !== 1 ) funde.push( 'Dunkle Stelle fehlt' );

        /* Format-BCH muss sich zurueckrechnen lassen. */
        for ( var m = 0; m < 8; m++ ) {
            var fb = formatbits( m ) ^ 0x5412;
            if ( ( fb >> 10 ) !== m ) { funde.push( 'Formatangabe fuer Maske ' + m + ' falsch' ); }
        }

        return funde;
    }

    return { svg: svg, erzeugen: erzeugen, pruefen: pruefen };
} )();
