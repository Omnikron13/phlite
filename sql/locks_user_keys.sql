CREATE TABLE IF NOT EXISTS locks_user_keys(
    id     INTEGER PRIMARY KEY,
    lockID INTEGER NOT NULL,
    userID INTEGER NOT NULL,
    FOREIGN KEY (lockID) REFERENCES locks(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (userID) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS locks_user_keys_index ON locks_user_keys(lockID, userID);
