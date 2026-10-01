// ==============================================
// ACTIVAR OPCIÓN DEL MENÚ SEGÚN LA PÁGINA ACTUAL
// ==============================================

document.addEventListener('DOMContentLoaded', function() {
    const links = document.querySelectorAll(".menu a");
    const paginaActual = window.location.pathname.split("/").pop() || "menu_de_inicio.php";

    links.forEach(link => link.classList.remove("active"));

    links.forEach(link => {
        const destino = link.getAttribute("href");
        if (destino === paginaActual) {
            link.classList.add("active");
        }
    });

    // Si ningún enlace coincidió, activar Inicio
    if (!document.querySelector(".menu a.active")) {
        const inicioLink = document.querySelector('.menu a[href="menu_de_inicio.php"]');
        if (inicioLink) inicioLink.classList.add("active");
    }

    // ==============================================
    // CERRAR SESIÓN
    // ==============================================

    const logoutLink = document.getElementById("logout-link");
    if (logoutLink) {
        logoutLink.addEventListener("click", function(e) {
            e.preventDefault();
            if (confirm("¿Seguro que deseas cerrar sesión?")) {
                window.location.href = "login.php?logout=true";
            }
        });
    }

    // ==============================================
    // AUTO-CERRAR ALERTAS
    // ==============================================

    const alerts = document.querySelectorAll('.alert-system');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // ==============================================
    // NOTIFICACIONES EN TIEMPO REAL (Simulación)
    // ==============================================

    function actualizarNotificaciones() {
        const badge = document.querySelector('.notification span');
        if (badge) {
            // Simular nuevas notificaciones
            fetch('api/notificaciones.php')
                .then(response => response.json())
                .then(data => {
                    if (data.total > 0) {
                        badge.textContent = data.total;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                })
                .catch(() => {});
        }
    }

    // Actualizar cada 30 segundos
    setInterval(actualizarNotificaciones, 30000);

    // ==============================================
    // CONFIRMAR ELIMINACIONES
    // ==============================================

    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('¿Estás seguro de eliminar este registro?')) {
                window.location.href = this.getAttribute('href');
            }
        });
    });

    // ==============================================
    // VALIDACIÓN DE FORMULARIOS
    // ==============================================

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const required = this.querySelectorAll('[required]');
            let valid = true;

            required.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#ef4444';
                    valid = false;
                } else {
                    field.style.borderColor = '#e2e8f0';
                }
            });

            if (!valid) {
                e.preventDefault();
                alert('Por favor completa todos los campos requeridos.');
            }
        });
    });
});

// ==============================================
// FUNCIONES UTILITARIAS
// ==============================================

// Formatear números como moneda
function formatCurrency(amount) {
    return 'C$ ' + parseFloat(amount).toLocaleString('es-NI', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Mostrar mensaje de éxito
function showSuccess(message) {
    const div = document.createElement('div');
    div.className = 'alert-system alert-success';
    div.textContent = message;
    document.querySelector('.content').prepend(div);
    setTimeout(() => {
        div.style.transition = 'opacity 0.5s';
        div.style.opacity = '0';
        setTimeout(() => div.remove(), 500);
    }, 5000);
}

// Mostrar mensaje de error
function showError(message) {
    const div = document.createElement('div');
    div.className = 'alert-system alert-danger';
    div.textContent = message;
    document.querySelector('.content').prepend(div);
    setTimeout(() => {
        div.style.transition = 'opacity 0.5s';
        div.style.opacity = '0';
        setTimeout(() => div.remove(), 500);
    }, 5000);
}