# APP Alquileres

Aplicación independiente PHP + MySQL/MariaDB basada en el documento funcional **Manual-Alquiler-Manager.pdf**. Entorno inicial WAMP en Windows.

## Instalación en WAMP
1. Copia el repositorio en `C:\\wamp64\\www\\APP-Alquileres`.
2. En phpMyAdmin importa `database.sql`.
3. Revisa `config/config.php` (base de datos `app_alquileres`; en WAMP local el usuario predeterminado es `root` y la contraseña está vacía).
4. Abre `http://localhost/APP-Alquileres/public/setup.php`, crea el administrador con una contraseña propia de al menos 8 caracteres.
5. Elimina `public/setup.php` inmediatamente después de crear el administrador.
6. Entra en `http://localhost/APP-Alquileres/public/login.php`.

## Módulos incluidos
- **Ubicaciones e inmuebles:** gestiona varias ubicaciones; cada una puede ser una vivienda independiente o un edificio con múltiples viviendas, habitaciones o locales. Cada ubicación contiene unidades y conserva su propio historial.
- **Inquilinos:** alta, edición, borrado cuando no hay movimientos asociados, activos primero, asignación obligatoria a una unidad, filtro global por ubicación e inquilino, estado, notas, fechas, alquiler mensual, opción para crear fianza automáticamente y PDF individual con ubicación, unidad, cargos, ingresos, balance y fianzas.
- **Cargos:** alta, edición y borrado; categoría, concepto, inquilino, importe, mes y pagado por; total a cobrar.
- **Ingresos:** alta, edición y borrado de ingresos manuales; método y total cobrado. Los ingresos contables generados al liquidar fianzas se protegen frente al borrado manual.
- **Fianzas y daños:** alta, edición, borrado antes de liquidar, motivo, importe de daños y detalle; botón de liquidación que calcula deuda, retención, devolución y crea ingresos de compensación/daños.
- **Dashboard:** KPIs financieros y balances con ámbito global o por ubicación/inquilino, detalle de ubicación y unidad, cargos por tipo y enlaces a ficha individual.
- **Estadísticas:** filtros por ubicación, inquilino, año y rango de fechas; cargos, ingresos, balance, porcentaje de cobro, desglose por tipo/método, evolución mensual y resumen anual.
- **Usuarios y autenticación:** roles ADMIN, GESTOR y LECTURA; contraseñas cifradas, sesiones y protección CSRF.
- **Backups:** crear y descargar copias SQL, listar, borrar, subir y restaurar con confirmación explícita. El módulo requiere que WAMP tenga los ejecutables `mysql.exe`/ `mariadb.exe` y `mysqldump.exe`/ `mariadb-dump.exe` en las rutas habituales.
- **Interfaz adaptable** a escritorio y móvil, pendiente de pruebas con dispositivos reales.

## Importante
Esta es la primera implementación amplia del manual, todavía no validada de extremo a extremo en tu instalación. Antes de usar datos reales, prueba los flujos con datos ficticios y guarda copias externas. No expongas la aplicación a Internet sin HTTPS, credenciales de base de datos adecuadas y revisión de seguridad. El botón de ficha genera un PDF descargable directamente desde PHP.
