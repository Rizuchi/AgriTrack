-- Run once on an existing AgriTrack database after contact_messages_migration.sql.
-- Resolve any rows returned by these checks before applying the constraints:
--
-- SELECT c.ContactID, c.UserID FROM contact_messages c
-- LEFT JOIN users u ON u.UserID = c.UserID
-- WHERE c.UserID IS NOT NULL AND u.UserID IS NULL;
--
-- SELECT r.ReplyID, r.ContactID, r.AdminUserID FROM contact_message_replies r
-- LEFT JOIN contact_messages c ON c.ContactID = r.ContactID
-- LEFT JOIN users u ON u.UserID = r.AdminUserID
-- WHERE c.ContactID IS NULL OR u.UserID IS NULL;
--
-- SELECT s.ContactID FROM contact_message_status s
-- LEFT JOIN contact_messages c ON c.ContactID = s.ContactID
-- WHERE c.ContactID IS NULL;
--
-- Contact messages may be anonymous; deleting a user preserves the message.
-- Deleting a message removes its replies and read-status row. An admin account
-- with authored replies cannot be deleted until those replies are dealt with.

ALTER TABLE `contact_messages`
  ADD KEY `fk_contact_messages_user` (`UserID`),
  ADD CONSTRAINT `fk_contact_messages_user`
    FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`)
    ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `contact_message_replies`
  ADD KEY `fk_contact_replies_admin` (`AdminUserID`),
  ADD CONSTRAINT `fk_contact_replies_contact`
    FOREIGN KEY (`ContactID`) REFERENCES `contact_messages` (`ContactID`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contact_replies_admin`
    FOREIGN KEY (`AdminUserID`) REFERENCES `users` (`UserID`)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `contact_message_status`
  ADD CONSTRAINT `fk_contact_status_contact`
    FOREIGN KEY (`ContactID`) REFERENCES `contact_messages` (`ContactID`)
    ON DELETE CASCADE ON UPDATE CASCADE;
