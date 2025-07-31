CREATE DATABASE CUBE;
USE CUBE;

CREATE TABLE Customer (
    Cust_ID INT AUTO_INCREMENT PRIMARY KEY,
    Cust_First_Name VARCHAR(255) NOT NULL,
    Cust_Last_Name VARCHAR(50) NOT NULL,
    Cust_Email VARCHAR(255) NOT NULL UNIQUE,
    Cust_Password VARCHAR(255) NOT NULL,
    Cust_Phone VARCHAR(15) NULL,
    Profile_Image LONGTEXT NULL,
    Reset_Token VARCHAR(6) NULL,
    Token_Expiry DATETIME NULL,
    Created_At DATETIME DEFAULT CURRENT_TIMESTAMP,
    Updated_At DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    Last_Login DATETIME NULL,
    Last_Password_Change DATETIME NULL,
    Last_Profile_Update DATETIME NULL,
    Cust_Status BOOLEAN DEFAULT 1
);

DELIMITER //
CREATE TRIGGER trg_invalidate_token
BEFORE UPDATE ON Customer
FOR EACH ROW
BEGIN
    IF NEW.Token_Expiry IS NOT NULL AND NEW.Token_Expiry < NOW() THEN
        SET NEW.Reset_Token = NULL;
        SET NEW.Token_Expiry = NULL;
    END IF;
END//
DELIMITER ;

CREATE TABLE Address (
    Add_ID INT AUTO_INCREMENT PRIMARY KEY,
    Cust_ID INT NOT NULL,
    Add_Line VARCHAR(255) NOT NULL,
    City VARCHAR(50) NOT NULL,
    State VARCHAR(50) NOT NULL,
    Postcode VARCHAR(5) NOT NULL,
    Add_Default BOOLEAN DEFAULT FALSE,
    Add_Status BOOLEAN DEFAULT 1,
    FOREIGN KEY (Cust_ID) REFERENCES Customer(Cust_ID)
);

CREATE TABLE Staff (
    Staff_ID INT AUTO_INCREMENT PRIMARY KEY,
    Staff_Name VARCHAR(255) NOT NULL,
    Staff_Email VARCHAR(255) NOT NULL UNIQUE,
    Staff_Role VARCHAR(50) NOT NULL,
    Staff_Password VARCHAR(255) NOT NULL,
    Join_Date DATE NOT NULL,
    Profile_Image LONGTEXT NULL,
    Reset_Token VARCHAR(255) NULL,
    Token_Expiry DATETIME NULL,
    Staff_Status BOOLEAN DEFAULT 1
);

CREATE TABLE Product (
    Prod_ID INT AUTO_INCREMENT PRIMARY KEY,
    Prod_Name VARCHAR(255) NOT NULL,
    Prod_Price DECIMAL(10,2) NOT NULL,
    Prod_Describe VARCHAR(255) NOT NULL,
    Prod_Status BOOLEAN DEFAULT 1
);

CREATE TABLE Color (
    Color_ID INT AUTO_INCREMENT PRIMARY KEY,
    Color_Name VARCHAR(50) NOT NULL,
    Color_Status BOOLEAN DEFAULT 1
);

CREATE TABLE Product_Color (
    Prod_ID INT NOT NULL,
    Color_ID INT NOT NULL,
    Stock INT NOT NULL DEFAULT 0 CHECK (Stock >= 0),
    PRIMARY KEY (Prod_ID, Color_ID),
    FOREIGN KEY (Prod_ID) REFERENCES Product(Prod_ID),
    FOREIGN KEY (Color_ID) REFERENCES Color(Color_ID)
);

CREATE TABLE Category (
    Cate_ID INT AUTO_INCREMENT PRIMARY KEY,
    Cate_Name VARCHAR(50) NOT NULL,
    Cate_Group VARCHAR(50) NOT NULL,
    Cate_Image VARCHAR(255) NOT NULL,
    Cate_Status BOOLEAN DEFAULT 1
);

CREATE TABLE Product_Category (
    Prod_ID INT NOT NULL,
    Cate_ID INT NOT NULL,
    PRIMARY KEY (Prod_ID, Cate_ID),
    FOREIGN KEY (Prod_ID) REFERENCES Product(Prod_ID),
    FOREIGN KEY (Cate_ID) REFERENCES Category(Cate_ID)
);

CREATE TABLE Image (
    Image_ID INT AUTO_INCREMENT PRIMARY KEY,
    Image VARCHAR(255) NOT NULL,
    Prod_ID INT NOT NULL,
    Image_Order INT DEFAULT 0,
    FOREIGN KEY (Prod_ID) REFERENCES Product(Prod_ID)
);

CREATE TABLE Credit_Card (
    Card_ID INT AUTO_INCREMENT PRIMARY KEY,
    Card_Number VARCHAR(16) NOT NULL,
    Expiry_Date VARCHAR(7) NOT NULL,
    Card_CVV VARCHAR(3) NOT NULL,
    Card_Holder_Name VARCHAR(100) NOT NULL
);

CREATE TABLE Payment (
    Pay_ID INT AUTO_INCREMENT PRIMARY KEY,
    Cust_ID INT NOT NULL,
    Amount DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (Cust_ID) REFERENCES Customer(Cust_ID)
);

CREATE TABLE Orders (
    Order_ID INT AUTO_INCREMENT PRIMARY KEY,
    Order_Date DATETIME,
    Amount DECIMAL(10,2) NOT NULL,
    Status ENUM('Pending', 'Completed','Cancelled', 'Failed') DEFAULT 'Pending',
    Add_ID INT NOT NULL,
    Cust_ID INT NOT NULL,
    Pay_ID INT NOT NULL,
    Staff_ID INT,
    Order_Status TINYINT(1) DEFAULT 1,
    FOREIGN KEY (Add_ID) REFERENCES Address(Add_ID),
    FOREIGN KEY (Cust_ID) REFERENCES Customer(Cust_ID),
    FOREIGN KEY (Pay_ID) REFERENCES Payment(Pay_ID),
    FOREIGN KEY (Staff_ID) REFERENCES Staff(Staff_ID)    
);

CREATE TABLE Order_Item (
    Order_Item_ID INT AUTO_INCREMENT PRIMARY KEY,
    Order_ID INT NOT NULL,
    Color_ID INT NOT NULL,
    Prod_ID INT NOT NULL,
    Quantity INT DEFAULT 1 CHECK (Quantity > 0),
    FOREIGN KEY (Order_ID) REFERENCES Orders(Order_ID),
    FOREIGN KEY (Color_ID) REFERENCES Color(Color_ID),
    FOREIGN KEY (Prod_ID) REFERENCES Product(Prod_ID)
);

CREATE TABLE Cart (
    Cart_ID INT AUTO_INCREMENT PRIMARY KEY,
    Cust_ID INT NOT NULL,
    Prod_ID INT NOT NULL,
    Color_ID INT NOT NULL,  
    Quantity INT DEFAULT 1 CHECK (Quantity > 0),
    FOREIGN KEY (Cust_ID) REFERENCES Customer(Cust_ID),
    FOREIGN KEY (Prod_ID) REFERENCES Product(Prod_ID),
    FOREIGN KEY (Color_ID) REFERENCES Color(Color_ID)
);

CREATE TABLE Rate (
    Rate_ID INT AUTO_INCREMENT PRIMARY KEY,
    Rating INT NOT NULL CHECK(Rating BETWEEN 1 AND 5),
    Rate_Text TEXT,
    Order_ID INT NOT NULL,
    Prod_ID INT NOT NULL,
    Color_ID INT NOT NULL,
    Cust_ID INT NOT NULL,
    FOREIGN KEY (Order_ID) REFERENCES Orders(Order_ID),
    FOREIGN KEY (Prod_ID) REFERENCES Product(Prod_ID),
    FOREIGN KEY (Color_ID) REFERENCES Color(Color_ID),
    FOREIGN KEY (Cust_ID) REFERENCES Customer(Cust_ID)    
);
