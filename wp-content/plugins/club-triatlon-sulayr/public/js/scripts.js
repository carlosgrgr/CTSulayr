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

        // Inicializar explícitamente cada pestaña para que el primer clic funcione
        var tabButtons = profileTabsContainer.querySelectorAll('button[data-bs-toggle="tab"]');
        if (tabButtons.length === 0) {
            return; // No hay botones de pestañas, salir
        }

        var panelsContainer = document.querySelector('.sulayr-profile-panels');
        if (!panelsContainer) {
            return;
        }

        /**
         * Deja solo el panel indicado como activo (evita que dos paneles queden visibles en el primer clic).
         * @param {string} targetSelector - Selector del panel a mostrar (ej. '#edit').
         */
        function setActivePanelOnly(targetSelector) {
            panelsContainer.querySelectorAll('.tab-pane').forEach(function(pane) {
                pane.classList.remove('active', 'show');
            });
            var activePane = document.querySelector(targetSelector);
            if (activePane) {
                activePane.classList.add('active', 'show');
            }
        }

        /**
         * Restaura la posición de scroll para evitar el salto al cambiar de pestaña.
         */
        var savedScroll = { x: 0, y: 0 };
        tabButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                savedScroll.x = window.scrollX;
                savedScroll.y = window.scrollY;
            }, true);
        });

        tabButtons.forEach(function(button) {
            try {
                new bootstrap.Tab(button);
            } catch (e) {
                console.warn('Club Triatlon Sulayr: Error al inicializar pestaña', e);
            }
        });

        // Tras cambiar de pestaña: un solo panel activo y restaurar scroll
        tabButtons.forEach(function(button) {
            var targetId = button.getAttribute('data-bs-target');
            if (!targetId) {
                return;
            }

            button.addEventListener('shown.bs.tab', function (event) {
                if (!event.target || !event.target.getAttribute) {
                    return;
                }

                var currentTargetId = event.target.getAttribute('data-bs-target');
                setActivePanelOnly(currentTargetId);

                // Evitar el scroll que produce Bootstrap al activar el panel
                requestAnimationFrame(function() {
                    window.scrollTo(savedScroll.x, savedScroll.y);
                });

                // Actualizar hash sin provocar scroll
                if (currentTargetId === '#edit') {
                    history.replaceState(null, '', window.location.pathname + window.location.search + '#edit');
                } else if (currentTargetId === '#info') {
                    history.replaceState(null, '', window.location.pathname + window.location.search);
                }
            });
        });
    }

    /**
     * Tarifa, tipo de entrenamiento y suplemento quedan visibles, sin desplegable.
     */
    function lockPlanFields() {
        var fields = document.querySelectorAll('.sulayr-profile-form .sulayr-field-locked select');
        fields.forEach(function(select) {
            select.disabled = true;
            select.setAttribute('aria-disabled', 'true');
        });
    }

    // Ejecutar solo cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProfileTabs);
        document.addEventListener('DOMContentLoaded', lockPlanFields);
    } else {
        // DOM ya está listo
        initProfileTabs();
        lockPlanFields();
    }

    if (window.acf && typeof window.acf.addAction === 'function') {
        window.acf.addAction('ready', lockPlanFields);
    }
})();