-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 01, 2026 at 11:40 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sis_controlix`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_GenerarPlanilla` (IN `p_Periodo` VARCHAR(7))   BEGIN

    DECLARE v_idPlanilla INT;

    -- Crear la planilla
    INSERT INTO Planillas(periodo)
    VALUES(p_Periodo);

    SET v_idPlanilla = LAST_INSERT_ID();

    -- Insertar empleados activos
    INSERT INTO DetallePlanilla(
        idPlanilla,
        idEmpleado,
        salarioBruto
    )
    SELECT
        v_idPlanilla,
        idEmpleado,
        salarioBase
    FROM Empleados
    WHERE estado = TRUE;

    -- Calcular INSS
    UPDATE DetallePlanilla
    SET inss = ROUND(salarioBruto * 0.07, 2)
    WHERE idPlanilla = v_idPlanilla;

    -- Calcular renta mensual
    UPDATE DetallePlanilla
    SET rentaNetaMensual = salarioBruto - inss
    WHERE idPlanilla = v_idPlanilla;

    -- Calcular renta anual
    UPDATE DetallePlanilla
    SET rentaNetaAnual = rentaNetaMensual * 12
    WHERE idPlanilla = v_idPlanilla;

    -- Calcular IR anual
    UPDATE DetallePlanilla
    SET irAnual = fn_CalcularIR(rentaNetaAnual)
    WHERE idPlanilla = v_idPlanilla;

    -- Calcular IR mensual
    UPDATE DetallePlanilla
    SET irMensual = ROUND(irAnual / 12, 2)
    WHERE idPlanilla = v_idPlanilla;

    -- Calcular salario neto
    UPDATE DetallePlanilla
    SET salarioNeto = salarioBruto - inss - irMensual
    WHERE idPlanilla = v_idPlanilla;

    -- Actualizar totales de planilla
    UPDATE Planillas P
    INNER JOIN (
        SELECT
            idPlanilla,
            SUM(salarioBruto) AS totalBruto,
            SUM(inss) AS totalINSS,
            SUM(irMensual) AS totalIR,
            SUM(salarioNeto) AS totalNeto
        FROM DetallePlanilla
        WHERE idPlanilla = v_idPlanilla
        GROUP BY idPlanilla
    ) X
        ON P.idPlanilla = X.idPlanilla
    SET
        P.totalBruto = X.totalBruto,
        P.totalINSS = X.totalINSS,
        P.totalIR = X.totalIR,
        P.totalNeto = X.totalNeto
    WHERE P.idPlanilla = v_idPlanilla;

    -- Mostrar planilla generada
    SELECT *
    FROM Planillas
    WHERE idPlanilla = v_idPlanilla;

END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_CalcularIR` (`p_RentaNetaAnual` DECIMAL(12,2)) RETURNS DECIMAL(12,2) DETERMINISTIC BEGIN

    DECLARE v_ImpuestoBase DECIMAL(12,2) DEFAULT 0;
    DECLARE v_Porcentaje DECIMAL(5,2) DEFAULT 0;
    DECLARE v_SobreExceso DECIMAL(12,2) DEFAULT 0;
    DECLARE v_IR DECIMAL(12,2) DEFAULT 0;

    SELECT
        impuestoBase,
        porcentaje,
        sobreExceso
    INTO
        v_ImpuestoBase,
        v_Porcentaje,
        v_SobreExceso
    FROM TablaIR
    WHERE p_RentaNetaAnual >= limiteInferior
      AND (
            limiteSuperior IS NULL
            OR p_RentaNetaAnual <= limiteSuperior
          )
    ORDER BY limiteInferior DESC
    LIMIT 1;

    SET v_IR =
        v_ImpuestoBase +
        (
            (p_RentaNetaAnual - v_SobreExceso)
            * (v_Porcentaje / 100)
        );

    IF v_IR < 0 THEN
        SET v_IR = 0;
    END IF;

    RETURN ROUND(v_IR, 2);

END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `alertas`
--

CREATE TABLE `alertas` (
  `idAlerta` int(11) NOT NULL,
  `idProducto` int(11) NOT NULL,
  `tipoAlerta` varchar(50) DEFAULT NULL,
  `mensaje` varchar(250) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `leida` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `alertas`
--

INSERT INTO `alertas` (`idAlerta`, `idProducto`, `tipoAlerta`, `mensaje`, `fecha`, `leida`) VALUES
(14, 1, 'Stock Bajo', 'El producto \'cuaderno rallado\' tiene stock bajo (4 unidades)', '2026-09-01 17:15:16', 0);

-- --------------------------------------------------------

--
-- Table structure for table `auditoria`
--

CREATE TABLE `auditoria` (
  `idAuditoria` int(11) NOT NULL,
  `idUsuario` int(11) DEFAULT NULL,
  `tabla` varchar(50) DEFAULT NULL,
  `accion` varchar(20) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `datos_anteriores` text DEFAULT NULL,
  `datos_nuevos` text DEFAULT NULL,
  `ip` varchar(50) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bitacora`
--

CREATE TABLE `bitacora` (
  `idBitacora` int(11) NOT NULL,
  `idUsuario` int(11) NOT NULL,
  `accion` varchar(250) DEFAULT NULL,
  `tablaAfectada` varchar(100) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bitacoraproduccion`
--

CREATE TABLE `bitacoraproduccion` (
  `idBitacora` int(11) NOT NULL,
  `idOrden` int(11) NOT NULL,
  `idUsuario` int(11) NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `accion` varchar(50) DEFAULT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categorias`
--

CREATE TABLE `categorias` (
  `idCategoria` int(11) NOT NULL,
  `nombreCategoria` varchar(100) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categorias`
--

INSERT INTO `categorias` (`idCategoria`, `nombreCategoria`, `descripcion`) VALUES
(1, 'libros', ''),
(4, 'computadora', 'tecnologia');

-- --------------------------------------------------------

--
-- Table structure for table `clientes`
--

CREATE TABLE `clientes` (
  `idCliente` int(11) NOT NULL,
  `nombres` varchar(100) DEFAULT NULL,
  `apellidos` varchar(100) DEFAULT NULL,
  `cedula` varchar(25) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `direccion` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clientes`
--

INSERT INTO `clientes` (`idCliente`, `nombres`, `apellidos`, `cedula`, `telefono`, `correo`, `direccion`) VALUES
(1, 'Onell Isaac', 'Rodríguez Arcia', '0010305051053y', '2908815', 'orozcohaziel39@gmail.com', 'farabundo marti'),
(3, 'Cliente', 'General', NULL, NULL, NULL, NULL),
(4, 'Enoc', 'Blanco', '0012405061003k', '18002626', 'familialoschiquitines@gmail.com', 'su casa'),
(5, 'joctam Josue', 'Uriarte', '0012405061003k', '82908815', 'pachecomanueljimenez@gmail.com', 'su casa');

-- --------------------------------------------------------

--
-- Table structure for table `compras`
--

CREATE TABLE `compras` (
  `idCompra` int(11) NOT NULL,
  `idProveedor` int(11) NOT NULL,
  `idUsuario` int(11) NOT NULL,
  `fechaCompra` datetime DEFAULT current_timestamp(),
  `numeroFactura` varchar(30) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `iva` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `compras`
--

INSERT INTO `compras` (`idCompra`, `idProveedor`, `idUsuario`, `fechaCompra`, `numeroFactura`, `subtotal`, `iva`, `total`) VALUES
(16, 1, 1, '2026-08-13 18:38:51', '161651', 70.00, 10.50, 80.50),
(17, 1, 1, '2026-08-15 20:07:43', '125', 105.00, 15.75, 120.75),
(18, 1, 1, '2026-08-15 20:14:33', '161651', 36000.00, 5400.00, 41400.00),
(19, 1, 3, '2026-08-25 08:53:45', '154456', 180000.00, 27000.00, 207000.00),
(20, 1, 3, '2026-08-25 09:14:30', '2584126', 162000.00, 24300.00, 186300.00),
(21, 1, 1, '2026-08-27 20:00:27', '125', 90000.00, 13500.00, 103500.00),
(22, 1, 1, '2026-08-27 20:03:06', '161651', 175.00, 26.25, 201.25);

-- --------------------------------------------------------

--
-- Table structure for table `configuracionsistema`
--

CREATE TABLE `configuracionsistema` (
  `idConfiguracion` int(11) NOT NULL,
  `nombreEmpresa` varchar(150) DEFAULT NULL,
  `direccion` varchar(250) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `impuesto` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `controlescalidad`
--

CREATE TABLE `controlescalidad` (
  `idControl` int(11) NOT NULL,
  `idProducto` int(11) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `lote` varchar(50) DEFAULT NULL,
  `estado` varchar(20) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `responsable` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `detallecompras`
--

CREATE TABLE `detallecompras` (
  `idDetalleCompra` int(11) NOT NULL,
  `idCompra` int(11) NOT NULL,
  `idProducto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precioCompra` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `detallecompras`
--

INSERT INTO `detallecompras` (`idDetalleCompra`, `idCompra`, `idProducto`, `cantidad`, `precioCompra`, `subtotal`) VALUES
(16, 16, 1, 2, 35.00, 70.00),
(17, 17, 1, 3, 35.00, 105.00),
(18, 18, 3, 2, 18000.00, 36000.00),
(19, 19, 3, 10, 18000.00, 180000.00),
(20, 20, 3, 9, 18000.00, 162000.00),
(21, 21, 3, 5, 18000.00, 90000.00),
(22, 22, 1, 5, 35.00, 175.00);

-- --------------------------------------------------------

--
-- Table structure for table `detallefacturas`
--

CREATE TABLE `detallefacturas` (
  `idDetalleFactura` int(11) NOT NULL,
  `idFactura` int(11) NOT NULL,
  `idProducto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precioUnitario` decimal(10,2) NOT NULL,
  `descuento` decimal(10,2) DEFAULT 0.00,
  `impuesto` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `detallefacturas`
--

INSERT INTO `detallefacturas` (`idDetalleFactura`, `idFactura`, `idProducto`, `cantidad`, `precioUnitario`, `descuento`, `impuesto`, `subtotal`) VALUES
(1, 1, 1, 6, 50.00, 0.00, 0.00, 300.00),
(2, 2, 1, 1, 50.00, 0.00, 0.00, 50.00),
(3, 3, 1, 10, 50.00, 0.00, 0.00, 500.00),
(4, 4, 1, 3, 50.00, 0.00, 0.00, 150.00),
(5, 5, 3, 1, 21000.00, 0.00, 0.00, 21000.00),
(6, 6, 1, 2, 50.00, 0.00, 0.00, 100.00),
(7, 7, 3, 10, 21000.00, 0.00, 0.00, 210000.00),
(8, 8, 1, 5, 50.00, 0.00, 0.00, 250.00),
(9, 9, 3, 2, 21000.00, 0.00, 0.00, 42000.00),
(10, 10, 3, 1, 21000.00, 0.00, 0.00, 21000.00),
(11, 11, 3, 1, 21000.00, 0.00, 0.00, 21000.00);

-- --------------------------------------------------------

--
-- Table structure for table `detalleplanilla`
--

CREATE TABLE `detalleplanilla` (
  `idDetallePlanilla` int(11) NOT NULL,
  `idPlanilla` int(11) NOT NULL,
  `idEmpleado` int(11) NOT NULL,
  `salarioBruto` decimal(12,2) NOT NULL,
  `inss` decimal(12,2) DEFAULT 0.00,
  `rentaNetaMensual` decimal(12,2) DEFAULT 0.00,
  `rentaNetaAnual` decimal(12,2) DEFAULT 0.00,
  `irAnual` decimal(12,2) DEFAULT 0.00,
  `irMensual` decimal(12,2) DEFAULT 0.00,
  `salarioNeto` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `detalleplanilla`
--

INSERT INTO `detalleplanilla` (`idDetallePlanilla`, `idPlanilla`, `idEmpleado`, `salarioBruto`, `inss`, `rentaNetaMensual`, `rentaNetaAnual`, `irAnual`, `irMensual`, `salarioNeto`) VALUES
(39, 4, 1, 8000.00, 560.00, 7440.00, 89280.00, 0.00, 0.00, 7440.00),
(40, 4, 2, 10000.00, 700.00, 9300.00, 111600.00, 1740.00, 145.00, 9155.00),
(41, 4, 3, 12000.00, 840.00, 11160.00, 133920.00, 5088.00, 424.00, 10736.00),
(42, 4, 4, 15000.00, 1050.00, 13950.00, 167400.00, 10110.00, 842.50, 13107.50),
(43, 4, 5, 18000.00, 1260.00, 16740.00, 200880.00, 15176.00, 1264.67, 15475.33),
(44, 4, 6, 25000.00, 1750.00, 23250.00, 279000.00, 30800.00, 2566.67, 20683.33),
(45, 4, 7, 30000.00, 2100.00, 27900.00, 334800.00, 41960.00, 3496.67, 24403.33),
(46, 4, 8, 35000.00, 2450.00, 32550.00, 390600.00, 55150.00, 4595.83, 27954.17),
(47, 4, 9, 45000.00, 3150.00, 41850.00, 502200.00, 83160.00, 6930.00, 34920.00),
(48, 4, 10, 30000.00, 2100.00, 27900.00, 334800.00, 41960.00, 3496.67, 24403.33),
(49, 4, 11, 24000.00, 1680.00, 22320.00, 267840.00, 28568.00, 2380.67, 19939.33),
(50, 4, 12, 15000.00, 1050.00, 13950.00, 167400.00, 10110.00, 842.50, 13107.50),
(51, 5, 1, 8000.00, 560.00, 7440.00, 89280.00, 0.00, 0.00, 7440.00),
(52, 5, 2, 10000.00, 700.00, 9300.00, 111600.00, 1740.00, 145.00, 9155.00),
(53, 5, 3, 12000.00, 840.00, 11160.00, 133920.00, 5088.00, 424.00, 10736.00),
(54, 5, 4, 15000.00, 1050.00, 13950.00, 167400.00, 10110.00, 842.50, 13107.50),
(55, 5, 5, 18000.00, 1260.00, 16740.00, 200880.00, 15176.00, 1264.67, 15475.33),
(56, 5, 6, 25000.00, 1750.00, 23250.00, 279000.00, 30800.00, 2566.67, 20683.33),
(57, 5, 7, 30000.00, 2100.00, 27900.00, 334800.00, 41960.00, 3496.67, 24403.33),
(58, 5, 8, 35000.00, 2450.00, 32550.00, 390600.00, 55150.00, 4595.83, 27954.17),
(59, 5, 9, 45000.00, 3150.00, 41850.00, 502200.00, 83160.00, 6930.00, 34920.00),
(60, 5, 10, 30000.00, 2100.00, 27900.00, 334800.00, 41960.00, 3496.67, 24403.33),
(61, 5, 11, 24000.00, 1680.00, 22320.00, 267840.00, 28568.00, 2380.67, 19939.33),
(62, 5, 12, 15000.00, 1050.00, 13950.00, 167400.00, 10110.00, 842.50, 13107.50),
(63, 5, 13, 15000.00, 1050.00, 13950.00, 167400.00, 10110.00, 842.50, 13107.50),
(64, 5, 14, 18000.00, 1260.00, 16740.00, 200880.00, 15176.00, 1264.67, 15475.33),
(65, 5, 15, 22000.00, 1540.00, 20460.00, 245520.00, 24104.00, 2008.67, 18451.33),
(66, 5, 16, 28000.00, 1960.00, 26040.00, 312480.00, 37496.00, 3124.67, 22915.33),
(67, 5, 17, 35000.00, 2450.00, 32550.00, 390600.00, 55150.00, 4595.83, 27954.17),
(68, 5, 18, 42000.00, 2940.00, 39060.00, 468720.00, 74680.00, 6223.33, 32836.67),
(69, 5, 19, 50000.00, 3500.00, 46500.00, 558000.00, 99900.00, 8325.00, 38175.00),
(70, 5, 20, 58000.00, 4060.00, 53940.00, 647280.00, 126684.00, 10557.00, 43383.00),
(71, 5, 21, 65000.00, 4550.00, 60450.00, 725400.00, 150120.00, 12510.00, 47940.00),
(72, 5, 22, 72000.00, 5040.00, 66960.00, 803520.00, 173556.00, 14463.00, 52497.00),
(73, 5, 23, 85000.00, 5950.00, 79050.00, 948600.00, 217080.00, 18090.00, 60960.00),
(74, 5, 24, 100000.00, 7000.00, 93000.00, 1116000.00, 267300.00, 22275.00, 70725.00);

--
-- Triggers `detalleplanilla`
--
DELIMITER $$
CREATE TRIGGER `trg_CalcularPlanilla` BEFORE INSERT ON `detalleplanilla` FOR EACH ROW BEGIN

    SET NEW.inss =
        ROUND(NEW.salarioBruto * 0.07, 2);

    SET NEW.rentaNetaMensual =
        NEW.salarioBruto - NEW.inss;

    SET NEW.rentaNetaAnual =
        NEW.rentaNetaMensual * 12;

    SET NEW.irAnual =
        fn_CalcularIR(NEW.rentaNetaAnual);

    SET NEW.irMensual =
        ROUND(NEW.irAnual / 12, 2);

    SET NEW.salarioNeto =
        NEW.salarioBruto
        - NEW.inss
        - NEW.irMensual;

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `detalleproduccion`
--

CREATE TABLE `detalleproduccion` (
  `idDetalle` int(11) NOT NULL,
  `idOrden` int(11) NOT NULL,
  `idProducto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `empleados`
--

CREATE TABLE `empleados` (
  `idEmpleado` int(11) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `cargo` varchar(100) NOT NULL,
  `salarioBase` decimal(12,2) NOT NULL,
  `fechaIngreso` date NOT NULL,
  `estado` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `empleados`
--

INSERT INTO `empleados` (`idEmpleado`, `nombres`, `apellidos`, `cedula`, `cargo`, `salarioBase`, `fechaIngreso`, `estado`) VALUES
(1, 'Carlos', 'González', '001-100101-0001A', 'Auxiliar de Inventario', 8000.00, '2025-01-10', 1),
(2, 'María', 'López', '001-100202-0002B', 'Recepcionista', 10000.00, '2025-02-15', 1),
(3, 'José', 'Martínez', '001-100303-0003C', 'Cajero', 12000.00, '2025-03-20', 1),
(4, 'Ana', 'Rodríguez', '001-100404-0004D', 'Encargada de Compras', 15000.00, '2025-04-05', 1),
(5, 'Luis', 'Hernández', '001-100505-0005E', 'Supervisor de Inventario', 18000.00, '2025-05-12', 1),
(6, 'Gabriela', 'Pérez', '001-100606-0006F', 'Analista de Ventas', 25000.00, '2025-06-18', 1),
(7, 'Juan', 'Ramírez', '001-100707-0007G', 'Administrador', 30000.00, '2025-07-10', 1),
(8, 'Daniela', 'Castillo', '001-100808-0008H', 'Contadora', 35000.00, '2025-08-15', 1),
(9, 'Roberto', 'Mendoza', '001-100909-0009I', 'Gerente de Operaciones', 45000.00, '2025-09-01', 1),
(10, 'Rodolfo', 'Espinoza', '001-290904-1027J', 'Gerente General', 30000.00, '2025-10-10', 1),
(11, 'Onell Isaac', 'Rodríguez Arcia', '0010305051053y', 'admin', 24000.00, '2026-08-13', 1),
(12, 'joctam Josue', 'Uriarte', '0012405061003k', 'cajero', 15000.00, '2026-08-14', 1),
(13, 'Roberto', 'Gutiérrez', '001-100909-0009H', 'Vendedor', 15000.00, '2025-07-01', 1),
(14, 'Laura', 'Mendoza', '001-101010-0010J', 'Asistente Administrativa', 18000.00, '2025-07-05', 1),
(15, 'Miguel', 'Castro', '001-101111-0011K', 'Encargado de Bodega', 22000.00, '2025-07-10', 1),
(16, 'Sofía', 'Torres', '001-101212-0012L', 'Recursos Humanos', 28000.00, '2025-07-15', 1),
(17, 'Ricardo', 'Hernández', '001-101313-0013M', 'Contador', 35000.00, '2025-07-20', 1),
(18, 'Patricia', 'Vargas', '001-101414-0014N', 'Supervisora de Ventas', 42000.00, '2025-07-25', 1),
(19, 'Fernando', 'Martínez', '001-101515-0015P', 'Analista de Sistemas', 50000.00, '2025-08-01', 1),
(20, 'Gabriela', 'Rojas', '001-101616-0016Q', 'Administradora', 58000.00, '2025-08-05', 1),
(21, 'Andrés', 'López', '001-101717-0017R', 'Jefe de Inventario', 65000.00, '2025-08-10', 1),
(22, 'Valeria', 'Ramírez', '001-101818-0018S', 'Jefa de Compras', 72000.00, '2025-08-15', 1),
(23, 'Daniel', 'Pérez', '001-101919-0019T', 'Gerente de Ventas', 85000.00, '2025-08-20', 1),
(24, 'Mariana', 'Castillo', '001-102020-0020U', 'Gerente General', 100000.00, '2025-08-25', 1);

-- --------------------------------------------------------

--
-- Table structure for table `facturas`
--

CREATE TABLE `facturas` (
  `idFactura` int(11) NOT NULL,
  `idVenta` int(11) NOT NULL,
  `numeroFactura` varchar(30) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `estado` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `facturas`
--

INSERT INTO `facturas` (`idFactura`, `idVenta`, `numeroFactura`, `fecha`, `estado`) VALUES
(1, 2, 'FAC-00002', '2026-08-13 18:49:19', 'Pagada'),
(2, 3, 'FAC-00003', '2026-08-13 18:49:48', 'Pagada'),
(3, 4, 'FAC-00004', '2026-08-14 13:45:35', 'Pagada'),
(4, 5, 'FAC-00005', '2026-08-15 17:20:52', 'Pagada'),
(5, 6, 'FAC-00006', '2026-08-15 20:15:49', 'Pagada'),
(6, 7, 'FAC-00007', '2026-08-15 20:20:38', 'Pagada'),
(7, 8, 'FAC-00008', '2026-08-25 08:55:46', 'Pagada'),
(8, 9, 'FAC-00009', '2026-08-27 20:09:06', 'Pagada'),
(9, 10, 'FAC-00010', '2026-08-27 21:49:13', 'Pagada'),
(10, 11, 'FAC-00011', '2026-08-27 21:55:58', 'Pagada'),
(11, 12, 'FAC-00012', '2026-08-27 21:56:22', 'Pagada');

-- --------------------------------------------------------

--
-- Table structure for table `historiallogin`
--

CREATE TABLE `historiallogin` (
  `idHistorial` int(11) NOT NULL,
  `idUsuario` int(11) NOT NULL,
  `fechaIngreso` datetime DEFAULT current_timestamp(),
  `ip` varchar(50) DEFAULT NULL,
  `dispositivo` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marcas`
--

CREATE TABLE `marcas` (
  `idMarca` int(11) NOT NULL,
  `nombreMarca` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `marcas`
--

INSERT INTO `marcas` (`idMarca`, `nombreMarca`) VALUES
(1, 'Loro'),
(3, 'Dell');

-- --------------------------------------------------------

--
-- Table structure for table `mermas`
--

CREATE TABLE `mermas` (
  `idMerma` int(11) NOT NULL,
  `idProducto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `motivo` varchar(250) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `idUsuario` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mermas`
--

INSERT INTO `mermas` (`idMerma`, `idProducto`, `cantidad`, `motivo`, `fecha`, `idUsuario`) VALUES
(19, 3, 2, 'Daño', '2026-08-27 20:07:29', 1);

-- --------------------------------------------------------

--
-- Table structure for table `movimientosinventario`
--

CREATE TABLE `movimientosinventario` (
  `idMovimiento` int(11) NOT NULL,
  `idProducto` int(11) NOT NULL,
  `idUsuario` int(11) NOT NULL,
  `tipoMovimiento` varchar(20) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `observacion` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `movimientosinventario`
--

INSERT INTO `movimientosinventario` (`idMovimiento`, `idProducto`, `idUsuario`, `tipoMovimiento`, `cantidad`, `fecha`, `observacion`) VALUES
(4, 1, 1, 'Entrada', 10, '2026-08-15 16:55:46', 'falla'),
(5, 1, 1, 'Entrada', 10, '2026-08-15 16:59:30', 'nada'),
(6, 3, 1, 'Entrada', 10, '2026-08-27 20:02:03', '');

-- --------------------------------------------------------

--
-- Table structure for table `ordenesproduccion`
--

CREATE TABLE `ordenesproduccion` (
  `idOrden` int(11) NOT NULL,
  `numeroOrden` varchar(20) NOT NULL,
  `idProducto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `fechaInicio` date DEFAULT NULL,
  `fechaFin` date DEFAULT NULL,
  `estado` varchar(20) DEFAULT 'Pendiente',
  `prioridad` varchar(20) DEFAULT 'Normal',
  `observaciones` text DEFAULT NULL,
  `idUsuario` int(11) NOT NULL,
  `fechaRegistro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permisos`
--

CREATE TABLE `permisos` (
  `idPermiso` int(11) NOT NULL,
  `idRol` int(11) NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `acceso` tinyint(1) DEFAULT 1,
  `fechaCreacion` datetime DEFAULT current_timestamp(),
  `fechaModificacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permisos`
--

INSERT INTO `permisos` (`idPermiso`, `idRol`, `modulo`, `acceso`, `fechaCreacion`, `fechaModificacion`) VALUES
(275, 1, 'dashboard', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(276, 1, 'productos', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(277, 1, 'categorias', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(278, 1, 'marcas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(279, 1, 'movimientos', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(280, 1, 'mermas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(281, 1, 'alertas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(282, 1, 'proveedores', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(283, 1, 'compras', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(284, 1, 'clientes', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(285, 1, 'ventas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(286, 1, 'facturas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(287, 1, 'empleados', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(288, 1, 'planilla', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(289, 1, 'reportes', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(290, 1, 'bitacora', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(291, 1, 'usuarios', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(292, 1, 'configuracion', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(293, 1, 'cotizaciones', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(294, 1, 'pedidos', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(295, 1, 'control_calidad', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(296, 1, 'produccion', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(297, 2, 'dashboard', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(298, 2, 'productos', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(299, 2, 'categorias', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(300, 2, 'marcas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(301, 2, 'movimientos', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(302, 2, 'mermas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(303, 2, 'alertas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(304, 2, 'proveedores', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(305, 2, 'compras', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(306, 2, 'clientes', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(307, 2, 'ventas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(308, 2, 'facturas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(309, 2, 'empleados', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(310, 2, 'planilla', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(311, 2, 'reportes', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(312, 2, 'bitacora', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(313, 2, 'usuarios', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(314, 2, 'configuracion', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(315, 2, 'cotizaciones', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(316, 2, 'pedidos', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(317, 2, 'control_calidad', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(318, 2, 'produccion', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(319, 3, 'dashboard', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(320, 3, 'productos', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(321, 3, 'categorias', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(322, 3, 'marcas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(323, 3, 'movimientos', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(324, 3, 'mermas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(325, 3, 'alertas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(326, 3, 'proveedores', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(327, 3, 'compras', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(328, 3, 'clientes', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(329, 3, 'ventas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(330, 3, 'facturas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(331, 3, 'empleados', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(332, 3, 'planilla', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(333, 3, 'reportes', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(334, 3, 'bitacora', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(335, 3, 'usuarios', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(336, 3, 'configuracion', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(337, 3, 'cotizaciones', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(338, 3, 'pedidos', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(339, 3, 'control_calidad', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(340, 3, 'produccion', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(341, 5, 'dashboard', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(342, 5, 'productos', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(343, 5, 'categorias', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(344, 5, 'marcas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(345, 5, 'movimientos', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(346, 5, 'mermas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(347, 5, 'alertas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(348, 5, 'proveedores', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(349, 5, 'compras', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(350, 5, 'clientes', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(351, 5, 'ventas', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(352, 5, 'facturas', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(353, 5, 'empleados', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(354, 5, 'planilla', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(355, 5, 'reportes', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(356, 5, 'bitacora', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(357, 5, 'usuarios', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(358, 5, 'configuracion', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(359, 5, 'cotizaciones', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(360, 5, 'pedidos', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(361, 5, 'control_calidad', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(362, 5, 'produccion', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(363, 6, 'dashboard', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(364, 6, 'productos', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(365, 6, 'categorias', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(366, 6, 'marcas', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(367, 6, 'movimientos', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(368, 6, 'mermas', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(369, 6, 'alertas', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(370, 6, 'proveedores', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(371, 6, 'compras', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(372, 6, 'clientes', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(373, 6, 'ventas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(374, 6, 'facturas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(375, 6, 'empleados', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(376, 6, 'planilla', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(377, 6, 'reportes', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(378, 6, 'bitacora', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(379, 6, 'usuarios', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(380, 6, 'configuracion', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(381, 6, 'cotizaciones', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(382, 6, 'pedidos', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(383, 6, 'control_calidad', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(384, 6, 'produccion', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(385, 4, 'dashboard', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(386, 4, 'productos', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(387, 4, 'categorias', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(388, 4, 'marcas', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(389, 4, 'movimientos', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(390, 4, 'mermas', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(391, 4, 'alertas', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(392, 4, 'proveedores', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(393, 4, 'compras', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(394, 4, 'clientes', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(395, 4, 'ventas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(396, 4, 'facturas', 1, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(397, 4, 'empleados', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(398, 4, 'planilla', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(399, 4, 'reportes', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(400, 4, 'bitacora', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(401, 4, 'usuarios', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(402, 4, 'configuracion', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(403, 4, 'cotizaciones', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(404, 4, 'pedidos', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(405, 4, 'control_calidad', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26'),
(406, 4, 'produccion', 0, '2026-08-24 17:23:26', '2026-08-24 17:23:26');

-- --------------------------------------------------------

--
-- Table structure for table `planillas`
--

CREATE TABLE `planillas` (
  `idPlanilla` int(11) NOT NULL,
  `periodo` varchar(7) NOT NULL,
  `fechaGeneracion` datetime DEFAULT current_timestamp(),
  `totalBruto` decimal(12,2) DEFAULT 0.00,
  `totalINSS` decimal(12,2) DEFAULT 0.00,
  `totalIR` decimal(12,2) DEFAULT 0.00,
  `totalNeto` decimal(12,2) DEFAULT 0.00,
  `estado` varchar(20) DEFAULT 'Generada'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `planillas`
--

INSERT INTO `planillas` (`idPlanilla`, `periodo`, `fechaGeneracion`, `totalBruto`, `totalINSS`, `totalIR`, `totalNeto`, `estado`) VALUES
(4, '2026-08', '2026-08-31 16:45:56', 267000.00, 18690.00, 26985.18, 221324.82, 'Generada'),
(5, '2026-09', '2026-09-01 08:45:49', 857000.00, 59990.00, 131264.85, 665745.15, 'Generada');

-- --------------------------------------------------------

--
-- Table structure for table `productos`
--

CREATE TABLE `productos` (
  `idProducto` int(11) NOT NULL,
  `idCategoria` int(11) NOT NULL,
  `idMarca` int(11) NOT NULL,
  `codigoBarra` varchar(50) DEFAULT NULL,
  `nombreProducto` varchar(150) NOT NULL,
  `descripcion` varchar(250) DEFAULT NULL,
  `precioCompra` decimal(10,2) DEFAULT NULL,
  `precioVenta` decimal(10,2) DEFAULT NULL,
  `stockMinimo` int(11) DEFAULT NULL,
  `stockActual` int(11) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `productos`
--

INSERT INTO `productos` (`idProducto`, `idCategoria`, `idMarca`, `codigoBarra`, `nombreProducto`, `descripcion`, `precioCompra`, `precioVenta`, `stockMinimo`, `stockActual`, `estado`) VALUES
(1, 1, 1, '123456789', 'cuaderno rallado', 'rallado xd', 35.00, 50.00, 5, 4, 1),
(2, 1, 1, '123', 'Pureba 7474', 'Prueba55574414', 88.00, 88.00, 99, 99, 0),
(3, 4, 3, '45681254', 'Dell Latitud', 'computadora core I5 12U', 18000.00, 21000.00, 3, 8, 1);

-- --------------------------------------------------------

--
-- Table structure for table `proveedores`
--

CREATE TABLE `proveedores` (
  `idProveedor` int(11) NOT NULL,
  `nombreEmpresa` varchar(150) NOT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `direccion` varchar(250) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `proveedores`
--

INSERT INTO `proveedores` (`idProveedor`, `nombreEmpresa`, `contacto`, `telefono`, `correo`, `direccion`, `estado`) VALUES
(1, 'cdn', 'juan', '18002626', 'cdn@gmail.com', 'carretera norte', 1);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `idRol` int(11) NOT NULL,
  `nombreRol` varchar(50) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`idRol`, `nombreRol`, `descripcion`) VALUES
(1, 'Administrador', 'Acceso total al sistema, configuración y administración'),
(2, 'Gerente', 'Acceso a reportes, planilla y gestión de empleados'),
(3, 'Supervisor', 'Acceso a inventario, compras y ventas'),
(4, 'Vendedor', 'Acceso a ventas y clientes'),
(5, 'Almacenista', 'Acceso a productos, movimientos y compras'),
(6, 'Cajero', 'Acceso a ventas y facturas');

-- --------------------------------------------------------

--
-- Table structure for table `tablair`
--

CREATE TABLE `tablair` (
  `idIR` int(11) NOT NULL,
  `limiteInferior` decimal(12,2) NOT NULL,
  `limiteSuperior` decimal(12,2) DEFAULT NULL,
  `impuestoBase` decimal(12,2) NOT NULL,
  `porcentaje` decimal(5,2) NOT NULL,
  `sobreExceso` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tablair`
--

INSERT INTO `tablair` (`idIR`, `limiteInferior`, `limiteSuperior`, `impuestoBase`, `porcentaje`, `sobreExceso`) VALUES
(1, 0.01, 100000.00, 0.00, 0.00, 0.00),
(2, 100000.01, 200000.00, 0.00, 15.00, 100000.00),
(3, 200000.01, 350000.00, 15000.00, 20.00, 200000.00),
(4, 350000.01, 500000.00, 45000.00, 25.00, 350000.00),
(5, 500000.01, NULL, 82500.00, 30.00, 500000.00);

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `idUsuario` int(11) NOT NULL,
  `idRol` int(11) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`idUsuario`, `idRol`, `nombres`, `apellidos`, `usuario`, `correo`, `password`, `telefono`, `estado`) VALUES
(1, 1, 'Onell Isaac', 'Rodríguez Arcia', 'onell', 'rodriguezonell2005@gmail.com', '$2y$10$em9FZX9CAIZJMM.5iCM8/OuHyaBUc3B02k2dLP73El.Ten.ydSd6O', '82908815', 1),
(2, 3, 'Brisa Rebeca', 'Jimenez', 'Brisa', 'Brisa@gmail.com', '$2y$10$AATg6rpmMi2N3MsD/eG1OO/hJy8YXYDXXhUnOEbwHxfc3OVkanSAe', '18002626', 1),
(3, 2, 'joctam Josue', 'Uriarte', 'Joctam', 'joc@gmail.con', '$2y$10$zW6F1j1xfxAuQqtmM.7uP.37TU03Lr214JopRLtJrhbXza.6SJj7C', '18777784', 1),
(4, 6, 'Rodolfo junior ', 'Espinoza', 'rodolfo', 'rodolfo@gmail.com', '$2y$10$tSqXHxx9bMXNK8n2FY.aWu9.coGZlbyt/GvrDQODpmqxWMAa871mi', '12684855', 1),
(5, 5, 'Dylan ', 'saborio alegria', 'dylan', 'dylan@gmail.con', '$2y$10$7w6ndrWVhDT4eWu0ltWG2ugcf0L4ueB9wSU8qc/IpsMo6aRL23hka', '24154185', 1);

-- --------------------------------------------------------

--
-- Table structure for table `ventas`
--

CREATE TABLE `ventas` (
  `idVenta` int(11) NOT NULL,
  `idCliente` int(11) NOT NULL,
  `idUsuario` int(11) NOT NULL,
  `fechaVenta` datetime DEFAULT current_timestamp(),
  `subtotal` decimal(10,2) DEFAULT NULL,
  `descuento` decimal(10,2) DEFAULT NULL,
  `iva` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ventas`
--

INSERT INTO `ventas` (`idVenta`, `idCliente`, `idUsuario`, `fechaVenta`, `subtotal`, `descuento`, `iva`, `total`) VALUES
(2, 1, 1, '2026-08-13 18:49:19', 300.00, 0.00, 45.00, 345.00),
(3, 3, 1, '2026-08-13 18:49:48', 50.00, 0.00, 7.50, 57.50),
(4, 3, 1, '2026-08-14 13:45:35', 500.00, 15.00, 72.75, 557.75),
(5, 1, 1, '2026-08-15 17:20:52', 150.00, 15.00, 20.25, 155.25),
(6, 1, 1, '2026-08-15 20:15:49', 21000.00, 1000.00, 3000.00, 23000.00),
(7, 3, 1, '2026-08-15 20:20:38', 100.00, 0.00, 15.00, 115.00),
(8, 3, 3, '2026-08-25 08:55:46', 210000.00, 0.00, 31500.00, 241500.00),
(9, 1, 1, '2026-08-27 20:09:06', 250.00, 0.00, 37.50, 287.50),
(10, 4, 1, '2026-08-27 21:49:13', 42000.00, 0.00, 6300.00, 48300.00),
(11, 3, 4, '2026-08-27 21:55:58', 21000.00, 0.00, 3150.00, 24150.00),
(12, 5, 4, '2026-08-27 21:56:22', 21000.00, 0.00, 3150.00, 24150.00);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alertas`
--
ALTER TABLE `alertas`
  ADD PRIMARY KEY (`idAlerta`),
  ADD KEY `idProducto` (`idProducto`);

--
-- Indexes for table `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`idAuditoria`),
  ADD KEY `idUsuario` (`idUsuario`);

--
-- Indexes for table `bitacora`
--
ALTER TABLE `bitacora`
  ADD PRIMARY KEY (`idBitacora`),
  ADD KEY `idUsuario` (`idUsuario`);

--
-- Indexes for table `bitacoraproduccion`
--
ALTER TABLE `bitacoraproduccion`
  ADD PRIMARY KEY (`idBitacora`),
  ADD KEY `idOrden` (`idOrden`),
  ADD KEY `idUsuario` (`idUsuario`);

--
-- Indexes for table `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`idCategoria`);

--
-- Indexes for table `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`idCliente`);

--
-- Indexes for table `compras`
--
ALTER TABLE `compras`
  ADD PRIMARY KEY (`idCompra`),
  ADD KEY `idProveedor` (`idProveedor`),
  ADD KEY `idUsuario` (`idUsuario`);

--
-- Indexes for table `configuracionsistema`
--
ALTER TABLE `configuracionsistema`
  ADD PRIMARY KEY (`idConfiguracion`);

--
-- Indexes for table `controlescalidad`
--
ALTER TABLE `controlescalidad`
  ADD PRIMARY KEY (`idControl`);

--
-- Indexes for table `detallecompras`
--
ALTER TABLE `detallecompras`
  ADD PRIMARY KEY (`idDetalleCompra`),
  ADD KEY `idCompra` (`idCompra`),
  ADD KEY `idProducto` (`idProducto`);

--
-- Indexes for table `detallefacturas`
--
ALTER TABLE `detallefacturas`
  ADD PRIMARY KEY (`idDetalleFactura`),
  ADD KEY `idFactura` (`idFactura`),
  ADD KEY `idProducto` (`idProducto`);

--
-- Indexes for table `detalleplanilla`
--
ALTER TABLE `detalleplanilla`
  ADD PRIMARY KEY (`idDetallePlanilla`),
  ADD KEY `fk_detalle_planilla` (`idPlanilla`),
  ADD KEY `fk_detalle_empleado` (`idEmpleado`);

--
-- Indexes for table `detalleproduccion`
--
ALTER TABLE `detalleproduccion`
  ADD PRIMARY KEY (`idDetalle`),
  ADD KEY `idOrden` (`idOrden`),
  ADD KEY `idProducto` (`idProducto`);

--
-- Indexes for table `empleados`
--
ALTER TABLE `empleados`
  ADD PRIMARY KEY (`idEmpleado`),
  ADD UNIQUE KEY `cedula` (`cedula`);

--
-- Indexes for table `facturas`
--
ALTER TABLE `facturas`
  ADD PRIMARY KEY (`idFactura`),
  ADD KEY `idVenta` (`idVenta`);

--
-- Indexes for table `historiallogin`
--
ALTER TABLE `historiallogin`
  ADD PRIMARY KEY (`idHistorial`),
  ADD KEY `idUsuario` (`idUsuario`);

--
-- Indexes for table `marcas`
--
ALTER TABLE `marcas`
  ADD PRIMARY KEY (`idMarca`);

--
-- Indexes for table `mermas`
--
ALTER TABLE `mermas`
  ADD PRIMARY KEY (`idMerma`),
  ADD KEY `idProducto` (`idProducto`),
  ADD KEY `idUsuario` (`idUsuario`);

--
-- Indexes for table `movimientosinventario`
--
ALTER TABLE `movimientosinventario`
  ADD PRIMARY KEY (`idMovimiento`),
  ADD KEY `idProducto` (`idProducto`),
  ADD KEY `idUsuario` (`idUsuario`);

--
-- Indexes for table `ordenesproduccion`
--
ALTER TABLE `ordenesproduccion`
  ADD PRIMARY KEY (`idOrden`),
  ADD UNIQUE KEY `numeroOrden` (`numeroOrden`),
  ADD KEY `idProducto` (`idProducto`),
  ADD KEY `idUsuario` (`idUsuario`);

--
-- Indexes for table `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`idPermiso`),
  ADD UNIQUE KEY `unique_permiso` (`idRol`,`modulo`);

--
-- Indexes for table `planillas`
--
ALTER TABLE `planillas`
  ADD PRIMARY KEY (`idPlanilla`);

--
-- Indexes for table `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`idProducto`),
  ADD UNIQUE KEY `codigoBarra` (`codigoBarra`),
  ADD KEY `idCategoria` (`idCategoria`),
  ADD KEY `idMarca` (`idMarca`);

--
-- Indexes for table `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`idProveedor`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`idRol`);

--
-- Indexes for table `tablair`
--
ALTER TABLE `tablair`
  ADD PRIMARY KEY (`idIR`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`idUsuario`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD KEY `idRol` (`idRol`);

--
-- Indexes for table `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`idVenta`),
  ADD KEY `idCliente` (`idCliente`),
  ADD KEY `idUsuario` (`idUsuario`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alertas`
--
ALTER TABLE `alertas`
  MODIFY `idAlerta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `idAuditoria` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bitacora`
--
ALTER TABLE `bitacora`
  MODIFY `idBitacora` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bitacoraproduccion`
--
ALTER TABLE `bitacoraproduccion`
  MODIFY `idBitacora` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categorias`
--
ALTER TABLE `categorias`
  MODIFY `idCategoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `clientes`
--
ALTER TABLE `clientes`
  MODIFY `idCliente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `compras`
--
ALTER TABLE `compras`
  MODIFY `idCompra` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `configuracionsistema`
--
ALTER TABLE `configuracionsistema`
  MODIFY `idConfiguracion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `controlescalidad`
--
ALTER TABLE `controlescalidad`
  MODIFY `idControl` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `detallecompras`
--
ALTER TABLE `detallecompras`
  MODIFY `idDetalleCompra` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `detallefacturas`
--
ALTER TABLE `detallefacturas`
  MODIFY `idDetalleFactura` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `detalleplanilla`
--
ALTER TABLE `detalleplanilla`
  MODIFY `idDetallePlanilla` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `detalleproduccion`
--
ALTER TABLE `detalleproduccion`
  MODIFY `idDetalle` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `empleados`
--
ALTER TABLE `empleados`
  MODIFY `idEmpleado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `facturas`
--
ALTER TABLE `facturas`
  MODIFY `idFactura` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `historiallogin`
--
ALTER TABLE `historiallogin`
  MODIFY `idHistorial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `marcas`
--
ALTER TABLE `marcas`
  MODIFY `idMarca` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `mermas`
--
ALTER TABLE `mermas`
  MODIFY `idMerma` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `movimientosinventario`
--
ALTER TABLE `movimientosinventario`
  MODIFY `idMovimiento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `ordenesproduccion`
--
ALTER TABLE `ordenesproduccion`
  MODIFY `idOrden` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permisos`
--
ALTER TABLE `permisos`
  MODIFY `idPermiso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=407;

--
-- AUTO_INCREMENT for table `planillas`
--
ALTER TABLE `planillas`
  MODIFY `idPlanilla` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `productos`
--
ALTER TABLE `productos`
  MODIFY `idProducto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `idProveedor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `idRol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tablair`
--
ALTER TABLE `tablair`
  MODIFY `idIR` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `idUsuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `ventas`
--
ALTER TABLE `ventas`
  MODIFY `idVenta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alertas`
--
ALTER TABLE `alertas`
  ADD CONSTRAINT `alertas_ibfk_1` FOREIGN KEY (`idProducto`) REFERENCES `productos` (`idProducto`);

--
-- Constraints for table `auditoria`
--
ALTER TABLE `auditoria`
  ADD CONSTRAINT `auditoria_ibfk_1` FOREIGN KEY (`idUsuario`) REFERENCES `usuarios` (`idUsuario`);

--
-- Constraints for table `bitacora`
--
ALTER TABLE `bitacora`
  ADD CONSTRAINT `bitacora_ibfk_1` FOREIGN KEY (`idUsuario`) REFERENCES `usuarios` (`idUsuario`);

--
-- Constraints for table `bitacoraproduccion`
--
ALTER TABLE `bitacoraproduccion`
  ADD CONSTRAINT `bitacoraproduccion_ibfk_1` FOREIGN KEY (`idOrden`) REFERENCES `ordenesproduccion` (`idOrden`),
  ADD CONSTRAINT `bitacoraproduccion_ibfk_2` FOREIGN KEY (`idUsuario`) REFERENCES `usuarios` (`idUsuario`);

--
-- Constraints for table `compras`
--
ALTER TABLE `compras`
  ADD CONSTRAINT `compras_ibfk_1` FOREIGN KEY (`idProveedor`) REFERENCES `proveedores` (`idProveedor`),
  ADD CONSTRAINT `compras_ibfk_2` FOREIGN KEY (`idUsuario`) REFERENCES `usuarios` (`idUsuario`);

--
-- Constraints for table `detallecompras`
--
ALTER TABLE `detallecompras`
  ADD CONSTRAINT `detallecompras_ibfk_1` FOREIGN KEY (`idCompra`) REFERENCES `compras` (`idCompra`),
  ADD CONSTRAINT `detallecompras_ibfk_2` FOREIGN KEY (`idProducto`) REFERENCES `productos` (`idProducto`);

--
-- Constraints for table `detallefacturas`
--
ALTER TABLE `detallefacturas`
  ADD CONSTRAINT `detallefacturas_ibfk_1` FOREIGN KEY (`idFactura`) REFERENCES `facturas` (`idFactura`),
  ADD CONSTRAINT `detallefacturas_ibfk_2` FOREIGN KEY (`idProducto`) REFERENCES `productos` (`idProducto`);

--
-- Constraints for table `detalleplanilla`
--
ALTER TABLE `detalleplanilla`
  ADD CONSTRAINT `fk_detalle_empleado` FOREIGN KEY (`idEmpleado`) REFERENCES `empleados` (`idEmpleado`),
  ADD CONSTRAINT `fk_detalle_planilla` FOREIGN KEY (`idPlanilla`) REFERENCES `planillas` (`idPlanilla`);

--
-- Constraints for table `detalleproduccion`
--
ALTER TABLE `detalleproduccion`
  ADD CONSTRAINT `detalleproduccion_ibfk_1` FOREIGN KEY (`idOrden`) REFERENCES `ordenesproduccion` (`idOrden`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalleproduccion_ibfk_2` FOREIGN KEY (`idProducto`) REFERENCES `productos` (`idProducto`);

--
-- Constraints for table `facturas`
--
ALTER TABLE `facturas`
  ADD CONSTRAINT `facturas_ibfk_1` FOREIGN KEY (`idVenta`) REFERENCES `ventas` (`idVenta`);

--
-- Constraints for table `historiallogin`
--
ALTER TABLE `historiallogin`
  ADD CONSTRAINT `historiallogin_ibfk_1` FOREIGN KEY (`idUsuario`) REFERENCES `usuarios` (`idUsuario`);

--
-- Constraints for table `mermas`
--
ALTER TABLE `mermas`
  ADD CONSTRAINT `mermas_ibfk_1` FOREIGN KEY (`idProducto`) REFERENCES `productos` (`idProducto`),
  ADD CONSTRAINT `mermas_ibfk_2` FOREIGN KEY (`idUsuario`) REFERENCES `usuarios` (`idUsuario`);

--
-- Constraints for table `movimientosinventario`
--
ALTER TABLE `movimientosinventario`
  ADD CONSTRAINT `movimientosinventario_ibfk_1` FOREIGN KEY (`idProducto`) REFERENCES `productos` (`idProducto`),
  ADD CONSTRAINT `movimientosinventario_ibfk_2` FOREIGN KEY (`idUsuario`) REFERENCES `usuarios` (`idUsuario`);

--
-- Constraints for table `ordenesproduccion`
--
ALTER TABLE `ordenesproduccion`
  ADD CONSTRAINT `ordenesproduccion_ibfk_1` FOREIGN KEY (`idProducto`) REFERENCES `productos` (`idProducto`),
  ADD CONSTRAINT `ordenesproduccion_ibfk_2` FOREIGN KEY (`idUsuario`) REFERENCES `usuarios` (`idUsuario`);

--
-- Constraints for table `permisos`
--
ALTER TABLE `permisos`
  ADD CONSTRAINT `permisos_ibfk_1` FOREIGN KEY (`idRol`) REFERENCES `roles` (`idRol`) ON DELETE CASCADE;

--
-- Constraints for table `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`idCategoria`) REFERENCES `categorias` (`idCategoria`),
  ADD CONSTRAINT `productos_ibfk_2` FOREIGN KEY (`idMarca`) REFERENCES `marcas` (`idMarca`);

--
-- Constraints for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`idRol`) REFERENCES `roles` (`idRol`);

--
-- Constraints for table `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `ventas_ibfk_1` FOREIGN KEY (`idCliente`) REFERENCES `clientes` (`idCliente`),
  ADD CONSTRAINT `ventas_ibfk_2` FOREIGN KEY (`idUsuario`) REFERENCES `usuarios` (`idUsuario`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
