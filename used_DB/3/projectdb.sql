-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主机： 127.0.0.1
-- 生成日期： 2026-06-26 12:48:02
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
(6, 1, 12),
(8, 3, 3),
(9, 1, 2),
(9, 3, 2),
(10, 1, 2),
(11, 2, 1),
(11, 3, 2),
(12, 1, 1),
(12, 3, 2),
(13, 1, 2),
(13, 2, 2),
(14, 2, 1),
(14, 3, 2),
(15, 2, 4),
(16, 1, 3);

-- --------------------------------------------------------

--
-- 表的结构 `furnitures`
--

CREATE TABLE `furnitures` (
  `fid` int(11) NOT NULL,
  `fname` varchar(255) NOT NULL,
  `fdesc` varchar(255) NOT NULL,
  `fprice` decimal(10,2) NOT NULL,
  `fimage` varchar(255) NOT NULL DEFAULT 'default.png',
  `fcategory` varchar(50) NOT NULL DEFAULT 'seating',
  `froom` varchar(50) NOT NULL DEFAULT 'living'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `furnitures`
--

INSERT INTO `furnitures` (`fid`, `fname`, `fdesc`, `fprice`, `fimage`, `fcategory`, `froom`) VALUES
(1, 'Oak Dining Chair', 'Classic style dining chair made of solid oak.', 450.00, 'WoodenRider1.png', 'seating', 'dining'),
(2, 'Large Dining Table', '6-seater dining table, perfect for families.', 2500.00, 'WoodenTable1.png', 'tables', 'dining'),
(3, '3-Seater Fabric Sofa', 'Comfortable grey fabric sofa with foam filling.', 3800.00, 'Sofa1.png', 'seating', 'living'),
(4, 'Wooden Wardrobe', 'Double door wardrobe with hanging space.', 1800.00, 'WoodenWardrobe1.png', 'storage', 'bedroom'),
(5, 'Industrial Bookshelf', 'Modern style bookshelf with steel frame.', 1200.00, 'Shelf1.png', 'storage', 'study'),
(6, 'Queen Size Bed Frame', 'Sturdy bed frame for queen size mattress.', 2200.00, 'WoodenBed1.png', 'beds', 'bedroom'),
(8, 'Modern minimalist fabric sofa', 'Three-seater design, high-density resilient foam filling, removable and washable premium fabric, Scandinavian minimalist style, sturdy solid wood frame.', 6000.00, '1782417666_sofa2.jfif', 'seating', 'living'),
(9, 'Nordic Style Double Bed', 'Imported solid wood bed frame, environmentally friendly boards, simple curved headboard design, sturdy and load-bearing, suitable for various bedroom styles', 4599.00, '1782417797_bed2.jfif', 'beds', 'bedroom'),
(10, 'Solid Wood Dining Table', 'Imported North American walnut/oak wood, simple tabletop design, sturdy and durable solid wood legs, seats 4-6 people.', 1500.00, '1782417876_table2.jfif', 'tables', 'dining'),
(11, 'Modern lounge chair', 'Ergonomic curved backrest, comfortable seat, solid wood legs, available in multiple colors, suitable for living room / study / bedroom.', 1699.00, '1782469370_chair2.jfif', 'seating', 'bedroom'),
(12, 'Modern lounge chair (luxury style)', 'Premium velvet fabric, high-elasticity sponge filling, brass metal legs—stylish and elegant, enhancing the overall quality of the space.', 2299.00, '1782469524_chair3.jfif', 'seating', 'bedroom'),
(13, 'round coffee table', 'Sintered stone/marble countertop, scratch-resistant and easy to clean, black metal cross brackets, simple and modern design, versatile for various living room styles.', 2000.00, '1782469711_coffeeTable.jfif', 'tables', 'living'),
(14, 'Leather single sofa chair', 'Top-grain cowhide leather upholstery, high-density resilient foam, brass metal legs—a touch of understated luxury, providing comfort for extended sitting.', 3000.00, '1782469799_sofa3.jfif', 'seating', 'living'),
(15, 'Luxury Bookshelf', 'The tempered glass door design, gold metal frame, and multi-layered storage space combine display and storage functions, enhancing the style of the study.', 6000.00, '1782470170_cabinet2.jfif', 'storage', 'study'),
(16, 'Minimalist Wardrobe', 'Large storage space, hinged door design, environmentally friendly E0 grade board, simple and versatile, suitable for various bedroom styles.', 4000.00, '1782470215_Wardrobe2.jfif', 'seating', 'bedroom');

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
(1, 'Oak Wood Plank', 487, 'pcs'),
(2, 'Steel Tube', 194, 'meter'),
(3, 'Fabric Cloth', 84, 'meter'),
(4, 'High Density Foam', 47, 'block');

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
(2, 6, 1),
(7, 8, 1),
(9, 8, 1);

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
(1, '2026-06-23 19:14:29', 450.00, 1, '2026-04-10 14:00:00', 'Flat A, 12/F, Sunshine Building, Mong Kok, Kowloon', 3),
(2, '2026-06-23 19:14:29', 2200.00, 1, '2026-04-12 10:00:00', 'Flat A, 12/F, Sunshine Building, Mong Kok, Kowloon', 5),
(7, '2026-06-26 05:15:34', 6000.00, 1, '2026-07-03 05:15:00', 'Flat A, 12/F, Sunshine Building, Mong Kok, Kowloon', 2),
(9, '2026-06-26 05:51:39', 6000.00, 1, '2026-07-03 05:51:00', 'Flat A, 12/F, Sunshine Building, Mong Kok, Kowloon', 5);

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

-- --------------------------------------------------------

--
-- 表的结构 `ticketmessages`
--

CREATE TABLE `ticketmessages` (
  `tmid` int(11) NOT NULL,
  `tid` int(11) NOT NULL,
  `sender_role` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `ticketmessages`
--

INSERT INTO `ticketmessages` (`tmid`, `tid`, `sender_role`, `message`, `created_at`) VALUES
(1, 1, 'customer', 'try1', '2026-06-26 07:05:05'),
(2, 1, 'staff', '2', '2026-06-26 07:05:18'),
(3, 1, 'staff', 'wew', '2026-06-26 07:05:20'),
(4, 1, 'customer', 'asd', '2026-06-26 07:05:46'),
(5, 2, 'customer', '1', '2026-06-26 07:06:45'),
(6, 3, 'customer', '2', '2026-06-26 07:06:47'),
(7, 1, 'staff', '000', '2026-06-26 07:53:31');

-- --------------------------------------------------------

--
-- 表的结构 `tickets`
--

CREATE TABLE `tickets` (
  `tid` int(11) NOT NULL,
  `oid` int(11) NOT NULL,
  `cid` int(11) NOT NULL,
  `message` text NOT NULL,
  `reply` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `tickets`
--

INSERT INTO `tickets` (`tid`, `oid`, `cid`, `message`, `reply`, `status`, `created_at`) VALUES
(1, 10, 1, '', NULL, 'Replied', '2026-06-26 07:05:05'),
(2, 9, 1, '', NULL, 'Pending', '2026-06-26 07:06:45'),
(3, 7, 1, '', NULL, 'Pending', '2026-06-26 07:06:47');

-- --------------------------------------------------------

--
-- 表的结构 `wishlists`
--

CREATE TABLE `wishlists` (
  `wid` int(11) NOT NULL,
  `cid` int(11) NOT NULL,
  `fid` int(11) NOT NULL,
  `added_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- 表的索引 `ticketmessages`
--
ALTER TABLE `ticketmessages`
  ADD PRIMARY KEY (`tmid`);

--
-- 表的索引 `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`tid`);

--
-- 表的索引 `wishlists`
--
ALTER TABLE `wishlists`
  ADD PRIMARY KEY (`wid`),
  ADD UNIQUE KEY `unique_wishlist` (`cid`,`fid`);

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
  MODIFY `fid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- 使用表AUTO_INCREMENT `materials`
--
ALTER TABLE `materials`
  MODIFY `mid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- 使用表AUTO_INCREMENT `orders`
--
ALTER TABLE `orders`
  MODIFY `oid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- 使用表AUTO_INCREMENT `staffs`
--
ALTER TABLE `staffs`
  MODIFY `sid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `ticketmessages`
--
ALTER TABLE `ticketmessages`
  MODIFY `tmid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- 使用表AUTO_INCREMENT `tickets`
--
ALTER TABLE `tickets`
  MODIFY `tid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `wishlists`
--
ALTER TABLE `wishlists`
  MODIFY `wid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
