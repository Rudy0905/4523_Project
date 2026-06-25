-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主机： 127.0.0.1
-- 生成日期： 2026-06-25 03:23:22
-- 服务器版本： 10.4.32-MariaDB
-- PHP 版本： 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `projectdb`
--

-- --------------------------------------------------------

--
-- 表的结构 `customers`
--

CREATE TABLE `customers` (
  `cid` int(11) NOT NULL,
  `cname` varchar(255) NOT NULL,
  `cpassword` varchar(255) NOT NULL,
  `ctel` varchar(20) NOT NULL,
  `caddr` varchar(255) NOT NULL,
  `company` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `customers`
--

INSERT INTO `customers` (`cid`, `cname`, `cpassword`, `ctel`, `caddr`, `company`) VALUES
(1, 'taiman', 'cust123', '23456789', 'Flat A, 12/F, Sunshine Building, Mong Kok, Kowloon', 'ABC Trading Ltd.'),
(2, 'siuming', 'cust456', '98765432', 'Room 8, 3/F, Harbour View Court, Tsuen Wan, New Territories', NULL);

-- --------------------------------------------------------

--
-- 表的结构 `furniturematerials`
--

CREATE TABLE `furniturematerials` (
  `fid` int(11) NOT NULL,
  `mid` int(11) NOT NULL,
  `pmqty` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `furniturematerials`
--

INSERT INTO `furniturematerials` (`fid`, `mid`, `pmqty`) VALUES
(1, 1, 2),
(2, 1, 10),
(3, 1, 5),
(3, 3, 10),
(3, 4, 3),
(4, 1, 15),
(5, 1, 4),
(5, 2, 6),
(6, 1, 12);

-- --------------------------------------------------------

--
-- 表的结构 `furnitures`
--

CREATE TABLE `furnitures` (
  `fid` int(11) NOT NULL,
  `fname` varchar(255) NOT NULL,
  `fdesc` varchar(255) NOT NULL,
  `fprice` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `furnitures`
--

INSERT INTO `furnitures` (`fid`, `fname`, `fdesc`, `fprice`) VALUES
(1, 'Oak Dining Chair', 'Classic style dining chair made of solid oak.', 450.00),
(2, 'Large Dining Table', '6-seater dining table, perfect for families.', 2500.00),
(3, '3-Seater Fabric Sofa', 'Comfortable grey fabric sofa with foam filling.', 3800.00),
(4, 'Wooden Wardrobe', 'Double door wardrobe with hanging space.', 1800.00),
(5, 'Industrial Bookshelf', 'Modern style bookshelf with steel frame.', 1200.00),
(6, 'Queen Size Bed Frame', 'Sturdy bed frame for queen size mattress.', 2200.00);

-- --------------------------------------------------------

--
-- 表的结构 `materials`
--

CREATE TABLE `materials` (
  `mid` int(11) NOT NULL,
  `mname` varchar(255) NOT NULL,
  `mqty` int(11) NOT NULL DEFAULT 0,
  `munit` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `materials`
--

INSERT INTO `materials` (`mid`, `mname`, `mqty`, `munit`) VALUES
(1, 'Oak Wood Plank', 498, 'pcs'),
(2, 'Steel Tube', 200, 'meter'),
(3, 'Fabric Cloth', 100, 'meter'),
(4, 'High Density Foam', 50, 'block');

-- --------------------------------------------------------

--
-- 表的结构 `orderfurnitures`
--

CREATE TABLE `orderfurnitures` (
  `oid` int(11) NOT NULL,
  `fid` int(11) NOT NULL,
  `oqty` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `orderfurnitures`
--

INSERT INTO `orderfurnitures` (`oid`, `fid`, `oqty`) VALUES
(1, 1, 1),
(2, 6, 1);

-- --------------------------------------------------------

--
-- 表的结构 `orders`
--

CREATE TABLE `orders` (
  `oid` int(11) NOT NULL,
  `odate` datetime NOT NULL DEFAULT current_timestamp(),
  `ototalamount` decimal(10,2) NOT NULL,
  `cid` int(11) NOT NULL,
  `odeliverydate` datetime NOT NULL,
  `odeliveraddress` text NOT NULL,
  `ostatus` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `orders`
--

INSERT INTO `orders` (`oid`, `odate`, `ototalamount`, `cid`, `odeliverydate`, `odeliveraddress`, `ostatus`) VALUES
(1, '2026-06-23 19:14:29', 450.00, 1, '2026-04-10 14:00:00', 'Flat A, 12/F, Sunshine Building, Mong Kok, Kowloon', 1),
(2, '2026-06-23 19:14:29', 2200.00, 1, '2026-04-12 10:00:00', 'Flat A, 12/F, Sunshine Building, Mong Kok, Kowloon', 1);

-- --------------------------------------------------------

--
-- 表的结构 `staffs`
--

CREATE TABLE `staffs` (
  `sid` int(11) NOT NULL,
  `spassword` varchar(255) NOT NULL,
  `sname` varchar(255) NOT NULL,
  `srole` varchar(50) NOT NULL,
  `stel` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `staffs`
--

INSERT INTO `staffs` (`sid`, `spassword`, `sname`, `srole`, `stel`) VALUES
(1, 'admin', 'Admin', 'Administrator', '12345678');

--
-- 转储表的索引
--

--
-- 表的索引 `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`cid`);

--
-- 表的索引 `furniturematerials`
--
ALTER TABLE `furniturematerials`
  ADD PRIMARY KEY (`fid`,`mid`),
  ADD KEY `mid` (`mid`);

--
-- 表的索引 `furnitures`
--
ALTER TABLE `furnitures`
  ADD PRIMARY KEY (`fid`);

--
-- 表的索引 `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`mid`);

--
-- 表的索引 `orderfurnitures`
--
ALTER TABLE `orderfurnitures`
  ADD PRIMARY KEY (`fid`,`oid`),
  ADD KEY `oid` (`oid`);

--
-- 表的索引 `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`oid`),
  ADD KEY `cid` (`cid`);

--
-- 表的索引 `staffs`
--
ALTER TABLE `staffs`
  ADD PRIMARY KEY (`sid`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `customers`
--
ALTER TABLE `customers`
  MODIFY `cid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用表AUTO_INCREMENT `furnitures`
--
ALTER TABLE `furnitures`
  MODIFY `fid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- 使用表AUTO_INCREMENT `materials`
--
ALTER TABLE `materials`
  MODIFY `mid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- 使用表AUTO_INCREMENT `orders`
--
ALTER TABLE `orders`
  MODIFY `oid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `staffs`
--
ALTER TABLE `staffs`
  MODIFY `sid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 限制导出的表
--

--
-- 限制表 `furniturematerials`
--
ALTER TABLE `furniturematerials`
  ADD CONSTRAINT `furniturematerials_ibfk_1` FOREIGN KEY (`fid`) REFERENCES `furnitures` (`fid`),
  ADD CONSTRAINT `furniturematerials_ibfk_2` FOREIGN KEY (`mid`) REFERENCES `materials` (`mid`);

--
-- 限制表 `orderfurnitures`
--
ALTER TABLE `orderfurnitures`
  ADD CONSTRAINT `orderfurnitures_ibfk_1` FOREIGN KEY (`fid`) REFERENCES `furnitures` (`fid`),
  ADD CONSTRAINT `orderfurnitures_ibfk_2` FOREIGN KEY (`oid`) REFERENCES `orders` (`oid`);

--
-- 限制表 `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`cid`) REFERENCES `customers` (`cid`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
