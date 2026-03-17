# Sepolia Setup (Safe Template)

Use this to configure free testnet mode without breaking the app.

## 1) Apache SetEnv (XAMPP)
File: C:\xampp\apache\conf\extra\httpd-vhosts.conf

Add these lines inside your active <VirtualHost ...> block:

SetEnv ETH_NETWORK sepolia
SetEnv ETH_NODE_URL https://eth-sepolia.g.alchemy.com/v2/REPLACE_WITH_NEW_KEY
SetEnv ETH_WALLET_ADDRESS 0xREPLACE_WITH_YOUR_WALLET
SetEnv ETH_PRIVATE_KEY REPLACE_WITH_PRIVATE_KEY_NO_0x
SetEnv ETH_CONTRACT_ADDRESS 0xREPLACE_WITH_CONTRACT_ADDRESS
SetEnv BLOCKCHAIN_ENABLED false

Then restart Apache in XAMPP.

## 2) Safe activation flow
- Keep BLOCKCHAIN_ENABLED=false first.
- Generate and verify a certificate (system should work normally).
- After you deploy a Sepolia contract, set ETH_CONTRACT_ADDRESS.
- Set BLOCKCHAIN_ENABLED=true.
- Restart Apache.

## 3) Security
- Never commit API keys/private keys to git.
- If a key was exposed, rotate it in Alchemy.

## 4) Optional DB columns (non-breaking)
Run only if you want richer status in certificate_hashes:

ALTER TABLE certificate_hashes
  ADD COLUMN data_hash CHAR(64) NULL,
  ADD COLUMN chain_status VARCHAR(20) NULL,
  ADD COLUMN tx_hash VARCHAR(100) NULL,
  ADD COLUMN chain_network VARCHAR(30) NULL,
  ADD COLUMN anchored_at DATETIME NULL;
