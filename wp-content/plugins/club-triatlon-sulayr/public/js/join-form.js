/**
 * Formulario de alta (público y de gestor). Solo ayuda a rellenarlo:
 * el servidor vuelve a comprobarlo todo.
 */
( function () {
    'use strict';

    function age( birth, on ) {
        var years = on.getFullYear() - birth.getFullYear();
        var m = on.getMonth() - birth.getMonth();
        if ( m < 0 || ( 0 === m && on.getDate() < birth.getDate() ) ) {
            years--;
        }
        return years;
    }

    function setup( form ) {
        var birth = form.querySelector( '[data-sulayr-birth]' );
        var start = form.querySelector( '[data-sulayr-start]' );
        var guardian = form.querySelector( '[data-sulayr-guardian]' );
        var services = form.querySelectorAll( '.sulayr-join-service' );

        function updateGuardian() {
            if ( ! guardian || ! birth ) {
                return;
            }
            var b = birth.value ? new Date( birth.value + 'T00:00:00' ) : null;
            var s = start && start.value ? new Date( start.value + 'T00:00:00' ) : new Date();
            var minor = b && ! isNaN( b ) && age( b, s ) < 18;
            guardian.hidden = ! minor;
            guardian.querySelectorAll( 'input' ).forEach( function ( input ) {
                input.required = !! minor;
            } );
        }

        function updateServices() {
            var chosen = [];
            services.forEach( function ( row ) {
                var box = row.querySelector( 'input[type=checkbox]' );
                if ( box && box.checked ) {
                    chosen.push( row.getAttribute( 'data-service' ) );
                }
            } );
            services.forEach( function ( row ) {
                var id = row.getAttribute( 'data-service' );
                var box = row.querySelector( 'input[type=checkbox]' );
                var incompatible = ( row.getAttribute( 'data-incompatible' ) || '' ).split( ',' ).filter( Boolean );
                var blocked = chosen.some( function ( other ) {
                    if ( other === id ) {
                        return false;
                    }
                    var otherRow = form.querySelector( '.sulayr-join-service[data-service="' + other + '"]' );
                    var otherIncompatible = otherRow ? ( otherRow.getAttribute( 'data-incompatible' ) || '' ).split( ',' ) : [];
                    return incompatible.indexOf( other ) !== -1 || otherIncompatible.indexOf( id ) !== -1;
                } );
                if ( box ) {
                    row.classList.toggle( 'is-blocked', blocked && ! box.checked );
                    if ( ! box.checked ) {
                        box.disabled = blocked;
                    }
                }
                var options = row.querySelector( '.sulayr-join-options' );
                if ( options ) {
                    var on = box && box.checked;
                    options.hidden = ! on;
                    var select = options.querySelector( 'select' );
                    if ( select ) {
                        select.required = !! on;
                    }
                }
            } );
        }

        if ( birth ) {
            birth.addEventListener( 'change', updateGuardian );
        }
        if ( start ) {
            start.addEventListener( 'change', updateGuardian );
        }
        services.forEach( function ( row ) {
            var box = row.querySelector( 'input[type=checkbox]' );
            if ( box ) {
                box.addEventListener( 'change', updateServices );
            }
        } );

        updateGuardian();
        updateServices();
    }

    document.addEventListener( 'DOMContentLoaded', function () {
        document.querySelectorAll( 'form.sulayr-join-form' ).forEach( setup );
    } );
}() );
