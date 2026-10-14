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
