UPDATE `user`
SET `reset_selector` = NULL, `reset_hash` = NULL,
    `reset_expires_at` = NULL, `reset_requested_at` = NULL;

ALTER TABLE `user`
    MODIFY `reset_selector` VARCHAR(22) CHARACTER SET ascii COLLATE ascii_bin NULL,
    MODIFY `reset_hash` VARCHAR(43) CHARACTER SET ascii COLLATE ascii_bin NULL;
