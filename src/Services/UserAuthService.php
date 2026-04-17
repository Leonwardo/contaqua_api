<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\MongoCollections;
use App\Models\UserAuth;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;

class UserAuthService
{
    public function __construct(
        private MongoCollections $collections,
        private LoggerInterface $logger,
        private bool $allowLegacyPlainPasswords = false
    ) {
    }
    
    /**
     * Validate a user token and return the user document
     */
    public function validateToken(string $token): ?UserAuth
    {
        $token = strtoupper(trim($token));
        if ($token === '') {
            return null;
        }
        
        try {
            $cursor = $this->collections->userAuth()->find([]);
            
            foreach ($cursor as $document) {
                $user = UserAuth::fromArray($document);
                
                if ($user->access <= 0 || $user->user_id <= 0) {
                    continue;
                }
                
                if ($user->buildToken() === $token) {
                    $this->logger->debug('Token validated', ['user' => $user->user]);
                    return $user;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error validating token', ['error' => $e->getMessage()]);
        }
        
        $this->logger->warning('Token validation failed', ['token_prefix' => substr($token, 0, 6)]);
        return null;
    }
    
    /**
     * Authenticate user and return token
     */
    public function login(string $username, string $password): ?string
    {
        $this->logger->info('Login attempt', ['user' => $username]);
        
        try {
            $document = $this->collections->userAuth()->findOne([
                'user' => $username,
            ]);
            
            if ($document === null) {
                $this->logger->warning('Login failed: user not found', ['user' => $username]);
                return null;
            }
            
            $user = UserAuth::fromArray($document);
            
            if (!$user->verifyPassword($password, $this->allowLegacyPlainPasswords)) {
                $this->logger->warning('Login failed: invalid password', ['user' => $username]);
                return null;
            }
            
            if ($user->access <= 0 || $user->user_id <= 0) {
                $this->logger->warning('Login failed: malformed user', ['user' => $username]);
                return null;
            }
            
            $token = $user->buildToken();
            $this->logger->info('Login successful', ['user' => $username]);
            
            return $token;
        } catch (\Exception $e) {
            $this->logger->error('Login error', ['user' => $username, 'error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Find user by username
     */
    public function findByUsername(string $username): ?UserAuth
    {
        try {
            $document = $this->collections->userAuth()->findOne(['user' => $username]);
            return $document ? UserAuth::fromArray($document) : null;
        } catch (\Exception $e) {
            $this->logger->error('Error finding user', ['user' => $username, 'error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Get all users
     * @return UserAuth[]
     */
    public function getAllUsers(?int $limit = null): array
    {
        try {
            $options = ['sort' => ['user' => 1]];
            if ($limit !== null) {
                $options['limit'] = $limit;
            }
            
            $cursor = $this->collections->userAuth()->find([], $options);
            $users = [];
            
            foreach ($cursor as $document) {
                $users[] = UserAuth::fromArray($document);
            }
            
            return $users;
        } catch (\Exception $e) {
            $this->logger->error('Error getting users', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Create a new user
     */
    public function createUser(string $username, string $password, string $role = 'TECHNICIAN', ?int $userId = null): UserAuth
    {
        // Check if user exists
        if ($this->findByUsername($username) !== null) {
            throw new \InvalidArgumentException("User '{$username}' already exists");
        }
        
        $user = new UserAuth();
        $user->user = $username;
        $user->setRole($role);
        $user->setPassword($password);
        
        // Generate user_id if not provided
        if ($userId === null) {
            $lastUser = $this->collections->userAuth()->findOne([], ['sort' => ['user_id' => -1]]);
            $user->user_id = $lastUser ? ((int) $lastUser['user_id'] + 1) : 1;
        } else {
            $user->user_id = $userId;
        }
        
        $user->created_at = new \DateTimeImmutable();
        
        $result = $this->collections->userAuth()->insertOne($user->toArray());
        $user->_id = $result->getInsertedId();
        
        $this->logger->info('User created', ['user' => $username, 'role' => $role]);
        
        return $user;
    }
    
    /**
     * Update user
     */
    public function updateUser(string $username, ?string $role = null, ?string $password = null): void
    {
        $user = $this->findByUsername($username);
        if ($user === null) {
            throw new \InvalidArgumentException("User '{$username}' not found");
        }
        
        $update = ['updated_at' => new \DateTimeImmutable()];
        
        if ($role !== null) {
            $user->setRole($role);
            $update['access'] = $user->access;
        }
        
        if ($password !== null && $password !== '') {
            $user->setPassword($password);
            $update['pass'] = $user->pass;
            $update['salt'] = $user->salt;
        }
        
        $this->collections->userAuth()->updateOne(
            ['_id' => $user->_id],
            ['$set' => $update]
        );
        
        $this->logger->info('User updated', ['user' => $username]);
    }
    
    /**
     * Delete user
     */
    public function deleteUser(string $username): void
    {
        $user = $this->findByUsername($username);
        if ($user === null) {
            throw new \InvalidArgumentException("User '{$username}' not found");
        }
        
        $this->collections->userAuth()->deleteOne(['_id' => $user->_id]);
        $this->logger->info('User deleted', ['user' => $username]);
    }
    
    /**
     * Count total users
     */
    public function count(): int
    {
        return $this->collections->userAuth()->countDocuments();
    }
}
