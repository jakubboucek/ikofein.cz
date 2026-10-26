ALTER TABLE `user` ENGINE = InnoDB;
ALTER TABLE `post` ENGINE = InnoDB;

UPDATE `user` SET `reset_hash` = NULL;
ALTER TABLE `user`
    MODIFY `password` VARCHAR(255) NOT NULL DEFAULT '',
    MODIFY `reset_hash` VARCHAR(43) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ADD `reset_selector` VARCHAR(22) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ADD `reset_expires_at` DATETIME NULL,
    ADD `reset_requested_at` DATETIME NULL,
    ADD `auth_version` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD UNIQUE KEY `reset_selector` (`reset_selector`);

CREATE TABLE `password_reset_limit` (
    `key` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    `window_start` BIGINT UNSIGNED NOT NULL,
    `attempts` INT UNSIGNED NOT NULL
) ENGINE = InnoDB;
