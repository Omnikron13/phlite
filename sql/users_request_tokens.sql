CREATE TABLE IF NOT EXISTS users_request_tokens(
    id     INTEGER PRIMARY KEY,
    userID INTEGER NOT NULL,
    token  TEXT    NOT NULL,
    time   INTEGER NOT NULL,
    FOREIGN KEY (userID) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
