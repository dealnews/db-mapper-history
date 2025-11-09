CREATE TABLE `revision_history` (
    `revision_id` int unsigned NOT NULL AUTO_INCREMENT,
    `object_id` bigint unsigned NOT NULL,
    `object_type` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `user` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `object` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `status` enum('update', 'delete', 'create') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'update',
    -- By having modified in the PK, the table can be partitioned by date
    -- allowing older rows to be dropped by dropping old partitions
    PRIMARY KEY (`revision_id`,`modified`),
    KEY `object_modified` (`object_type`,`object_id`,`modified`),
    KEY `modified_type` (`object_type`,`modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Partitioning example. See https://dev.mysql.com/doc/refman/8.4/en/partitioning-range.html
-- for detailed instructions on partitioning tables and how to drop old ones.
-- This example partitions the data by month with a catch-all at the end
-- with a far future date.
-- PARTITION BY RANGE  COLUMNS(modified)
-- (
--     PARTITION p202411 VALUES LESS THAN ('2024-12-01') ENGINE = InnoDB,
--     PARTITION p202412 VALUES LESS THAN ('2025-01-01') ENGINE = InnoDB,
--     PARTITION p202501 VALUES LESS THAN ('2025-02-01') ENGINE = InnoDB,
--     PARTITION p202502 VALUES LESS THAN ('2025-03-01') ENGINE = InnoDB,
--     PARTITION p202503 VALUES LESS THAN ('2025-04-01') ENGINE = InnoDB,
--     PARTITION p202504 VALUES LESS THAN ('2025-05-01') ENGINE = InnoDB,
--     PARTITION p202505 VALUES LESS THAN ('2025-06-01') ENGINE = InnoDB,
--     PARTITION p999999 VALUES LESS THAN ('2199-12-31') ENGINE = InnoDB
-- )
