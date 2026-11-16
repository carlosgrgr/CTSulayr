/**
 * Ranking → Competición: añadir y quitar filas de resultados, sexo y grupo
 * del socio elegido, y confirmación del borrado (SPEC-RANKING-INTERNO §4.2).
 * Sin JavaScript la tabla trae filas vacías y todo funciona igual.
 */
( function () {
    'use strict';

    var table = document.getElementById( 'sulayr-ranking-results' );
    var template = document.getElementById( 'sulayr-ranking-row-template' );
    var addButton = document.getElementById( 'sulayr-ranking-add-row' );

    function updateMeta( select ) {
        var row = select.closest( 'tr' );
        var target = row ? row.querySelector( '[data-meta-target]' ) : null;
        if ( ! target ) {
            return;
        }
        var option = select.options[ select.selectedIndex ];
        target.textContent = option && option.getAttribute( 'data-meta' ) ? option.getAttribute( 'data-meta' ) : '';
    }

    if ( table ) {
        table.addEventListener( 'change', function ( event ) {
            if ( event.target.classList.contains( 'sulayr-ranking-member' ) ) {
                updateMeta( event.target );
            }
        } );

        table.addEventListener( 'click', function ( event ) {
            if ( ! event.target.classList.contains( 'sulayr-ranking-remove' ) ) {
                return;
            }
            event.preventDefault();
            var row = event.target.closest( 'tr' );
            if ( row ) {
                row.parentNode.removeChild( row );
            }
        } );
    }

    if ( table && template && addButton ) {
        addButton.addEventListener( 'click', function () {
            var next = parseInt( addButton.getAttribute( 'data-next' ), 10 ) || 0;
            var html = template.innerHTML.replace( /__i__/g, String( next ) );
            var tbody = table.querySelector( 'tbody' );
            var holder = document.createElement( 'tbody' );
            holder.innerHTML = html;
            while ( holder.firstChild ) {
                tbody.appendChild( holder.firstChild );
            }
            addButton.setAttribute( 'data-next', String( next + 1 ) );
            var select = tbody.lastElementChild ? tbody.lastElementChild.querySelector( '.sulayr-ranking-member' ) : null;
            if ( select ) {
                select.focus();
            }
        } );
    }

    document.addEventListener( 'click', function ( event ) {
        var message = event.target.getAttribute && event.target.getAttribute( 'data-sulayr-confirm' );
        if ( message && ! window.confirm( message ) ) {
            event.preventDefault();
        }
    } );
}() );
