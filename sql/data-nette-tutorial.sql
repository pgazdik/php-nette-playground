-- create posts table

CREATE TABLE `posts` (
	`id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`title` varchar(255) NOT NULL,
	`content` text NOT NULL,
	`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8;


-- insert posts

INSERT INTO `posts` (`id`, `title`, `content`, `created_at`) VALUES
(1,	'Article One',	'Lorem ipusm dolor one',	CURRENT_TIMESTAMP),
(2,	'Article Two',	'Lorem ipsum dolor two',	CURRENT_TIMESTAMP),
(3,	'Article Three',	'Lorem ipsum dolor three',	CURRENT_TIMESTAMP);

-- create comments

CREATE TABLE `comments` (
	`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`post_id` int NOT NULL,
	`name` varchar(250) NOT NULL,
	`email` varchar(250) NOT NULL,
	`content` text NOT NULL,
	`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`)
) ENGINE=InnoDB CHARSET=utf8;

