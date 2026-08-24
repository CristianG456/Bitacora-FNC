// notifications.js

document.addEventListener('DOMContentLoaded', () => {
    // Verificar si existe el ID de usuario y Echo
    if (!window.userId || !window.Echo) {
        return;
    }

    // Suscribirse al canal privado del usuario
    window.Echo.private(`App.Models.User.${window.userId}`)
        .listen('.NuevaNotificacion', (e) => {
            mostrarToast(e);
            actualizarContador(1);
            agregarALista(e);
            emitirEventoLocal(e);
        })
        .error((error) => {
            console.error('Error suscribiendo al canal de notificaciones:', error);
        });

    function mostrarToast(data) {
        let container = document.getElementById('global-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'global-toast-container';
            // Estilos del contenedor (esquina superior derecha, fixed)
            container.style.position = 'fixed';
            container.style.top = '20px';
            container.style.right = '20px';
            container.style.zIndex = '9999';
            container.style.display = 'flex';
            container.style.flexDirection = 'column';
            container.style.gap = '10px';
            document.body.appendChild(container);
        }

        const colorMap = {
            'info': '#3b82f6',
            'success': '#10b981',
            'warning': '#f59e0b',
            'error': '#ef4444',
            'caso': '#b11226' // Color institucional
        };

        const color = colorMap[data.tipo] || colorMap['info'];

        const toast = document.createElement('div');
        toast.className = 'global-toast';
        toast.style.backgroundColor = '#ffffff';
        toast.style.borderLeft = `4px solid ${color}`;
        toast.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)';
        toast.style.padding = '1rem';
        toast.style.borderRadius = '0.5rem';
        toast.style.display = 'flex';
        toast.style.alignItems = 'center';
        toast.style.justifyContent = 'space-between';
        toast.style.minWidth = '300px';
        toast.style.maxWidth = '400px';
        toast.style.animation = 'slideInToast 0.3s ease-out forwards';
        
        // Agregar estilos de animación globales si no existen
        if (!document.getElementById('toast-styles')) {
            const style = document.createElement('style');
            style.id = 'toast-styles';
            style.textContent = `
                @keyframes slideInToast {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                .global-toast-close {
                    color: #9ca3af; background: none; border: none; cursor: pointer; padding: 5px; margin-left: 10px; font-size: 1.2rem; line-height: 1;
                }
                .global-toast-close:hover { color: #4b5563; }
            `;
            document.head.appendChild(style);
        }

        toast.innerHTML = `
            <div style="flex-grow: 1;">
                <strong style="color: ${color}; display: block; margin-bottom: 4px;">${data.titulo}</strong>
                <p style="margin: 0; color: #4b5563; font-size: 0.875rem;">${data.mensaje}</p>
                <small style="color: #9ca3af; font-size: 0.7rem; display: block; margin-top: 4px;">Justo ahora</small>
            </div>
            <button class="global-toast-close" onclick="this.parentElement.remove()">✕</button>
        `;

        container.appendChild(toast);

        // Auto remove
        setTimeout(() => {
            if (toast.parentElement) {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s ease-out';
                setTimeout(() => { if (toast.parentElement) toast.remove(); }, 300);
            }
        }, 6000);
    }

    function actualizarContador(incremento) {
        // Asumiendo que el contador de notificaciones tiene un ID o Clase específica.
        // Ej: id="notification-badge"
        const badges = document.querySelectorAll('.notif-badge, #notif-badge-count');
        badges.forEach(badge => {
            let current = parseInt(badge.innerText || '0', 10);
            if (isNaN(current)) current = 0;
            current += incremento;
            badge.innerText = current;
            badge.style.display = current > 0 ? 'inline-block' : 'none';
            // Animación para llamar la atención
            badge.style.transform = 'scale(1.2)';
            setTimeout(() => { badge.style.transform = 'scale(1)'; }, 200);
        });
    }

    function agregarALista(data) {
        // Si hay un contenedor de dropdown para notificaciones
        const listContainer = document.querySelector('.notifications-list, #notifications-dropdown');
        if (!listContainer) return;

        // Remover mensaje de "No hay notificaciones"
        const emptyState = listContainer.querySelector('.empty-notifications');
        if (emptyState) emptyState.remove();

        const item = document.createElement('div');
        item.className = 'notification-item unread';
        item.style.padding = '10px';
        item.style.borderBottom = '1px solid #e5e7eb';
        item.style.backgroundColor = '#f9fafb';
        
        // Dependiendo del framework CSS que uses, esto puede adaptarse
        item.innerHTML = `
            <strong>${data.titulo}</strong>
            <p style="margin: 0; font-size: 0.85rem;">${data.mensaje}</p>
            <small style="color: #6b7280;">Hace unos instantes</small>
        `;

        listContainer.insertBefore(item, listContainer.firstChild);
    }

    // Emitir un evento de CustomEvent local para que otras partes de la app (ej: Dashboard) reaccionen a la notificación
    function emitirEventoLocal(data) {
        const event = new CustomEvent('nueva-notificacion-recibida', { detail: data });
        document.dispatchEvent(event);
    }
});
