<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$db_file = 'db.json';
$upload_dir = 'uploads/';

// Initialize DB if not exists
if (!file_exists($db_file)) {
    $initial_data = [
        'users' => [
            [
                'id' => 'admin_1',
                'name' => 'Admin',
                'email' => 'admin@mess.com',
                'password' => '123456',
                'mobile' => '0000000000',
                'role' => 'admin',
                'createdAt' => date('c')
            ],
            [
                'id' => 'user_1',
                'name' => 'Demo User',
                'email' => 'user@mess.com',
                'password' => '123456',
                'mobile' => '1111111111',
                'role' => 'user',
                'createdAt' => date('c')
            ]
        ],
        'meals' => [],
        'app_updates' => [],
        'bug_reports' => [],
        'notifications' => [],
        'settings' => [
            'mess_details' => [
                'name' => 'Royal Mess',
                'phone' => '+91 9876543210',
                'address' => '123 Mess Street, Food City'
            ],
            'global_update' => [
                'version' => '1.0.0',
                'url' => '#',
                'description' => 'Initial release',
                'active' => false
            ]
        ]
    ];
    file_put_contents($db_file, json_encode($initial_data, JSON_PRETTY_PRINT));
}

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

function get_db() {
    global $db_file;
    return json_decode(file_get_contents($db_file), true);
}

function save_db($data) {
    global $db_file;
    file_put_contents($db_file, json_encode($data, JSON_PRETTY_PRINT));
}

$action = $_REQUEST['action'] ?? '';

// Handle PDF Download (Existing logic)
if (isset($_GET['data']) && $action == 'download_pdf') {
    $data = base64_decode($_GET['data']);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="meal_report.pdf"');
    echo $data;
    exit;
}

// API Endpoints
switch ($action) {
    case 'login':
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $db = get_db();
        foreach ($db['users'] as $user) {
            if ($user['email'] === $email && $user['password'] === $password) {
                $_SESSION['user_id'] = $user['id'];
                echo json_encode(['success' => true, 'user' => $user]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        break;

    case 'signup':
        $db = get_db();
        $newUser = [
            'id' => 'u_' . uniqid(),
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'password' => $_POST['password'],
            'mobile' => $_POST['mobile'],
            'role' => 'user',
            'createdAt' => date('c')
        ];
        // Check if email exists
        foreach ($db['users'] as $u) {
            if ($u['email'] === $newUser['email']) {
                echo json_encode(['success' => false, 'message' => 'Email already exists']);
                exit;
            }
        }
        $db['users'][] = $newUser;
        save_db($db);
        echo json_encode(['success' => true, 'user' => $newUser]);
        break;

    case 'get_all_data':
        echo json_encode(['success' => true, 'data' => get_db()]);
        break;

    case 'save_meal':
        $db = get_db();
        $mealData = json_decode($_POST['meal'], true);
        $found = false;
        foreach ($db['meals'] as &$m) {
            if ($m['userId'] === $mealData['userId'] && $m['date'] === $mealData['date']) {
                $m = array_merge($m, $mealData);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $mealData['id'] = 'm_' . uniqid();
            $db['meals'][] = $mealData;
        }
        save_db($db);
        echo json_encode(['success' => true]);
        break;

    case 'save_user':
        $db = get_db();
        $userData = json_decode($_POST['user'], true);
        if (isset($userData['id'])) {
            foreach ($db['users'] as &$u) {
                if ($u['id'] === $userData['id']) {
                    $u = array_merge($u, $userData);
                    break;
                }
            }
        } else {
            $userData['id'] = 'u_' . uniqid();
            $userData['createdAt'] = date('c');
            $db['users'][] = $userData;
        }
        save_db($db);
        echo json_encode(['success' => true]);
        break;

    case 'delete_user':
        $db = get_db();
        $id = $_POST['id'];
        $db['users'] = array_values(array_filter($db['users'], function($u) use ($id) { return $u['id'] !== $id; }));
        save_db($db);
        echo json_encode(['success' => true]);
        break;

    case 'save_settings':
        $db = get_db();
        $type = $_POST['type'];
        $data = json_decode($_POST['data'], true);
        $db['settings'][$type] = $data;
        save_db($db);
        echo json_encode(['success' => true]);
        break;

    case 'add_update':
        $db = get_db();
        $update = [
            'id' => 'up_' . uniqid(),
            'title' => $_POST['title'],
            'createdAt' => date('c'),
            'fileUrl' => $_POST['fileUrl'] ?? '#'
        ];
        $db['app_updates'][] = $update;
        save_db($db);
        echo json_encode(['success' => true]);
        break;

    case 'delete_update':
        $db = get_db();
        $id = $_POST['id'];
        $db['app_updates'] = array_values(array_filter($db['app_updates'], function($u) use ($id) { return $u['id'] !== $id; }));
        save_db($db);
        echo json_encode(['success' => true]);
        break;

    case 'add_bug':
        $db = get_db();
        $bug = [
            'id' => 'bug_' . uniqid(),
            'userId' => $_POST['userId'],
            'userName' => $_POST['userName'],
            'description' => $_POST['description'],
            'status' => 'pending',
            'createdAt' => date('c')
        ];
        $db['bug_reports'][] = $bug;
        save_db($db);
        echo json_encode(['success' => true]);
        break;

    case 'update_bug':
        $db = get_db();
        $id = $_POST['id'];
        $status = $_POST['status'];
        foreach ($db['bug_reports'] as &$b) {
            if ($b['id'] === $id) {
                $b['status'] = $status;
                break;
            }
        }
        save_db($db);
        echo json_encode(['success' => true]);
        break;

    case 'send_notification':
        $db = get_db();
        $notif = [
            'id' => 'n_' . uniqid(),
            'title' => $_POST['title'],
            'body' => $_POST['body'],
            'createdAt' => date('c')
        ];
        $db['notifications'][] = $notif;
        save_db($db);
        echo json_encode(['success' => true]);
        break;

    case 'upload_file':
        if (isset($_FILES['file'])) {
            $file = $_FILES['file'];
            $fileName = time() . '_' . basename($file['name']);
            $targetPath = $upload_dir . $fileName;
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                echo json_encode(['success' => true, 'url' => $targetPath]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Upload failed']);
            }
        }
        break;

    default:
        if ($action) {
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
        break;
}
?>
