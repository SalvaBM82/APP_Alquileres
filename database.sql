CREATE DATABASE IF NOT EXISTS app_alquileres CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE app_alquileres;

-- Esquema inicial completo. No elimina tablas ni datos existentes.
CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('ADMIN','GESTOR','LECTURA') NOT NULL DEFAULT 'GESTOR',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inquilinos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(160) NOT NULL,
  dni_nie VARCHAR(30) NULL,
  telefono VARCHAR(40) NULL,
  email VARCHAR(190) NULL,
  unidad VARCHAR(120) NULL,
  fecha_entrada DATE NULL,
  fecha_salida DATE NULL,
  alquiler_mensual DECIMAL(10,2) NOT NULL DEFAULT 0,
  estado ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  notas TEXT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_inquilinos_estado (estado), INDEX idx_inquilinos_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cargos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha DATE NOT NULL,
  tipo VARCHAR(80) NOT NULL DEFAULT 'Alquiler',
  concepto VARCHAR(255) NOT NULL,
  inquilino_id INT UNSIGNED NOT NULL,
  importe DECIMAL(10,2) NOT NULL,
  mes CHAR(7) NULL,
  pagado_por ENUM('Propietario','Inquilino','Otro') NOT NULL DEFAULT 'Propietario',
  estado_pago ENUM('PENDIENTE','PAGADO') NOT NULL DEFAULT 'PENDIENTE',
  fecha_pago DATE NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cargos_inquilino FOREIGN KEY (inquilino_id) REFERENCES inquilinos(id) ON DELETE RESTRICT,
  INDEX idx_cargos_fecha (fecha), INDEX idx_cargos_mes (mes), INDEX idx_cargos_tipo (tipo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ingresos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha DATE NOT NULL,
  importe DECIMAL(10,2) NOT NULL,
  concepto VARCHAR(255) NOT NULL,
  inquilino_id INT UNSIGNED NOT NULL,
  metodo ENUM('Transferencia','Bizum','Efectivo','Otro') NOT NULL DEFAULT 'Transferencia',
  origen ENUM('NORMAL','COMPENSACION_DEUDA','RETENCION_DANOS') NOT NULL DEFAULT 'NORMAL',
  periodo_alquiler CHAR(7) NULL COMMENT 'Mes de alquiler al que se atribuye el ingreso, formato AAAA-MM',
  fianza_id INT UNSIGNED NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ingresos_inquilino FOREIGN KEY (inquilino_id) REFERENCES inquilinos(id) ON DELETE RESTRICT,
  INDEX idx_ingresos_fecha (fecha)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fianzas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inquilino_id INT UNSIGNED NOT NULL,
  fecha DATE NOT NULL,
  pagada DECIMAL(10,2) NOT NULL DEFAULT 0,
  a_devolver DECIMAL(10,2) NOT NULL DEFAULT 0,
  resto DECIMAL(10,2) NOT NULL DEFAULT 0,
  estado ENUM('RETENIDA','DEVUELTA','PARCIAL','LIQUIDADA') NOT NULL DEFAULT 'RETENIDA',
  motivo_retencion ENUM('Deuda','Daños','Limpieza','Mixto','Ninguno') NOT NULL DEFAULT 'Ninguno',
  importe_danos DECIMAL(10,2) NOT NULL DEFAULT 0,
  detalle_retencion TEXT NULL,
  liquidada TINYINT(1) NOT NULL DEFAULT 0,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_fianzas_inquilino FOREIGN KEY (inquilino_id) REFERENCES inquilinos(id) ON DELETE RESTRICT,
  INDEX idx_fianzas_estado (estado)
) ENGINE=InnoDB;

-- Multiubicación: una ubicación puede ser una vivienda independiente o agrupar varias unidades.
CREATE TABLE IF NOT EXISTS ubicaciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(160) NOT NULL,
  tipo ENUM('VIVIENDA_INDEPENDIENTE','LOCAL_INDEPENDIENTE','HABITACION_INDEPENDIENTE','GARAJE_INDEPENDIENTE','TRASTERO_INDEPENDIENTE','OFICINA_INDEPENDIENTE','OTRO_INDEPENDIENTE','MULTIUNIDAD') NOT NULL DEFAULT 'VIVIENDA_INDEPENDIENTE',
  direccion VARCHAR(255) NULL,
  descripcion TEXT NULL,
  estado ENUM('ACTIVA','INACTIVA') NOT NULL DEFAULT 'ACTIVA',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ubicaciones_estado (estado),
  INDEX idx_ubicaciones_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS unidades (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ubicacion_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  tipo ENUM('VIVIENDA','HABITACION','LOCAL','OTRA') NOT NULL DEFAULT 'VIVIENDA',
  descripcion TEXT NULL,
  estado ENUM('ACTIVA','INACTIVA') NOT NULL DEFAULT 'ACTIVA',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_unidades_ubicacion FOREIGN KEY (ubicacion_id) REFERENCES ubicaciones(id) ON DELETE RESTRICT,
  UNIQUE KEY uq_unidad_nombre (ubicacion_id,nombre),
  INDEX idx_unidades_ubicacion (ubicacion_id),
  INDEX idx_unidades_estado (estado)
) ENGINE=InnoDB;

-- Amplía el tipo de ubicación en instalaciones existentes sin borrar datos.
ALTER TABLE ubicaciones MODIFY COLUMN tipo ENUM('INDEPENDIENTE','MULTIUNIDAD','VIVIENDA_INDEPENDIENTE','LOCAL_INDEPENDIENTE','HABITACION_INDEPENDIENTE','GARAJE_INDEPENDIENTE','TRASTERO_INDEPENDIENTE','OFICINA_INDEPENDIENTE','OTRO_INDEPENDIENTE') NOT NULL DEFAULT 'VIVIENDA_INDEPENDIENTE';
UPDATE ubicaciones SET tipo='VIVIENDA_INDEPENDIENTE' WHERE tipo='INDEPENDIENTE';

-- Añade la relación a la unidad sin borrar ni reemplazar la columna unidad anterior.
-- IF NOT EXISTS permite volver a importar este archivo en MariaDB sin duplicar la columna.
ALTER TABLE inquilinos ADD COLUMN IF NOT EXISTS unidad_id INT UNSIGNED NULL;

-- Migración conservadora: los inquilinos existentes se asignan a una ubicación y
-- se crea una unidad por cada valor histórico del campo "unidad".
INSERT INTO ubicaciones (nombre,tipo,direccion,descripcion,estado)
SELECT 'Ubicación inicial','MULTIUNIDAD',NULL,'Ubicación creada automáticamente para conservar los registros anteriores','ACTIVA'
WHERE EXISTS (SELECT 1 FROM inquilinos WHERE unidad_id IS NULL)
  AND NOT EXISTS (SELECT 1 FROM ubicaciones WHERE nombre='Ubicación inicial');

SET @ubicacion_legacy := (SELECT id FROM ubicaciones WHERE nombre='Ubicación inicial' ORDER BY id LIMIT 1);

INSERT IGNORE INTO unidades (ubicacion_id,nombre,tipo,estado)
SELECT @ubicacion_legacy,
       COALESCE(NULLIF(TRIM(i.unidad),''),'Unidad sin especificar'),
       'OTRA','ACTIVA'
FROM inquilinos i
WHERE i.unidad_id IS NULL AND @ubicacion_legacy IS NOT NULL
GROUP BY COALESCE(NULLIF(TRIM(i.unidad),''),'Unidad sin especificar');

UPDATE inquilinos i
JOIN unidades u ON u.ubicacion_id=@ubicacion_legacy
 AND u.nombre=COALESCE(NULLIF(TRIM(i.unidad),''),'Unidad sin especificar')
SET i.unidad_id=u.id
WHERE i.unidad_id IS NULL AND @ubicacion_legacy IS NOT NULL;

-- Preferencias configurables de la aplicación
CREATE TABLE IF NOT EXISTS configuracion_app (
  clave VARCHAR(80) PRIMARY KEY,
  valor TEXT NOT NULL,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO configuracion_app(clave,valor) VALUES
 ('nombre_app','APP Alquileres'),
 ('titulo_web','APP Alquileres · Gestión de propiedades'),
 ('subtitulo_app','Gestión de propiedades'),
 ('color_principal','#2859a6');


-- Estado de pago de cargos existentes y nuevos (los antiguos se inicializan como pendientes).
SET @col_estado_pago := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cargos' AND COLUMN_NAME='estado_pago');
SET @sql_estado_pago := IF(@col_estado_pago=0,'ALTER TABLE cargos ADD COLUMN estado_pago ENUM(''PENDIENTE'',''PAGADO'') NOT NULL DEFAULT ''PENDIENTE''','SELECT 1');
PREPARE stmt_estado_pago FROM @sql_estado_pago; EXECUTE stmt_estado_pago; DEALLOCATE PREPARE stmt_estado_pago;
SET @col_fecha_pago := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cargos' AND COLUMN_NAME='fecha_pago');
SET @sql_fecha_pago := IF(@col_fecha_pago=0,'ALTER TABLE cargos ADD COLUMN fecha_pago DATE NULL','SELECT 1');
PREPARE stmt_fecha_pago FROM @sql_fecha_pago; EXECUTE stmt_fecha_pago; DEALLOCATE PREPARE stmt_fecha_pago;

-- Plantillas para cargos recurrentes, aplicables a una unidad o a un inquilino.
CREATE TABLE IF NOT EXISTS cargos_recurrentes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unidad_id INT UNSIGNED NULL,
  inquilino_id INT UNSIGNED NULL,
  tipo VARCHAR(80) NOT NULL DEFAULT 'Otros',
  concepto VARCHAR(255) NOT NULL,
  importe DECIMAL(10,2) NOT NULL,
  periodicidad ENUM('MENSUAL','TRIMESTRAL','ANUAL') NOT NULL DEFAULT 'MENSUAL',
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NULL,
  pagado_por ENUM('Propietario','Inquilino','Otro') NOT NULL DEFAULT 'Inquilino',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_recurrentes_unidad FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE RESTRICT,
  CONSTRAINT fk_recurrentes_inquilino FOREIGN KEY (inquilino_id) REFERENCES inquilinos(id) ON DELETE RESTRICT,
  INDEX idx_recurrentes_unidad (unidad_id,activo),
  INDEX idx_recurrentes_inquilino (inquilino_id,activo),
  INDEX idx_recurrentes_periodo (periodicidad,fecha_inicio,fecha_fin)
) ENGINE=InnoDB;

-- Historial de precios para aplicar subidas desde un mes concreto sin perder importes anteriores.
CREATE TABLE IF NOT EXISTS cargos_recurrentes_precios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recurrente_id INT UNSIGNED NOT NULL,
  fecha_efecto DATE NOT NULL,
  importe DECIMAL(10,2) NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_recurrente_precio_plantilla FOREIGN KEY (recurrente_id) REFERENCES cargos_recurrentes(id) ON DELETE CASCADE,
  UNIQUE KEY uq_recurrente_precio_fecha (recurrente_id,fecha_efecto),
  INDEX idx_recurrente_precio_fecha (recurrente_id,fecha_efecto)
) ENGINE=InnoDB;

-- Campos de trazabilidad para saber qué plantilla generó cada cargo.
-- Las comprobaciones con information_schema permiten importar database.sql más de una vez.
SET @col_recurrente := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cargos' AND COLUMN_NAME='recurrente_id');
SET @sql_recurrente := IF(@col_recurrente=0,'ALTER TABLE cargos ADD COLUMN recurrente_id INT UNSIGNED NULL','SELECT 1');
PREPARE stmt_recurrente FROM @sql_recurrente; EXECUTE stmt_recurrente; DEALLOCATE PREPARE stmt_recurrente;
SET @col_periodo := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cargos' AND COLUMN_NAME='periodo_recurrente');
SET @sql_periodo := IF(@col_periodo=0,'ALTER TABLE cargos ADD COLUMN periodo_recurrente CHAR(7) NULL','SELECT 1');
PREPARE stmt_periodo FROM @sql_periodo; EXECUTE stmt_periodo; DEALLOCATE PREPARE stmt_periodo;
SET @idx_recurrente := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cargos' AND INDEX_NAME='uq_cargo_recurrente_periodo');
SET @sql_idx_recurrente := IF(@idx_recurrente=0,'CREATE UNIQUE INDEX uq_cargo_recurrente_periodo ON cargos(inquilino_id,recurrente_id,periodo_recurrente)','SELECT 1');
PREPARE stmt_idx_recurrente FROM @sql_idx_recurrente; EXECUTE stmt_idx_recurrente; DEALLOCATE PREPARE stmt_idx_recurrente;


-- Excepciones para que un cargo recurrente borrado no vuelva a generarse automáticamente.
CREATE TABLE IF NOT EXISTS cargos_recurrentes_exclusiones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inquilino_id INT UNSIGNED NOT NULL,
  recurrente_id INT UNSIGNED NOT NULL DEFAULT 0,
  periodo_recurrente CHAR(7) NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_recurrentes_exclusion_inquilino FOREIGN KEY (inquilino_id) REFERENCES inquilinos(id) ON DELETE RESTRICT,
  UNIQUE KEY uq_recurrente_exclusion (inquilino_id,recurrente_id,periodo_recurrente)
) ENGINE=InnoDB;


-- Periodo al que corresponde el ingreso, independiente de la fecha efectiva de cobro.
ALTER TABLE ingresos ADD COLUMN IF NOT EXISTS periodo_alquiler CHAR(7) NULL COMMENT 'Mes de alquiler al que se atribuye el ingreso, formato AAAA-MM';


-- Categorías configurables para cargos. Se conserva el texto en los movimientos para
-- mantener compatibilidad con el historial y las estadísticas existentes.
CREATE TABLE IF NOT EXISTS categorias_cargos (
  nombre VARCHAR(80) NOT NULL PRIMARY KEY,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  orden INT NOT NULL DEFAULT 0,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO categorias_cargos(nombre,activo,orden) VALUES
 ('Alquiler',1,10),('Basura',1,20),('Luz',1,30),('Agua',1,40),('Internet',1,50),
 ('Electricidad',1,60),('Reparacion',1,70),('Otros',1,80);
-- Conversión idempotente desde el ENUM antiguo a texto configurable.
ALTER TABLE cargos MODIFY COLUMN tipo VARCHAR(80) NOT NULL DEFAULT 'Alquiler';
ALTER TABLE cargos_recurrentes MODIFY COLUMN tipo VARCHAR(80) NOT NULL DEFAULT 'Otros';
