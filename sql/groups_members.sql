CREATE TABLE IF NOT EXISTS groups_members(
    id      INTEGER PRIMARY KEY,
    groupID INTEGER NOT NULL,
    userID  INTEGER NOT NULL,
    FOREIGN KEY (groupID) REFERENCES groups(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (userID)  REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS groups_members_index ON groups_members(groupID, userID);
