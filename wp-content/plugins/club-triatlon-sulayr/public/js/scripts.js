/**
 * Club Triatlon Sulayr - Gestión de pestañas del perfil de usuario
 * Namespace: sulayrProfileTabs
 */
(function() {
    'use strict';

    /**
     * Inicializar las pestañas del perfil de usuario
     * Solo se ejecuta si existe el contenedor de pestañas específico
     */
    function initProfileTabs() {
        // Verificar que existe el contenedor de pestañas específico del perfil
        var profileTabsContainer = document.getElementById('profileTabs');
        if (!profileTabsContainer) {
            return; // No está en la página de perfil, salir
        }

        // Verificar que Bootstrap está disponible
        if (typeof bootstrap === 'undefined' || !bootstrap.Tab) {
            console.warn('Club Triatlon Sulayr: Bootstrap no está disponible');
            return;
        }

        // Verificar que existe el botón de edición
        var editTabButton = document.getElementById('edit-tab');
        if (!editTabButton) {
            return; // No existe el botón, salir
        }

        // Manejar hash en la URL al cargar la página
        if (window.location.hash === '#edit') {
            try {
                var editTab = new bootstrap.Tab(editTabButton);
                editTab.show();
            } catch (e) {
                console.warn('Club Triatlon Sulayr: Error al activar pestaña de edición', e);
            }
        }

        // Actualizar el hash cuando se cambia de pestaña
        // Solo seleccionar botones dentro del contenedor específico
        var tabButtons = profileTabsContainer.querySelectorAll('button[data-bs-toggle="tab"]');
        if (tabButtons.length === 0) {
            return; // No hay botones de pestañas, salir
        }

        tabButtons.forEach(function(button) {
            // Verificar que el botón tiene el atributo data-bs-target
            var targetId = button.getAttribute('data-bs-target');
            if (!targetId) {
                return; // Saltar este botón si no tiene target
            }

            button.addEventListener('shown.bs.tab', function (event) {
                // Verificar que el evento tiene un target válido
                if (!event.target || !event.target.getAttribute) {
                    return;
                }

                var currentTargetId = event.target.getAttribute('data-bs-target');
                
                // Solo actualizar el hash si el target es #edit o #info
                if (currentTargetId === '#edit') {
                    window.location.hash = 'edit';
                } else if (currentTargetId === '#info') {
                    // Limpiar el hash solo si estamos en la pestaña de información
                    if (window.location.hash === '#edit') {
                        window.location.hash = '';
                    }
                }
            });
        });
    }

    // Ejecutar solo cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProfileTabs);
    } else {
        // DOM ya está listo
        initProfileTabs();
    }
})();