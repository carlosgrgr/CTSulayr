/**
 * Ranking → Competición: añadir y quitar filas de resultados, sexo y grupo
 * del socio elegido, socios ya usados fuera de los desplegables, y confirmación
 * del borrado (SPEC-RANKING-INTERNO §4.2).
 * Sin JavaScript la tabla trae filas vacías y el servidor ya excluye a los socios
 * usados, así que todo funciona igual.
 */
( function () {
    'use strict';

    var table = document.getElementById( 'sulayr-ranking-results' );
    var template = document.getElementById( 'sulayr-ranking-row-template' );
    var addButton = document.getElementById( 'sulayr-ranking-add-row' );
    var master = null;

    // Lista completa de socios, sacada de la plantilla (que los trae a todos).
    if ( template ) {
        var probe = document.createElement( 'div' );
        probe.innerHTML = template.innerHTML;
        var base = probe.querySelector( '.sulayr-ranking-member' );
        if ( base ) {
            master = Array.prototype.slice.call( base.options ).map( function ( option ) {
                return option.cloneNode( true );
            } );
        }
    }

    function updateMeta( select ) {
        var row = select.closest( 'tr' );
        var target = row ? row.querySelector( '[data-meta-target]' ) : null;
        if ( ! target ) {
            return;
        }
        var option = select.options[ select.selectedIndex ];
        target.textContent = option && option.getAttribute( 'data-meta' ) ? option.getAttribute( 'data-meta' ) : '';
    }

    // Cada desplegable ofrece los socios que no están elegidos en otra fila.
    // Solo se reconstruye si la lista cambia, para no quitar el foco al que está eligiendo.
    function syncMembers() {
        if ( ! table || ! master ) {
            return;
        }
        var selects = Array.prototype.slice.call( table.querySelectorAll( 'select.sulayr-ranking-member' ) );
        selects.forEach( function ( select, index ) {
            var taken = {};
            selects.forEach( function ( other, j ) {
                if ( j !== index && other.value ) {
                    taken[ other.value ] = true;
                }
            } );
            var wanted = master.filter( function ( option ) {
                return '' === option.value || ! taken[ option.value ];
            } );
            var current = Array.prototype.slice.call( select.options ).map( function ( option ) {
                return option.value;
            } );
            var next = wanted.map( function ( option ) {
                return option.value;
            } );
            if ( current.join( '\n' ) === next.join( '\n' ) ) {
                return;
            }
            var keep = select.value;
            select.innerHTML = '';
            wanted.forEach( function ( option ) {
                select.appendChild( option.cloneNode( true ) );
            } );
            select.value = keep;
        } );
    }

    if ( table ) {
        table.addEventListener( 'change', function ( event ) {
            if ( event.target.classList.contains( 'sulayr-ranking-member' ) ) {
                updateMeta( event.target );
                syncMembers();
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
                syncMembers();
            }
        } );

        syncMembers();
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
            syncMembers();
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
