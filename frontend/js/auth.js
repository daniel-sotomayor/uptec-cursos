/**
 * UPTEC - Sistema de Control de Cursos v2.0
 * Modulo de Autenticacion - Frontend
 *
 * Maneja login, registro, sesion, redireccion por rol
 */

(function() {
    'use strict';

    const AUTH = {
        // Usuario actual en cache
        user: null,

        // Verificar si hay sesion activa
        async check() {
            try {
                const data = await API.get('me');
                if (data.success && data.user) {
                    this.user = data.user;
                    return true;
                }
                return false;
            } catch (err) {
                return false;
            }
        },

        // Obtener usuario actual
        getUser() {
            return this.user;
        },

        // Obtener rol
        getRole() {
            return this.user ? this.user.rol : null;
        },

        // Login
        async login(email, password) {
            const data = await API.post('login', { email, password });
            if (data.success && data.user) {
                this.user = data.user;
                // Guardar token CSRF
                if (data.token) {
                    const meta = document.querySelector('meta[name="csrf-token"]');
                    if (meta) meta.content = data.token;
                }
                // Redireccionar segun rol
                if (data.redirect) {
                    window.location.href = data.redirect;
                }
                return data;
            }
            throw new Error(data.error || 'Error en login');
        },

        // Registro
        async register(userData) {
            const data = await API.post('register', userData);
            return data;
        },

        // Logout
        async logout() {
            try {
                await API.post('logout', {});
            } catch (e) {
                // Ignorar error
            }
            this.user = null;
            window.location.href = '/login.html';
        },

        // Cambiar password
        async changePassword(currentPassword, newPassword) {
            return await API.put('password', null, { current_password: currentPassword, new_password: newPassword });
        },

        // Verificar permisos
        hasRole(roles) {
            if (!this.user) return false;
            const roleHierarchy = {
                'Participante': 1,
                'Facilitador': 2,
                'Analista': 3,
                'Administrador': 4
            };
            const userLevel = roleHierarchy[this.user.rol] || 0;

            if (Array.isArray(roles)) {
                return roles.some(r => (roleHierarchy[r] || 0) <= userLevel);
            }
            return (roleHierarchy[roles] || 0) <= userLevel;
        },

        isAdmin() {
            return this.user && this.user.rol === 'Administrador';
        },

        isAnalyst() {
            return this.user && this.user.rol === 'Analista';
        },

        isTeacher() {
            return this.user && this.user.rol === 'Facilitador';
        },

        isStudent() {
            return this.user && this.user.rol === 'Participante';
        }
    };

    // Exponer globalmente
    window.AUTH = AUTH;

})();
