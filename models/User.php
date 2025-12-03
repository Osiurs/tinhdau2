<?php
/**
 * Class User - Quản lý người dùng
 * Lưu trữ thông tin user trong CSV file
 */

require_once __DIR__ . '/../config/database.php';

class User {
    private $filePath;
    private $headers = ['id', 'username', 'password', 'full_name', 'role', 'status', 'created_at', 'updated_at'];

    public function __construct() {
        $this->filePath = __DIR__ . '/../data/users.csv';
        $this->ensureStorage();
    }

    /**
     * Đảm bảo file CSV tồn tại với header
     */
    private function ensureStorage() {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        
        if (!file_exists($this->filePath)) {
            $fh = fopen($this->filePath, 'w');
            if ($fh) {
                fputcsv($fh, $this->headers);
                fclose($fh);
            }
        }
    }

    /**
     * Lấy tất cả users
     */
    public function getAll() {
        $users = [];
        if (($fh = fopen($this->filePath, 'r')) !== false) {
            $headers = fgetcsv($fh);
            while (($data = fgetcsv($fh)) !== false) {
                if (count($data) === count($headers)) {
                    $user = array_combine($headers, $data);
                    // Không trả về password
                    unset($user['password']);
                    $users[] = $user;
                }
            }
            fclose($fh);
        }
        return $users;
    }

    /**
     * Lấy user theo username
     */
    public function getByUsername($username) {
        if (($fh = fopen($this->filePath, 'r')) !== false) {
            $headers = fgetcsv($fh);
            while (($data = fgetcsv($fh)) !== false) {
                if (count($data) === count($headers)) {
                    $user = array_combine($headers, $data);
                    if ($user['username'] === $username) {
                        fclose($fh);
                        return $user;
                    }
                }
            }
            fclose($fh);
        }
        return null;
    }

    /**
     * Lấy user theo ID
     */
    public function getById($id) {
        if (($fh = fopen($this->filePath, 'r')) !== false) {
            $headers = fgetcsv($fh);
            while (($data = fgetcsv($fh)) !== false) {
                if (count($data) === count($headers)) {
                    $user = array_combine($headers, $data);
                    if ($user['id'] == $id) {
                        fclose($fh);
                        return $user;
                    }
                }
            }
            fclose($fh);
        }
        return null;
    }

    /**
     * Tạo user mới
     */
    public function create($username, $password, $fullName, $role = 'user') {
        // Kiểm tra username đã tồn tại
        if ($this->getByUsername($username)) {
            throw new Exception('Tên đăng nhập đã tồn tại');
        }

        // Validate
        if (empty($username) || empty($password)) {
            throw new Exception('Tên đăng nhập và mật khẩu không được để trống');
        }

        if (!in_array($role, ['admin', 'user'])) {
            throw new Exception('Role không hợp lệ');
        }

        // Tạo ID mới
        $newId = $this->getNextId();

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $user = [
            'id' => $newId,
            'username' => $username,
            'password' => $hashedPassword,
            'full_name' => $fullName,
            'role' => $role,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Ghi vào file
        $fh = fopen($this->filePath, 'a');
        if ($fh && flock($fh, LOCK_EX)) {
            fputcsv($fh, $user);
            flock($fh, LOCK_UN);
            fclose($fh);
            
            // Không trả về password
            unset($user['password']);
            return $user;
        }

        throw new Exception('Không thể tạo user');
    }

    /**
     * Cập nhật user
     */
    public function update($id, $data) {
        $users = $this->readAllUsers();
        $updated = false;

        foreach ($users as &$user) {
            if ($user['id'] == $id) {
                // Cập nhật các trường được phép
                if (isset($data['full_name'])) {
                    $user['full_name'] = $data['full_name'];
                }
                if (isset($data['role']) && in_array($data['role'], ['admin', 'user'])) {
                    $user['role'] = $data['role'];
                }
                if (isset($data['status']) && in_array($data['status'], ['active', 'inactive'])) {
                    $user['status'] = $data['status'];
                }
                if (isset($data['password']) && !empty($data['password'])) {
                    $user['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                }
                $user['updated_at'] = date('Y-m-d H:i:s');
                $updated = true;
                break;
            }
        }

        if ($updated) {
            $this->writeAllUsers($users);
            return true;
        }

        return false;
    }

    /**
     * Xóa user
     */
    public function delete($id) {
        $users = $this->readAllUsers();
        $initialCount = count($users);

        $users = array_filter($users, function($user) use ($id) {
            return $user['id'] != $id;
        });

        if (count($users) < $initialCount) {
            $this->writeAllUsers($users);
            return true;
        }

        return false;
    }

    /**
     * Xác thực đăng nhập
     */
    public function authenticate($username, $password) {
        $user = $this->getByUsername($username);

        if (!$user) {
            return false;
        }

        if ($user['status'] !== 'active') {
            return false;
        }

        if (password_verify($password, $user['password'])) {
            // Không trả về password
            unset($user['password']);
            return $user;
        }

        return false;
    }

    /**
     * Kiểm tra user có phải admin không
     */
    public function isAdmin($userId) {
        $user = $this->getById($userId);
        return $user && $user['role'] === 'admin';
    }

    /**
     * Đổi mật khẩu
     */
    public function changePassword($id, $oldPassword, $newPassword) {
        $user = $this->getById($id);

        if (!$user) {
            throw new Exception('User không tồn tại');
        }

        if (!password_verify($oldPassword, $user['password'])) {
            throw new Exception('Mật khẩu cũ không đúng');
        }

        if (empty($newPassword)) {
            throw new Exception('Mật khẩu mới không được để trống');
        }

        return $this->update($id, ['password' => $newPassword]);
    }

    /**
     * Đọc tất cả users (bao gồm password)
     */
    private function readAllUsers() {
        $users = [];
        if (($fh = fopen($this->filePath, 'r')) !== false) {
            $headers = fgetcsv($fh);
            while (($data = fgetcsv($fh)) !== false) {
                if (count($data) === count($headers)) {
                    $users[] = array_combine($headers, $data);
                }
            }
            fclose($fh);
        }
        return $users;
    }

    /**
     * Ghi tất cả users vào file
     */
    private function writeAllUsers($users) {
        $fh = fopen($this->filePath, 'w');
        if ($fh && flock($fh, LOCK_EX)) {
            fputcsv($fh, $this->headers);
            foreach ($users as $user) {
                $row = [];
                foreach ($this->headers as $header) {
                    $row[] = $user[$header] ?? '';
                }
                fputcsv($fh, $row);
            }
            flock($fh, LOCK_UN);
            fclose($fh);
            return true;
        }
        return false;
    }

    /**
     * Lấy ID tiếp theo
     */
    private function getNextId() {
        $maxId = 0;
        if (($fh = fopen($this->filePath, 'r')) !== false) {
            $headers = fgetcsv($fh);
            while (($data = fgetcsv($fh)) !== false) {
                if (count($data) === count($headers)) {
                    $user = array_combine($headers, $data);
                    if (isset($user['id']) && $user['id'] > $maxId) {
                        $maxId = (int)$user['id'];
                    }
                }
            }
            fclose($fh);
        }
        return $maxId + 1;
    }
}
