document.addEventListener("DOMContentLoaded", async () => {
    const usuarioSelect = document.getElementById("usuarioSelect");
    const iniciarSesionBtn = document.getElementById("iniciarSesion");
    const menu = document.getElementById("menu"); // Elemento del menú principal en el HTML
    const contenido = document.getElementById("contenido");
    const menuToggle = document.getElementById("menuToggle"); // Asegúrate de que este elemento exista en el HTML

    /** Simulación de Base de Datos (para futura integración con servidor) **/
    async function obtenerUsuariosDesdeBD() {
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve(usuarios); // Usa la variable "usuarios" de roles.js
            }, 1000); // Simula un retraso de carga
        });
    }

    /** Cargar usuarios en el select **/
    async function cargarUsuarios() {
        usuarioSelect.innerHTML = '<option value="">Seleccione un usuario</option>';

        const usuariosBD = await obtenerUsuariosDesdeBD();

        usuariosBD.forEach((usuario) => {
            const option = document.createElement("option");
            option.value = usuario.id;
            // Si el usuario tiene rol admin, se marca visualmente
            if (usuario.roles.includes("admin")) {
                option.textContent = usuario.nombre + " (Admin)";
            } else {
                option.textContent = usuario.nombre;
            }
            usuarioSelect.appendChild(option);
        });

        // Restaurar la sesión del usuario si existe
        const usuarioGuardado = localStorage.getItem("usuarioActivo");
        if (usuarioGuardado) {
            const usuario = JSON.parse(usuarioGuardado);
            usuarioSelect.value = usuario.id;
            mostrarPermisos(usuario);
        }
    }

    // Se ejecuta al cargar la página
    await cargarUsuarios();

    /** Evento para iniciar sesión **/
    iniciarSesionBtn.addEventListener("click", () => {
        const usuarioId = parseInt(usuarioSelect.value);
        if (isNaN(usuarioId)) {
            alert("Por favor, seleccione un usuario válido.");
            return;
        }
        const usuario = usuarios.find((u) => u.id === usuarioId);
        if (usuario) {
            localStorage.setItem("usuarioActivo", JSON.stringify(usuario));
            mostrarPermisos(usuario);
        }
    });

    /** Función para mostrar permisos del usuario **/
    function mostrarPermisos(usuario) {
        // Objeto de redirección en función del primer rol del usuario
        const paginasPorRol = {
            admin: "admin_dashboard.html",
            ingeniero: "dashboard_juve.html",
            medico: "dashboard_medico.html",
            operario_medios: "dashboard_medios.html",
            reporte_siembra: "dashboard_siembra.html",
            incubadora: "dashboard_incubadora.html",
            limpieza_incubadora: "dashboard_limpieza.html",
            operadora_cultivo: "dashboard_cultivo.html",
            operadora_lavado: "dashboard_lavado.html",
            supervisor_lavado: "dashboard_supervisor.html",
            envio_planta: "dashboard_envio.html"
        };

        // Tomamos el primer rol asignado
        const primerRol = usuario.roles[0];
        const dashboardUrl = paginasPorRol[primerRol] || "dashboard_default.html";

        // Obtener todos los permisos del usuario (usando la variable "permisosPorRol" definida en roles.js)
        const permisos = usuario.roles.reduce((acc, rol) => {
            return acc.concat(permisosPorRol[rol] || []);
        }, []);

        // Actualizar el contenido; se utiliza un contenedor interno "menuContainer" para listar los permisos
        contenido.innerHTML = `
            <h2>Bienvenido, <strong>${usuario.nombre}</strong></h2>
            <p><strong>Tu Dashboard:</strong> <a href="${dashboardUrl}" target="_blank">${dashboardUrl}</a></p>
            <h3>Tus Permisos:</h3>
            <div id="menuContainer"></div>
        `;


        const menuContainer = document.getElementById("menuContainer");

        if (permisos.length === 0) {
            menuContainer.innerHTML = "<p>No tienes permisos asignados.</p>";
        } else {
            permisos.forEach((permiso) => {
                const menuItem = document.createElement("button");
                menuItem.classList.add("permiso-btn");
                menuItem.textContent = permiso.replace("_", " ");
                menuItem.onclick = () => alert(`Funcionalidad en desarrollo: ${permiso}`);
                menuContainer.appendChild(menuItem);
            });
        }
    }

    /** Mostrar/Ocultar botón de menú en móviles **/
    function manejarMenu() {
        if (window.innerWidth <= 768) {
            if (menuToggle) menuToggle.style.display = "block";
        } else {
            if (menuToggle) menuToggle.style.display = "none";
            menu.classList.remove("active");
        }
    }
    manejarMenu();
    window.addEventListener("resize", manejarMenu);

    /** Evento para alternar el menú en móviles **/
    if (menuToggle) {
        menuToggle.addEventListener("click", (event) => {
            event.stopPropagation();
            menu.classList.toggle("active");
        });
    }

    /** Resaltar menú activo **/
    menu.addEventListener("click", (event) => {
        if (event.target.tagName === "BUTTON") {
            document.querySelectorAll("#menu button").forEach(btn => btn.classList.remove("active"));
            event.target.classList.add("active");
        }
    });
});
