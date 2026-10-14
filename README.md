# APP Alquileres

Aplicación independiente en PHP + MySQL/MariaDB para gestionar alquileres. Entorno inicial WAMP en Windows y posterior despliegue en servidor web.

## Instalación en WAMP
1. Copia el proyecto en `C:\\wamp64\\www\\APP-Alquileres`.
2. Abre phpMyAdmin e importa `database.sql`. El esquema utiliza `CREATE TABLE IF NOT EXISTS`; no elimina tablas ni datos.
3. Revisa `config/config.php` (base de datos `app_alquileres`, usuario local `root`, contraseña vacía por defecto).
4. Abre `http://localhost/APP-Alquileres/public/setup.php` y crea el administrador con una contraseña propia de al menos 12 caracteres.
5. Accede a `http://localhost/APP-Alquileres/public/`.
6. Elimina `public/setup.php` después de crear el administrador.

## Incluido en esta primera versión
- Inicio y cierre de sesión con contraseña cifrada, sesiones y protección CSRF.
- Resumen de inquilinos, cargos, ingresos y fianzas pendientes.
- Alta y listado de inquilinos, cargos, ingresos y fianzas.
- Creación básica de usuarios con roles ADMIN, GESTOR y LECTURA.
- Esquema SQL inicial completo, sin migraciones separadas.
- Interfaz adaptable a escritorio y móvil.

## Pendiente
Edición completa de registros, estadísticas detalladas, PDF individual por inquilino, copias y restauración desde la propia aplicación, pruebas integrales y revisión de seguridad antes de producción. Esta base inicial no debe exponerse a Internet sin HTTPS, credenciales de base de datos adecuadas y copias externas.
