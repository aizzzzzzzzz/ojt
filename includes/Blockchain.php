<?php
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
} else {
    error_log("Blockchain autoload not found: " . $autoloadPath);
}

class Blockchain {
    private $chain = [];
    private $pendingCertificates = [];
    private $ethEndpoint;
    private $walletAddress;
    private $privateKey;
    private $contractAddress;
    private $useEthereum;
    private $network;
    
    const GENESIS_PREV_HASH = '0000000000000000000000000000000000000000000000000000000000000000';
    
    public function __construct() {
        $this->ethEndpoint = getenv('ETH_NODE_URL') ?: 'https://sepolia.infura.io/v3/YOUR_PROJECT_ID';
        $this->walletAddress = getenv('ETH_WALLET_ADDRESS') ?: '';
        $this->privateKey = getenv('ETH_PRIVATE_KEY') ?: '';
        $this->contractAddress = getenv('ETH_CONTRACT_ADDRESS') ?: '';
        $this->network = getenv('ETH_NETWORK') ?: 'sepolia';

        $legacyAutoEnable = !empty($this->walletAddress) && !empty($this->privateKey);
        $this->useEthereum = $this->readEnvBool('BLOCKCHAIN_ENABLED', $legacyAutoEnable);
        
        if (!$this->useEthereum && empty($this->chain)) {
            $this->initializeChain();
        }
    }

    private function readEnvBool($key, $default = false) {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    public static function buildCertificateHash($studentId, $certificateNo, $studentName, $employerName, $hoursCompleted) {
        $canonicalPayload = [
            'student_id' => (string)$studentId,
            'certificate_no' => strtoupper(trim((string)$certificateNo)),
            'student_name' => trim((string)$studentName),
            'employer_name' => trim((string)$employerName),
            'hours_completed' => number_format((float)$hoursCompleted, 2, '.', '')
        ];

        return hash('sha256', json_encode($canonicalPayload, JSON_UNESCAPED_SLASHES));
    }
    
    private function initializeChain() {
        $genesisBlock = [
            'index' => 0,
            'timestamp' => time(),
            'certificate_hash' => '',
            'previous_hash' => self::GENESIS_PREV_HASH,
            'nonce' => 0,
            'data' => 'Genesis Block - OJT Certificate Blockchain'
        ];
        
        $genesisBlock['hash'] = $this->calculateHash(
            $genesisBlock['index'],
            $genesisBlock['timestamp'],
            $genesisBlock['certificate_hash'],
            $genesisBlock['previous_hash'],
            $genesisBlock['nonce']
        );
        
        $this->chain[] = $genesisBlock;
    }
    
    public function calculateHash($index, $timestamp, $certificateHash, $previousHash, $nonce) {
        return hash('sha256', $index . $timestamp . $certificateHash . $previousHash . $nonce);
    }
    
    public function getLastBlock() {
        return end($this->chain);
    }

    public function addCertificate($studentId, $certificateNo, $studentName, $employerName, $hoursCompleted) {
        $certificateData = [
            'student_id' => $studentId,
            'certificate_no' => $certificateNo,
            'student_name' => $studentName,
            'employer_name' => $employerName,
            'hours_completed' => $hoursCompleted
        ];
        
        $certificateHash = self::buildCertificateHash(
            $studentId,
            $certificateNo,
            $studentName,
            $employerName,
            $hoursCompleted
        );
        
        if ($this->useEthereum) {
            return $this->addCertificateToEthereum($certificateNo, $certificateHash, $certificateData);
        }
        
        $lastBlock = $this->getLastBlock();
        
        $newBlock = [
            'index' => count($this->chain),
            'timestamp' => time(),
            'certificate_hash' => $certificateHash,
            'previous_hash' => $lastBlock['hash'],
            'nonce' => 0,
            'data' => $certificateData,
            'anchored_at' => time()
        ];
        
        $nonce = 0;
        while (true) {
            $hash = $this->calculateHash(
                $newBlock['index'],
                $newBlock['timestamp'],
                $newBlock['certificate_hash'],
                $newBlock['previous_hash'],
                $nonce
            );
            
            if (substr($hash, 0, 4) === '0000') {
                $newBlock['nonce'] = $nonce;
                $newBlock['hash'] = $hash;
                break;
            }
            $nonce++;
        }
        
        $this->chain[] = $newBlock;
        
        return [
            'success' => true,
            'block' => $newBlock,
            'mode' => 'local_blockchain',
            'certificate_hash' => $certificateHash,
            'chain_status' => 'confirmed'
        ];
    }
    
    private function addCertificateToEthereum($certificateNo, $certificateHash, $data) {
        try {
            if (empty($this->ethEndpoint) || empty($this->contractAddress)) {
                return [
                    'success' => true,
                    'certificate_hash' => $certificateHash,
                    'mode' => 'ethereum_testnet',
                    'chain_status' => 'pending',
                    'network' => $this->network,
                    'note' => 'Ethereum is enabled but endpoint/contract is not configured.'
                ];
            }

            if (empty($this->walletAddress) || empty($this->privateKey)) {
                return [
                    'success' => true,
                    'certificate_hash' => $certificateHash,
                    'mode' => 'ethereum_testnet',
                    'chain_status' => 'pending',
                    'network' => $this->network,
                    'note' => 'Read-only mode: wallet/private key not configured.'
                ];
            }

            if (!class_exists('\Web3\Web3')) {
                return [
                    'success' => true,
                    'certificate_hash' => $certificateHash,
                    'mode' => 'ethereum_testnet',
                    'chain_status' => 'pending',
                    'network' => $this->network,
                    'note' => 'web3 library not available. Certificate saved in DB only.'
                ];
            }

            $web3 = new \Web3\Web3($this->ethEndpoint);
             
            // For actual implementation, you would:
            // 1. Create a transaction to store the certificate hash
            // 2. Sign it with the private key
            // 3. Send it to the network

            return [
                'success' => true,
                'tx_hash' => null,
                'certificate_no' => $certificateNo,
                'certificate_hash' => $certificateHash,
                'mode' => 'ethereum_testnet',
                'chain_status' => 'pending',
                'network' => $this->network,
                'note' => 'Ethereum write queued but contract interaction is not implemented yet.'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'mode' => 'ethereum_error',
                'chain_status' => 'failed',
                'network' => $this->network
            ];
        }
    }
    
    public function verifyCertificate($certificateNo) {
        if ($this->useEthereum) {
            return $this->verifyCertificateOnEthereum($certificateNo);
        }
        
        foreach ($this->chain as $block) {
            if (isset($block['data']['certificate_no']) && 
                $block['data']['certificate_no'] === $certificateNo) {
                return [
                    'verified' => true,
                    'block' => $block,
                    'mode' => 'local_blockchain'
                ];
            }
        }
        
        return [
            'verified' => false,
            'message' => 'Certificate not found on local blockchain',
            'mode' => 'local_blockchain',
            'chain_status' => 'not_found'
        ];
    }
    
    private function verifyCertificateOnEthereum($certificateNo) {
        try {
            if (empty($this->ethEndpoint) || empty($this->contractAddress)) {
                return [
                    'verified' => false,
                    'mode' => 'ethereum_testnet',
                    'chain_status' => 'disabled',
                    'network' => $this->network,
                    'note' => 'Ethereum endpoint or contract address is not configured.'
                ];
            }

            if (!class_exists('\Web3\Web3')) {
                return [
                    'verified' => false,
                    'mode' => 'ethereum_testnet',
                    'chain_status' => 'pending',
                    'network' => $this->network,
                    'note' => 'web3 library is not available.'
                ];
            }

            return [
                'verified' => false,
                'mode' => 'ethereum_testnet',
                'chain_status' => 'pending',
                'network' => $this->network,
                'certificate_no' => $certificateNo,
                'note' => 'Verification endpoint is ready, but contract read is not implemented yet.'
            ];
        } catch (\Exception $e) {
            return [
                'verified' => false,
                'error' => $e->getMessage(),
                'chain_status' => 'failed',
                'network' => $this->network
            ];
        }
    }
    
    public function getChain() {
        return $this->chain;
    }
    
    public function isChainValid() {
        for ($i = 1; $i < count($this->chain); $i++) {
            $currentBlock = $this->chain[$i];
            $previousBlock = $this->chain[$i - 1];
            
            $calculatedHash = $this->calculateHash(
                $currentBlock['index'],
                $currentBlock['timestamp'],
                $currentBlock['certificate_hash'],
                $currentBlock['previous_hash'],
                $currentBlock['nonce']
            );
            
            if ($currentBlock['hash'] !== $calculatedHash) {
                return false;
            }
            
            if ($currentBlock['previous_hash'] !== $previousBlock['hash']) {
                return false;
            }
        }
        
        return true;
    }
    
    public function getBlockchainInfo() {
        return [
            'mode' => $this->useEthereum ? 'Ethereum Testnet' : 'Local Blockchain',
            'total_blocks' => count($this->chain),
            'is_valid' => $this->isChainValid(),
            'latest_block_hash' => $this->getLastBlock()['hash'] ?? null,
            'difficulty' => 4,
            'consensus' => $this->useEthereum ? 'Proof of Authority' : 'Proof of Work',
            'network' => $this->network,
            'ethereum_enabled' => $this->useEthereum
        ];
    }
}
?>
