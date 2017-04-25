CREATE TABLE IF NOT EXISTS locks_group_keys(
    id      INTEGER PRIMARY KEY,
    lockID  INTEGER NOT NULL,
    groupID INTEGER NOT NULL,
    FOREIGN KEY (lockID)  REFERENCES locks(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (groupID) REFERENCES groups(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS locks_group_keys_index ON locks_group_keys(lockID, groupID);
