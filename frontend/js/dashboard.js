/**
 * UPTEC - Sistema de Control de Cursos v2.0
 * Dashboard Core - Funcionalidades compartidas de vistas
 *
 * Maneja sidebar, header, notificaciones, modales, tabla de datos y catálogo de iconos SVG
 */

// ============================================
// Inyección dinámica y limpia del Favicon
// ============================================
(function autoInjectFavicon() {
    if (document.querySelector("link[rel*='icon']")) return;

    const path = window.location.pathname;
    let pathToImg = 'img/logo-uptec.png';

    if (path.includes('/views/')) {
        pathToImg = '../../img/logo-uptec.png';
    }

    const link = document.createElement('link');
    link.type = 'image/png';
    link.rel = 'icon';
    link.href = pathToImg;

    document.head.appendChild(link);
})();

// ============================================
// CATÁLOGO DE ICONOS SVG VECTORIALES (ZERO-DEPENDENCY)
// ============================================
const Icons = {
    // Generador base de SVG con tamaño configurable
    svg: (paths, size = 20, extraClass = '') => `
        <svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="${extraClass}">
            ${paths}
        </svg>
    `,

    // Iconos de navegación y acciones
    dashboard: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>',
    usuarios: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    egresados: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>',
    cursos: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M6 6h10M6 10h10"/></svg>',
    inscripciones: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>',
    calificaciones: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/></svg>',
    reportes: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/></svg>',
    auditoria: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
    respaldo: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg>',
    facilitadores: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
    verificacion: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>',
    certificados: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>',

    // Iconos de apoyo visual para estados vacíos y diálogos
    alerta: (size = 48) => `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    vacio: (size = 48) => `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>`
};

(function() {
    'use strict';

    // ============================================
    // TOAST NOTIFICATIONS
    // ============================================
    const Toast = {
        container: null,

        init() {
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.className = 'toast-container';
                document.body.appendChild(this.container);
            }
        },

        show(message, type = 'success', duration = 4000) {
            this.init();

            const icons = {
                success: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
                error: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
                warning: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
                info: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
            };

            const toast = document.createElement('div');
            toast.className = `toast toast--${type}`;
            toast.innerHTML = `
                <span class="alert__icon">${icons[type]}</span>
                <span class="alert__text">${message}</span>
            `;

            this.container.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease-out forwards';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
    };

    // ============================================
    // MODAL
    // ============================================
    const Modal = {
        open(content, options = {}) {
            const size = options.size || '';
            const title = options.title || '';

            this.close();

            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay active';
            overlay.id = 'active-modal';
            overlay.innerHTML = `
                <div class="modal ${size}">
                    <div class="modal__header">
                        <h3 class="modal__title">${title}</h3>
                        <button class="modal__close" onclick="Modal.close()">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="modal__body">${content}</div>
                    ${options.footer ? `<div class="modal__footer">${options.footer}</div>` : ''}
                </div>
            `;

            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) this.close();
            });

            document.body.appendChild(overlay);
            document.body.style.overflow = 'hidden';
        },

        close() {
            const modal = document.getElementById('active-modal');
            if (modal) {
                modal.remove();
                document.body.style.overflow = '';
            }
        }
    };

    // ============================================
    // CONFIRM DIALOG
    // ============================================
    const ConfirmDialog = {
        open(message, onConfirm, onCancel) {
            const content = `
                <div class="text-center" style="padding: 1.5rem 0;">
                    <div style="margin-bottom: 1rem; display: flex; justify-content: center;">
                        ${Icons.alerta(48)}
                    </div>
                    <p style="font-size: 1.0625rem; color: var(--color-text-primary); font-weight: 500;">${message}</p>
                </div>
            `;

            const footer = `
                <button class="btn btn--ghost" onclick="Modal.close(); ${onCancel ? 'window._onCancelFn()' : ''}">Cancelar</button>
                <button class="btn btn--danger" id="confirm-btn">Confirmar</button>
            `;

            if (onCancel) window._onCancelFn = onCancel;

            Modal.open(content, { title: 'Confirmar acción', footer });

            setTimeout(() => {
                const btn = document.getElementById('confirm-btn');
                if (btn) {
                    btn.addEventListener('click', () => {
                        Modal.close();
                        if (onConfirm) onConfirm();
                    });
                }
            }, 50);
        }
    };

    // ============================================
    // SIDEBAR CON ICONOS SVG EMBEBIDOS
    // ============================================
    // Detección dinámica de la ruta base
    const isSubfolder = window.location.pathname.toLowerCase().includes('/uptec-cursos');
    const APP_BASE = isSubfolder ? '/uptec-cursos' : '';

    function resolveUrl(url) {
        if (!url) return url;
        if (APP_BASE && url.startsWith('/') && !url.startsWith(APP_BASE)) {
            return `${APP_BASE}${url}`;
        }
        return url;
    }

    const Sidebar = {
        init(rol) {
            const sidebar = document.getElementById('sidebar');
            if (!sidebar) return;

            const menus = {
                'Administrador': [
                    { label: 'Dashboard', icon: Icons.dashboard, href: '/frontend/views/administrador/dashboard.html', section: 'Principal' },
                    { label: 'Usuarios', icon: Icons.usuarios, href: '/frontend/views/administrador/usuarios.html', section: 'Gestión' },
                    { label: 'Egresados', icon: Icons.egresados, href: '/frontend/views/administrador/egresados.html', section: 'Gestión' },
                    { label: 'Cursos', icon: Icons.cursos, href: '/frontend/views/administrador/cursos.html' },
                    { label: 'Inscripciones', icon: Icons.inscripciones, href: '/frontend/views/administrador/inscripciones.html' },
                    { label: 'Calificaciones', icon: Icons.calificaciones, href: '/frontend/views/administrador/calificaciones.html' },
                    { label: 'Reportes', icon: Icons.reportes, href: '/frontend/views/administrador/reportes.html', section: 'Reportes' },
                    { label: 'Auditoría', icon: Icons.auditoria, href: '/frontend/views/administrador/auditoria.html' },
                    { label: 'Respaldo', icon: Icons.respaldo, href: '/frontend/views/administrador/backup.html' }
                ],
                'Analista': [
                    { label: 'Dashboard', icon: Icons.dashboard, href: '/frontend/views/analista/dashboard.html', section: 'Principal' },
                    { label: 'Cursos', icon: Icons.cursos, href: '/frontend/views/analista/cursos.html', section: 'Gestión' },
                    { label: 'Egresados', icon: Icons.egresados, href: '/frontend/views/analista/egresados.html', section: 'Gestión' },
                    { label: 'Facilitadores', icon: Icons.facilitadores, href: '/frontend/views/analista/facilitadores.html' },
                    { label: 'Usuarios', icon: Icons.usuarios, href: '/frontend/views/analista/usuarios.html' },
                    { label: 'Verificación', icon: Icons.verificacion, href: '/frontend/views/analista/verificacion.html' },
                    { label: 'Reportes', icon: Icons.reportes, href: '/frontend/views/analista/reportes.html', section: 'Reportes' }
                ],
                'Facilitador': [
                    { label: 'Dashboard', icon: Icons.dashboard, href: '/frontend/views/facilitador/dashboard.html', section: 'Principal' },
                    { label: 'Mis Cursos', icon: Icons.cursos, href: '/frontend/views/facilitador/mis-cursos.html', section: 'Gestión' },
                    { label: 'Estudiantes', icon: Icons.usuarios, href: '/frontend/views/facilitador/estudiantes.html' },
                    { label: 'Calificaciones', icon: Icons.calificaciones, href: '/frontend/views/facilitador/calificaciones.html' },
                    { label: 'Reportes', icon: Icons.reportes, href: '/frontend/views/facilitador/reportes.html', section: 'Reportes' }
                ],
                'Participante': [
                    { label: 'Dashboard', icon: Icons.dashboard, href: '/frontend/views/participante/dashboard.html', section: 'Principal' },
                    { label: 'Mis Cursos', icon: Icons.cursos, href: '/frontend/views/participante/mis-cursos.html', section: 'Gestión' },
                    { label: 'Inscribirme', icon: Icons.inscripciones, href: '/frontend/views/participante/inscribir.html' },
                    { label: 'Mis Notas', icon: Icons.calificaciones, href: '/frontend/views/participante/mis-notas.html' },
                    { label: 'Certificados', icon: Icons.certificados, href: '/frontend/views/participante/certificados.html', section: 'Reportes' }
                ]
            };

            const items = menus[rol] || [];
            let html = `
                <div class="sidebar__brand">
                    <div class="sidebar__brand-icon">
                        <img src="${resolveUrl('/frontend/img/logo-uptec-grande.png')}" alt="UPTECMS Cursos" />
                    </div>
                    <div>
                        <div class="sidebar__brand-text">UPTECMS</div>
                        <div class="sidebar__brand-sub">Cursos</div>
                    </div>
                </div>
                <ul class="sidebar__nav">
            `;

            let currentSection = '';
            items.forEach(item => {
                if (item.section && item.section !== currentSection) {
                    currentSection = item.section;
                    html += `<li class="sidebar__section">${item.section}</li>`;
                }
                                const currentClean = window.location.pathname.replace(/\/$/, '');
                const targetClean = item.href.replace(/\/$/, '');
                const isDashboardItem = item.label === 'Dashboard';
                const isCurrentDashboard = currentClean === '/' + rol.toLowerCase() || currentClean === '/views/' + rol.toLowerCase() || currentClean.endsWith('dashboard.html');
                const isActive = isDashboardItem ? isCurrentDashboard : (window.location.pathname.endsWith(item.href) || window.location.pathname.includes(item.href.split('/').pop()));
                html += `
                    <li>
                        <a href="${resolveUrl(item.href)}" class="sidebar__link ${isActive ? 'active' : ''}">
                            <span class="sidebar__link-icon" style="display: inline-flex; align-items: center; justify-content: center;">
                                ${item.icon}
                            </span>
                            <span>${item.label}</span>
                        </a>
                    </li>
                `;
            });

            html += `</ul>
                <div class="sidebar__footer">
                    <div>v2.0 UPTECMS</div>
                    <div style="font-size: 0.75rem; opacity: 0.7;">Caracas, Venezuela</div>
                </div>
            `;

            sidebar.innerHTML = html;
        }
    };

    // ============================================
    // HEADER
    // ============================================
    const Header = {
        init() {
            const headerUser = document.getElementById('header-user');
            const headerRole = document.getElementById('header-role');
            const headerAvatar = document.getElementById('header-avatar');

            if (AUTH.user) {
                if (headerUser) headerUser.textContent = AUTH.user.nombre + ' ' + AUTH.user.apellidos;
                if (headerRole) headerRole.textContent = AUTH.user.rol;
                if (headerAvatar) headerAvatar.textContent = (AUTH.user.nombre[0] + AUTH.user.apellidos[0]).toUpperCase();
            }

            // Logout
            const logoutBtn = document.getElementById('logout-btn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', () => AUTH.logout());
            }

            // Mobile sidebar toggle
            const sidebarToggle = document.getElementById('sidebar-toggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', () => {
                    document.getElementById('sidebar').classList.toggle('open');
                    document.getElementById('sidebar-overlay').classList.toggle('open');
                });
            }

            const sidebarOverlay = document.getElementById('sidebar-overlay');
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', () => {
                    document.getElementById('sidebar').classList.remove('open');
                    sidebarOverlay.classList.remove('open');
                });
            }
        }
    };

    // ============================================
    // TABLAS
    // ============================================
    const DataTable = {
        render(containerId, data, columns, options = {}) {
            const container = document.getElementById(containerId);
            if (!container) return;

            if (!data || data.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state__icon" style="display: flex; justify-content: center; margin-bottom: 0.75rem;">
                            ${Icons.vacio(48)}
                        </div>
                        <div class="empty-state__title">No hay datos</div>
                        <div class="empty-state__text">${options.emptyText || 'No se encontraron registros'}</div>
                    </div>
                `;
                return;
            }

            let html = '<div class="table-container"><table class="table">';

            // Header
            html += '<thead><tr>';
            columns.forEach(col => {
                html += `<th>${col.label}</th>`;
            });
            if (options.actions) html += '<th>Acciones</th>';
            html += '</tr></thead>';

            // Body
            html += '<tbody>';
            data.forEach((row, idx) => {
                html += '<tr>';
                columns.forEach(col => {
                    let value = this._getValue(row, col.key);
                    if (col.format) value = col.format(value, row);
                    if (col.badge) {
                        const badgeClass = col.badge[value] || 'badge--neutral';
                        value = `<span class="badge ${badgeClass}">${value || '-'}</span>`;
                    }
                    html += `<td>${value !== null && value !== undefined ? value : '-'}</td>`;
                });
                if (options.actions) {
                    html += '<td>';
                    options.actions.forEach(action => {
                        const btnClass = action.class || 'btn btn--sm btn--ghost';
                        html += `<button class="${btnClass}" onclick="${action.onClick(row, idx)}" title="${action.title || ''}">${action.icon || action.label}</button> `;
                    });
                    html += '</td>';
                }
                html += '</tr>';
            });
            html += '</tbody></table></div>';

            container.innerHTML = html;
        },

        _getValue(obj, path) {
            return path.split('.').reduce((o, p) => o && o[p], obj);
        }
    };

    // ============================================
    // SPINNER / LOADING
    // ============================================
    const Loading = {
        show(containerId) {
            const container = document.getElementById(containerId);
            if (container) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="spinner spinner--lg" style="margin: 0 auto 1rem;"></div>
                        <div class="empty-state__text">Cargando...</div>
                    </div>
                `;
            }
        },

        hide(containerId) {
            // Reemplazo normal al renderizar
        }
    };

    // ============================================
    // UTILIDADES
    // ============================================
    const Utils = {
        formatDate(date) {
            if (!date) return '-';
            const d = new Date(date);
            return d.toLocaleDateString('es-VE', { year: 'numeric', month: 'short', day: 'numeric' });
        },

        formatDateTime(date) {
            if (!date) return '-';
            const d = new Date(date);
            return d.toLocaleDateString('es-VE', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        },

        formatNumber(num) {
            if (num === null || num === undefined) return '-';
            return Number(num).toLocaleString('es-VE');
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // ============================================
    // INICIALIZACIÓN
    // ============================================
    async function initDashboard() {
        await API.init();
        const isAuth = await AUTH.check();

        if (!isAuth) {
            window.location.href = resolveUrl('/login.html');
            return;
        }

        Sidebar.init(AUTH.getRole());
        Header.init();
    }

    // Exponer globalmente
    window.Icons = Icons;
    window.Toast = Toast;
    window.Modal = Modal;
    window.ConfirmDialog = ConfirmDialog;
    window.Sidebar = Sidebar;
    window.Header = Header;
    window.DataTable = DataTable;
    window.Loading = Loading;
    window.Utils = Utils;
    window.initDashboard = initDashboard;

})();
