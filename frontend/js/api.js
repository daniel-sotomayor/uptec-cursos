/**
 * UPTEC - Sistema de Control de Cursos v2.0
 * API Client - Modulo de comunicacion con backend
 *
 * Maneja todas las peticiones HTTP con CSRF, auth y manejo de errores
 * Universidad Politecnica Territorial de Caracas Mariscal Sucre
 */

(function() {
    'use strict';

    // Inyección automática del Favicon / Logo en todo el frontend
    (function injectFavicon() {
        if (!document.querySelector("link[rel*='icon']")) {
            const link = document.createElement('link');
            link.rel = 'icon';
            link.type = 'image/png';
            const path = window.location.pathname;
            if (path.includes('/views/')) {
                link.href = '../../../frontend/img/logo-uptec.png';
            } else if (path.includes('/frontend/')) {
                link.href = 'img/logo-uptec.png';
            } else {
                link.href = '/img/logo-uptec.png';
            }
            document.head.appendChild(link);
        }
    })();


    // Configuracion base
    // Detección dinámica de la ruta base (soporta subcarpetas como /uptec-cursos o raíz virtualhost)
    const isSubfolder = window.location.pathname.toLowerCase().includes('/uptec-cursos');
    const APP_BASE = isSubfolder ? '/uptec-cursos' : '';
    const API_BASE = `${APP_BASE}/backend/api/api.php`;

    // Token CSRF en memoria
    let csrfToken = null;

    // Obtener token CSRF
    async function fetchCsrfToken() {
        try {
            const response = await fetch(`${API_BASE}?endpoint=csrf`, { credentials: 'include' });
            const data = await response.json();
            if (data.success && data.token) {
                csrfToken = data.token;
                // Actualizar meta tag si existe
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.content = csrfToken;
            }
        } catch (err) {
            console.error('[UPTEC] Error obteniendo CSRF token:', err);
        }
    }

    // Obtener token actual
    function getCsrfToken() {
        if (csrfToken) return csrfToken;
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    // Cliente API principal
    const API = {
        // Inicializar - obtener CSRF token
        async init() {
            await fetchCsrfToken();
        },

        // GET request
        async get(endpoint, params = {}) {
            const queryParams = new URLSearchParams({ endpoint, ...params });
            const response = await fetch(`${API_BASE}?${queryParams}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                credentials: 'include'
            });
            return this._handleResponse(response);
        },

        // POST request
        async post(endpoint, data = {}) {
            const body = typeof data === 'string' ? data : JSON.stringify(data);
            const response = await fetch(`${API_BASE}?endpoint=${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                body: body,
                credentials: 'include'
            });
            return this._handleResponse(response);
        },

        // PUT request
        async put(endpoint, id, data = {}) {
            const url = id ? `${API_BASE}?endpoint=${endpoint}&id=${id}` : `${API_BASE}?endpoint=${endpoint}`;
            const response = await fetch(url, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                body: JSON.stringify(data),
                credentials: 'include'
            });
            return this._handleResponse(response);
        },

        // DELETE request
        async delete(endpoint, id) {
            const response = await fetch(`${API_BASE}?endpoint=${endpoint}&id=${id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                credentials: 'include'
            });
            return this._handleResponse(response);
        },

        // Descargar archivo (para backup)
        async download(endpoint, params = {}, filename = 'download') {
            const queryParams = new URLSearchParams({ endpoint, ...params });
            const response = await fetch(`${API_BASE}?${queryParams}`, {
                method: 'GET',
                headers: { 'X-CSRF-Token': getCsrfToken() },
                credentials: 'include'
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.error || 'Error en la descarga');
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);
        },

        // Manejar respuesta
        async _handleResponse(response) {
            const text = await response.text();

            // Verificar si la respuesta es HTML (error del servidor)
            if (text.trim().startsWith('<')) {
                console.error('[UPTEC] Respuesta HTML recibida:', text.substring(0, 200));
                const error = new Error('Error del servidor. Revise la consola para mas detalles.');
                error.status = response.status;
                error.htmlResponse = true;
                throw error;
            }

            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('[UPTEC] Respuesta no es JSON valido:', text.substring(0, 200));
                throw new Error('Respuesta del servidor no valida');
            }

            // Si el token CSRF es invalido, intentar refrescar
            if (response.status === 403 && data.code === 'CSRF_INVALID') {
                await fetchCsrfToken();
            }

            if (!response.ok) {
                const error = new Error(data.error || 'Error en la peticion');
                error.status = response.status;
                error.data = data;
                throw error;
            }

            return data;
        }
    };

    // Exponer globalmente
    window.API = API;

})();
