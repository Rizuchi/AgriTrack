CREATE TABLE IF NOT EXISTS `contact_messages` (
  `ContactID` int(11) NOT NULL AUTO_INCREMENT,
  `UserID` int(11) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(254) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ContactID`),
  KEY `created_at` (`created_at`),
  KEY `fk_contact_messages_user` (`UserID`),
  CONSTRAINT `fk_contact_messages_user` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `contact_message_replies` (
  `ReplyID` int(11) NOT NULL AUTO_INCREMENT,
  `ContactID` int(11) NOT NULL,
  `AdminUserID` int(11) NOT NULL,
  `reply_text` text NOT NULL,
  `email_sent` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ReplyID`),
  KEY `contact_created_at` (`ContactID`, `created_at`, `ReplyID`),
  KEY `fk_contact_replies_admin` (`AdminUserID`),
  CONSTRAINT `fk_contact_replies_contact` FOREIGN KEY (`ContactID`) REFERENCES `contact_messages` (`ContactID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_contact_replies_admin` FOREIGN KEY (`AdminUserID`) REFERENCES `users` (`UserID`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `contact_message_status` (
  `ContactID` int(11) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ContactID`),
  CONSTRAINT `fk_contact_status_contact` FOREIGN KEY (`ContactID`) REFERENCES `contact_messages` (`ContactID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;