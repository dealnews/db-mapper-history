CREATE TABLE revision_history (
    revision_id INTEGER PRIMARY KEY AUTOINCREMENT,
    object_id INTEGER NOT NULL,
    object_type TEXT NOT NULL,
    modified DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user TEXT NOT NULL DEFAULT '',
    object TEXT,
    status TEXT NOT NULL DEFAULT 'update' CHECK (status IN ('update', 'delete', 'create'))
);

CREATE INDEX idx_revision_history_object_modified ON revision_history (object_type, object_id, modified);
CREATE INDEX idx_revision_history_modified_type ON revision_history (object_type, modified);

-- SQLite lacks table partitioning; drop old data manually if needed.
