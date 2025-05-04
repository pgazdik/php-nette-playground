CREATE TABLE book (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    available BOOLEAN DEFAULT TRUE
);

INSERT INTO book (name, available) VALUES ('The Lord of the Rings', TRUE);
