<?php

declare(strict_types=1);

/**
 * THD Digital Publishing
 *
 * User XML Repository.
 *
 * Responsibilities:
 * - Read users.xml
 * - Find users
 * - Create/update/delete users
 * - Hash passwords before persistence
 * - Verify passwords
 * - Persist XML safely with file locking
 *
 * This repository must NOT:
 * - Start sessions
 * - Authenticate HTTP requests
 * - Authorize admin actions
 * - Handle CSRF
 * - Render HTML
 */


/*
|--------------------------------------------------------------------------
| XML structure
|--------------------------------------------------------------------------
|
| <users>
|     <user>
|         <id>...</id>
|         <username>...</username>
|         <password_hash>...</password_hash>
|         <display_name>...</display_name>
|         <email>...</email>
|         <role>admin</role>
|         <status>active</status>
|         <created_at>...</created_at>
|         <updated_at>...</updated_at>
|         <last_login_at>...</last_login_at>
|     </user>
| </users>
|
*/


class UserRepository
{
    /**
     * XML file path.
     */
    private string $xmlPath;


    /**
     * Create repository.
     *
     * @param string|null $xmlPath Optional path for testing.
     */
    public function __construct(
        ?string $xmlPath = null
    ) {
        $this->xmlPath = $xmlPath ?? USERS_XML;
    }


    /*
    |--------------------------------------------------------------------------
    | Read operations
    |--------------------------------------------------------------------------
    */

    /**
     * Return all users.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $xml = $this->loadXml();

        $users = [];

        foreach ($xml->user as $node) {
            $users[] = $this->nodeToArray($node);
        }

        return $users;
    }


    /**
     * Find a user by ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(
        string $id
    ): ?array {
        $id = trim($id);

        if ($id === '') {
            return null;
        }

        $xml = $this->loadXml();

        foreach ($xml->user as $node) {
            if (
                (string) $node->id === $id
            ) {
                return $this->nodeToArray($node);
            }
        }

        return null;
    }


    /**
     * Find a user by username.
     *
     * Username comparison is case-insensitive.
     *
     * @return array<string, mixed>|null
     */
    public function findByUsername(
        string $username
    ): ?array {
        $username = trim($username);

        if ($username === '') {
            return null;
        }

        $username = mb_strtolower(
            $username,
            'UTF-8'
        );

        $xml = $this->loadXml();

        foreach ($xml->user as $node) {
            $nodeUsername = mb_strtolower(
                (string) $node->username,
                'UTF-8'
            );

            if (
                $nodeUsername === $username
            ) {
                return $this->nodeToArray($node);
            }
        }

        return null;
    }


    /**
     * Check whether a username exists.
     */
    public function existsByUsername(
        string $username,
        ?string $excludeId = null
    ): bool {
        $username = trim($username);

        if ($username === '') {
            return false;
        }

        $normalizedUsername = mb_strtolower(
            $username,
            'UTF-8'
        );

        $xml = $this->loadXml();

        foreach ($xml->user as $node) {
            $nodeId = (string) $node->id;

            if (
                $excludeId !== null
                && $nodeId === $excludeId
            ) {
                continue;
            }

            $nodeUsername = mb_strtolower(
                (string) $node->username,
                'UTF-8'
            );

            if (
                $nodeUsername === $normalizedUsername
            ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Count users.
     */
    public function count(
        ?string $status = null
    ): int {
        $xml = $this->loadXml();

        $count = 0;

        foreach ($xml->user as $node) {
            if (
                $status !== null
                && (string) $node->status !== $status
            ) {
                continue;
            }

            $count++;
        }

        return $count;
    }


    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new user.
     *
     * Password input is expected in plaintext and is immediately
     * converted into a password hash before persistence.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function create(
        array $data
    ): array {
        $this->assertRequiredFields($data);

        $password = $data['password'] ?? '';

        if (
            !is_string($password)
            || $password === ''
        ) {
            throw new InvalidArgumentException(
                'Mật khẩu không hợp lệ.'
            );
        }

        return $this->withExclusiveLock(
            function ($handle) use (
                $data,
                $password
            ): array {
                $xml = $this->loadXmlFromHandle(
                    $handle
                );

                /*
                 * Protect against duplicate usernames even when
                 * two requests arrive almost simultaneously.
                 */
                $username = mb_strtolower(
                    trim((string) $data['username']),
                    'UTF-8'
                );

                foreach ($xml->user as $existingUser) {
                    $existingUsername = mb_strtolower(
                        (string) $existingUser->username,
                        'UTF-8'
                    );

                    if (
                        $existingUsername === $username
                    ) {
                        throw new RuntimeException(
                            'Tên đăng nhập đã tồn tại.',
                            409
                        );
                    }
                }

                $node = $xml->addChild('user');

                $data['username'] = trim(
                    (string) $data['username']
                );

                $data['password_hash'] = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if (
                    !is_string(
                        $data['password_hash']
                    )
                ) {
                    throw new RuntimeException(
                        'Không thể tạo password hash.'
                    );
                }

                unset(
                    $data['password']
                );

                $this->writeUserNode(
                    $node,
                    $data
                );

                $this->saveXmlToHandle(
                    $xml,
                    $handle
                );

                return $this->nodeToArray($node);
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    /**
     * Update user profile/account information.
     *
     * If password is supplied, it will be re-hashed.
     *
     * @param string $id
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function update(
        string $id,
        array $data
    ): array {
        $id = trim($id);

        if ($id === '') {
            throw new InvalidArgumentException(
                'User ID không được để trống.'
            );
        }

        return $this->withExclusiveLock(
            function ($handle) use (
                $id,
                $data
            ): array {
                $xml = $this->loadXmlFromHandle(
                    $handle
                );

                $targetNode = null;

                foreach ($xml->user as $node) {
                    if (
                        (string) $node->id === $id
                    ) {
                        $targetNode = $node;

                        break;
                    }
                }

                if ($targetNode === null) {
                    throw new RuntimeException(
                        'Không tìm thấy tài khoản.',
                        404
                    );
                }

                /*
                 * Check username uniqueness if username is being changed.
                 */
                if (
                    isset($data['username'])
                ) {
                    $username = trim(
                        (string) $data['username']
                    );

                    $normalizedUsername = mb_strtolower(
                        $username,
                        'UTF-8'
                    );

                    foreach ($xml->user as $node) {
                        if (
                            (string) $node->id === $id
                        ) {
                            continue;
                        }

                        $existingUsername = mb_strtolower(
                            (string) $node->username,
                            'UTF-8'
                        );

                        if (
                            $existingUsername
                            === $normalizedUsername
                        ) {
                            throw new RuntimeException(
                                'Tên đăng nhập đã tồn tại.',
                                409
                            );
                        }
                    }
                }

                /*
                 * Never allow an empty password to overwrite
                 * the existing password hash.
                 */
                if (
                    array_key_exists(
                        'password',
                        $data
                    )
                ) {
                    $password = $data['password'];

                    if (
                        !is_string($password)
                        || $password === ''
                    ) {
                        unset(
                            $data['password']
                        );
                    } else {
                        $data['password_hash'] =
                            password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );

                        if (
                            !is_string(
                                $data['password_hash']
                            )
                        ) {
                            throw new RuntimeException(
                                'Không thể tạo password hash.'
                            );
                        }

                        unset(
                            $data['password']
                        );
                    }
                }

                $this->writeUserNode(
                    $targetNode,
                    $data
                );

                $this->saveXmlToHandle(
                    $xml,
                    $handle
                );

                return $this->nodeToArray(
                    $targetNode
                );
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Password operations
    |--------------------------------------------------------------------------
    */

    /**
     * Verify a plaintext password against a user's stored hash.
     */
    public function verifyPassword(
        string $userId,
        string $password
    ): bool {
        $user = $this->findById(
            $userId
        );

        if ($user === null) {
            return false;
        }

        $passwordHash = $user['password_hash'] ?? '';

        if (
            !is_string($passwordHash)
            || $passwordHash === ''
        ) {
            return false;
        }

        return password_verify(
            $password,
            $passwordHash
        );
    }


    /**
     * Update password for a user.
     */
    public function updatePassword(
        string $userId,
        string $newPassword
    ): bool {
        $userId = trim($userId);

        if (
            $userId === ''
            || $newPassword === ''
        ) {
            return false;
        }

        $passwordHash = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

        if (!is_string($passwordHash)) {
            throw new RuntimeException(
                'Không thể tạo password hash.'
            );
        }

        $this->withExclusiveLock(
            function ($handle) use (
                $userId,
                $passwordHash
            ): void {
                $xml = $this->loadXmlFromHandle(
                    $handle
                );

                foreach ($xml->user as $node) {
                    if (
                        (string) $node->id !== $userId
                    ) {
                        continue;
                    }

                    $node->password_hash =
                        $passwordHash;

                    $node->updated_at =
                        now_datetime();

                    $this->saveXmlToHandle(
                        $xml,
                        $handle
                    );

                    return;
                }

                throw new RuntimeException(
                    'Không tìm thấy tài khoản.',
                    404
                );
            }
        );

        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | Login metadata
    |--------------------------------------------------------------------------
    */

    /**
     * Update last login timestamp.
     */
    public function markLogin(
        string $userId
    ): bool {
        $userId = trim($userId);

        if ($userId === '') {
            return false;
        }

        $this->withExclusiveLock(
            function ($handle) use (
                $userId
            ): void {
                $xml = $this->loadXmlFromHandle(
                    $handle
                );

                foreach ($xml->user as $node) {
                    if (
                        (string) $node->id !== $userId
                    ) {
                        continue;
                    }

                    $node->last_login_at =
                        now_datetime();

                    $node->updated_at =
                        now_datetime();

                    $this->saveXmlToHandle(
                        $xml,
                        $handle
                    );

                    return;
                }

                throw new RuntimeException(
                    'Không tìm thấy tài khoản.',
                    404
                );
            }
        );

        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    /**
     * Delete a user record.
     *
     * Authorization belongs to auth/service layer.
     */
    public function delete(
        string $id
    ): bool {
        $id = trim($id);

        if ($id === '') {
            return false;
        }

        return $this->withExclusiveLock(
            function ($handle) use ($id): bool {
                $xml = $this->loadXmlFromHandle(
                    $handle
                );

                $index = 0;

                foreach ($xml->user as $node) {
                    if (
                        (string) $node->id !== $id
                    ) {
                        $index++;

                        continue;
                    }

                    unset(
                        $xml->user[$index]
                    );

                    $this->saveXmlToHandle(
                        $xml,
                        $handle
                    );

                    return true;
                }

                return false;
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | XML mapping
    |--------------------------------------------------------------------------
    */

    /**
     * Convert XML user node to application array.
     *
     * @return array<string, mixed>
     */
    private function nodeToArray(
        SimpleXMLElement $node
    ): array {
        return [
            'id' => (string) $node->id,
            'username' => (string) $node->username,
            'password_hash' => (string) $node->password_hash,
            'display_name' => (string) $node->display_name,
            'email' => (string) $node->email,
            'role' => (string) $node->role,
            'status' => (string) $node->status,
            'created_at' => (string) $node->created_at,
            'updated_at' => (string) $node->updated_at,
            'last_login_at' => (string) $node->last_login_at,
        ];
    }


    /**
     * Write user data into an XML node.
     *
     * @param SimpleXMLElement $node
     * @param array<string, mixed> $data
     */
    private function writeUserNode(
        SimpleXMLElement $node,
        array $data
    ): void {
        $fields = [
            'id',
            'username',
            'password_hash',
            'display_name',
            'email',
            'role',
            'status',
            'created_at',
            'updated_at',
            'last_login_at',
        ];

        foreach ($fields as $field) {
            if (
                !array_key_exists(
                    $field,
                    $data
                )
            ) {
                continue;
            }

            $this->setXmlValue(
                $node,
                $field,
                $data[$field]
            );
        }
    }


    /**
     * Set an XML element value.
     */
    private function setXmlValue(
        SimpleXMLElement $parent,
        string $name,
        mixed $value
    ): void {
        if (isset($parent->{$name})) {
            $parent->{$name} = (string) $value;

            return;
        }

        $parent->addChild(
            $name,
            (string) $value
        );
    }


    /*
    |--------------------------------------------------------------------------
    | XML loading/persistence
    |--------------------------------------------------------------------------
    */

    /**
     * Load users.xml.
     */
    private function loadXml(): SimpleXMLElement
    {
        $this->ensureXmlFile();

        $previousUseErrors =
            libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_file(
                $this->xmlPath,
                SimpleXMLElement::class,
                LIBXML_NONET
            );

            if ($xml === false) {
                $this->throwXmlError();
            }

            return $xml;
        } finally {
            libxml_use_internal_errors(
                $previousUseErrors
            );
        }
    }


    /**
     * Load XML from a locked handle.
     *
     * @param resource $handle
     */
    private function loadXmlFromHandle(
        $handle
    ): SimpleXMLElement {
        if (
            fseek(
                $handle,
                0,
                SEEK_SET
            ) !== 0
        ) {
            throw new RuntimeException(
                'Không thể đọc file users.xml.'
            );
        }

        $contents = stream_get_contents(
            $handle
        );

        if ($contents === false) {
            throw new RuntimeException(
                'Không thể đọc dữ liệu users.xml.'
            );
        }

        if (trim($contents) === '') {
            return new SimpleXMLElement(
                '<users></users>'
            );
        }

        $previousUseErrors =
            libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string(
                $contents,
                SimpleXMLElement::class,
                LIBXML_NONET
            );

            if ($xml === false) {
                $this->throwXmlError();
            }

            return $xml;
        } finally {
            libxml_use_internal_errors(
                $previousUseErrors
            );
        }
    }


    /**
     * Save XML through an already locked handle.
     *
     * @param resource $handle
     */
    private function saveXmlToHandle(
        SimpleXMLElement $xml,
        $handle
    ): void {
        $contents = $xml->asXML();

        if (
            $contents === false
            || trim($contents) === ''
        ) {
            throw new RuntimeException(
                'Không thể tạo dữ liệu XML.'
            );
        }

        if (
            ftruncate(
                $handle,
                0
            ) === false
        ) {
            throw new RuntimeException(
                'Không thể làm trống users.xml.'
            );
        }

        if (
            fseek(
                $handle,
                0,
                SEEK_SET
            ) !== 0
        ) {
            throw new RuntimeException(
                'Không thể đưa con trỏ file về đầu.'
            );
        }

        $written = fwrite(
            $handle,
            $contents
        );

        if (
            $written === false
            || $written < strlen($contents)
        ) {
            throw new RuntimeException(
                'Không thể ghi đầy đủ users.xml.'
            );
        }

        fflush($handle);
    }


    /**
     * Execute a callback under an exclusive file lock.
     *
     * @param callable $callback
     */
    private function withExclusiveLock(
        callable $callback
    ): mixed {
        $this->ensureXmlFile();

        $handle = fopen(
            $this->xmlPath,
            'c+b'
        );

        if ($handle === false) {
            throw new RuntimeException(
                'Không thể mở users.xml để ghi.'
            );
        }

        try {
            if (
                !flock(
                    $handle,
                    LOCK_EX
                )
            ) {
                throw new RuntimeException(
                    'Không thể khóa users.xml.'
                );
            }

            try {
                return $callback($handle);
            } finally {
                flock(
                    $handle,
                    LOCK_UN
                );
            }
        } finally {
            fclose($handle);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Repository contract
    |--------------------------------------------------------------------------
    */

    /**
     * Check minimum required user fields.
     *
     * Detailed user validation belongs to validator.php
     * or the future auth/user service layer.
     *
     * @param array<string, mixed> $data
     */
    private function assertRequiredFields(
        array $data
    ): void {
        $requiredFields = [
            'id',
            'username',
            'role',
            'status',
        ];

        foreach ($requiredFields as $field) {
            if (
                !array_key_exists(
                    $field,
                    $data
                )
                || $data[$field] === ''
                || $data[$field] === null
            ) {
                throw new InvalidArgumentException(
                    'Thiếu trường bắt buộc: ' . $field
                );
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | XML initialization
    |--------------------------------------------------------------------------
    */

    /**
     * Ensure users.xml exists.
     */
    private function ensureXmlFile(): void
    {
        $directory = dirname(
            $this->xmlPath
        );

        if (
            !ensure_directory($directory)
        ) {
            throw new RuntimeException(
                'Không thể tạo thư mục XML.'
            );
        }

        if (
            !file_exists(
                $this->xmlPath
            )
        ) {
            $initialXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<users>
</users>
XML;

            if (
                file_put_contents(
                    $this->xmlPath,
                    $initialXml,
                    LOCK_EX
                ) === false
            ) {
                throw new RuntimeException(
                    'Không thể tạo users.xml.'
                );
            }

            return;
        }

        if (
            !is_readable(
                $this->xmlPath
            )
        ) {
            throw new RuntimeException(
                'Không thể đọc users.xml.'
            );
        }

        if (
            !is_writable(
                $this->xmlPath
            )
        ) {
            throw new RuntimeException(
                'Không thể ghi users.xml.'
            );
        }
    }


    /**
     * Throw an XML parsing exception.
     */
    private function throwXmlError(): never
    {
        $errors = libxml_get_errors();

        $details = [];

        foreach ($errors as $error) {
            $details[] = trim(
                $error->message
            );
        }

        libxml_clear_errors();

        $message =
            'Không thể đọc dữ liệu users.xml.';

        if ($details !== []) {
            $message .= ' '
                . implode(
                    ' | ',
                    $details
                );
        }

        throw new RuntimeException(
            $message
        );
    }
}