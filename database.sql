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
  tipo ENUM('Alquiler','Basura','Luz','Agua','Internet','Electricidad','Reparacion','Otros') NOT NULL DEFAULT 'Alquiler',
  concepto VARCHAR(255) NOT NULL,
  inquilino_id INT UNSIGNED NOT NULL,
  importe DECIMAL(10,2) NOT NULL,
  mes CHAR(7) NULL,
  pagado_por ENUM('Propietario','Inquilino','Otro') NOT NULL DEFAULT 'Propietario',
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
