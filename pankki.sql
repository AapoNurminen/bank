-- Creating the 'users' table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,  -- Unique ID for each user
    username VARCHAR(255) NOT NULL,     -- Username of the user
    password VARCHAR(255) NOT NULL,     -- User's password
    is_admin BOOLEAN DEFAULT FALSE      -- Flag to indicate if the user is an admin
);

-- Creating the 'accounts' table
CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,  -- Unique ID for each account
    user_id INT NOT NULL,               -- ID of the user who owns this account
    balance DECIMAL(15, 2) DEFAULT 0.00, -- Account balance (decimals are supported)
    iban VARCHAR(34) UNIQUE NOT NULL,   -- International Bank Account Number (IBAN), unique for each account
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE -- Foreign key to the 'users' table
);

-- Creating the 'transactions' table
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,  -- Unique ID for each transaction
    iban VARCHAR(34) NOT NULL,          -- IBAN of the sender (linked to accounts)
    user_id INT NOT NULL,               -- ID of the user sending the money
    to_iban VARCHAR(34) NOT NULL,       -- IBAN of the recipient
    to_user_id INT NOT NULL,            -- ID of the user receiving the money
    amount DECIMAL(15, 2) NOT NULL,     -- Amount of money being transferred
    info TEXT,                          -- Information about the transaction (optional)
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Date and time of transaction
    FOREIGN KEY (iban) REFERENCES accounts(iban) ON DELETE CASCADE,  -- Foreign key to sender's account (IBAN)
    FOREIGN KEY (to_iban) REFERENCES accounts(iban) ON DELETE CASCADE, -- Foreign key to recipient's account (IBAN)
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,    -- Foreign key to sender's user (ID)
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE   -- Foreign key to recipient's user (ID)
);

