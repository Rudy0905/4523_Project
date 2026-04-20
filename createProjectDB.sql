-- "NO_AUTO_VALUE_ON_ZERO" suppress generate the next sequence number for AUTO_INCREMENT column
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+08:00";

-- Database: `ProjectDB`
DROP DATABASE IF EXISTS `ProjectDB`;
CREATE DATABASE IF NOT EXISTS `ProjectDB` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `ProjectDB`;

DROP TABLE IF EXISTS FurnitureMaterials;
DROP TABLE IF EXISTS OrderFurnitures;
DROP TABLE IF EXISTS Orders;
DROP TABLE IF EXISTS Staffs;
DROP TABLE IF EXISTS Furnitures;
DROP TABLE IF EXISTS Customers;
DROP TABLE IF EXISTS Materials;

CREATE TABLE Materials (
    mid INT NOT NULL AUTO_INCREMENT,
    mname VARCHAR(255) NOT NULL,
    mqty INT NOT NULL DEFAULT 0,
    munit VARCHAR(50) NOT NULL,
    PRIMARY KEY (mid)
) ENGINE=InnoDB;
INSERT INTO Materials (mname, mqty, munit) VALUES 
('Oak Wood Plank', 500, 'pcs'),      -- mid = 1
('Steel Tube', 200, 'meter'),        -- mid = 2
('Fabric Cloth', 100, 'meter'),      -- mid = 3
('High Density Foam', 50, 'block');  -- mid = 4

CREATE TABLE Customers (
    cid INT NOT NULL AUTO_INCREMENT,
    cname VARCHAR(255) NOT NULL,
    cpassword VARCHAR(255) NOT NULL,
    ctel VARCHAR(20) NOT NULL,
    caddr VARCHAR(255) NOT NULL,
    company VARCHAR(255),
    PRIMARY KEY (cid)
) ENGINE=InnoDB;


CREATE TABLE Furnitures (
    fid INT NOT NULL AUTO_INCREMENT,
    fname VARCHAR(255) NOT NULL,
    fdesc VARCHAR(255) NOT NULL,
    fprice DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (fid)
) ENGINE=InnoDB;
INSERT INTO Furnitures (fname, fdesc, fprice) VALUES 
('Oak Dining Chair', 'Classic style dining chair made of solid oak.', 450.00),     -- fid = 1
('Large Dining Table', '6-seater dining table, perfect for families.', 2500.00),   -- fid = 2
('3-Seater Fabric Sofa', 'Comfortable grey fabric sofa with foam filling.', 3800.00), -- fid = 3
('Wooden Wardrobe', 'Double door wardrobe with hanging space.', 1800.00),          -- fid = 4
('Industrial Bookshelf', 'Modern style bookshelf with steel frame.', 1200.00),     -- fid = 5
('Queen Size Bed Frame', 'Sturdy bed frame for queen size mattress.', 2200.00);    -- fid = 6


CREATE TABLE Staffs (
    sid INT NOT NULL AUTO_INCREMENT,
    spassword VARCHAR(255) NOT NULL,
    sname VARCHAR(255) NOT NULL,
    srole VARCHAR(50) NOT NULL,
    stel VARCHAR(20) NOT NULL,
    PRIMARY KEY (sid)
) ENGINE=InnoDB;
INSERT INTO Staffs (spassword, sname, srole, stel) VALUES 
('admin', 'Admin', 'Administrator', '12345678');

CREATE TABLE Orders (
    oid INT NOT NULL AUTO_INCREMENT,
    odate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ototalamount DECIMAL(10, 2) NOT NULL,
    cid INT NOT NULL,
    odeliverydate DATETIME NOT NULL,
    odeliveraddress TEXT NOT NULL,
    ostatus INT DEFAULT 1 NOT NULL,
    PRIMARY KEY (oid),
    FOREIGN KEY (cid) REFERENCES Customers(cid)
) ENGINE=InnoDB;


CREATE TABLE OrderFurnitures (
    oid INT NOT NULL,
    fid INT NOT NULL,
    oqty INT NOT NULL,
    PRIMARY KEY (fid, oid),
    FOREIGN KEY (fid) REFERENCES Furnitures(fid),
    FOREIGN KEY (oid) REFERENCES Orders(oid)
) ENGINE=InnoDB;

CREATE TABLE FurnitureMaterials (
    fid INT NOT NULL,
    mid INT NOT NULL,
    pmqty INT NOT NULL,
    PRIMARY KEY (fid, mid),
    FOREIGN KEY (fid) REFERENCES Furnitures(fid),
    FOREIGN KEY (mid) REFERENCES Materials(mid)
) ENGINE=InnoDB;
INSERT INTO FurnitureMaterials (fid, mid, pmqty) VALUES 
-- 1. Oak Dining Chair (Requires: 2 Wood Planks)
(1, 1, 2),
-- 2. Large Dining Table (Requires: 10 Wood Planks)
(2, 1, 10),
-- 3. 3-Seater Fabric Sofa (Requires: 5 Wood Planks, 10 Fabric, 3 Foam Blocks)
(3, 1, 5), (3, 3, 10), (3, 4, 3),
-- 4. Wooden Wardrobe (Requires: 15 Wood Planks)
(4, 1, 15),
-- 5. Industrial Bookshelf (Requires: 4 Wood Planks, 6 Steel Tubes)
(5, 1, 4), (5, 2, 6),
-- 6. Queen Size Bed Frame (Requires: 12 Wood Planks)
(6, 1, 12);

COMMIT;