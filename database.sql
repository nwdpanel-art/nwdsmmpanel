CREATE DATABASE IF NOT EXISTS smm_panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smm_panel;
CREATE TABLE users(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL,email VARCHAR(190) UNIQUE NOT NULL,password VARCHAR(255) NOT NULL,balance DECIMAL(10,2) DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE services(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(150) NOT NULL,category VARCHAR(80) NOT NULL,description TEXT,price DECIMAL(10,2) NOT NULL,status TINYINT DEFAULT 1);
CREATE TABLE orders(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,service_id INT NOT NULL,link VARCHAR(500) NOT NULL,quantity INT NOT NULL,amount DECIMAL(10,2) NOT NULL,status VARCHAR(30) DEFAULT 'Pending',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id),FOREIGN KEY(service_id) REFERENCES services(id));
CREATE TABLE transactions(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,amount DECIMAL(10,2) NOT NULL,type VARCHAR(30) NOT NULL,note VARCHAR(255),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id));
CREATE TABLE tickets(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,subject VARCHAR(200),message TEXT,status VARCHAR(30) DEFAULT 'Open',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id));
CREATE TABLE admins(id INT AUTO_INCREMENT PRIMARY KEY,email VARCHAR(190) UNIQUE NOT NULL,password VARCHAR(255) NOT NULL);
INSERT INTO services(name,category,description,price) VALUES
('Social Media Content Strategy','Strategy','Content planning and monthly social strategy.',15.00),
('Instagram Content Management','Management','Legitimate content scheduling and profile management.',25.00),
('TikTok Content Planning','Management','Content calendar, ideas and publishing workflow.',20.00),
('Social Media Analytics Report','Analytics','Performance report with actionable insights.',10.00),
('YouTube Channel Optimization','Optimization','Titles, descriptions, metadata and channel recommendations.',30.00);
-- Demo admin password is: admin123
INSERT INTO admins(email,password) VALUES('admin@example.com','$2y$10$0Y2Qf8mJk5u6Kq9yG7bG0eJg3v0p5L3jXQ6G8Q1w5R9F2A0mY4L6e');