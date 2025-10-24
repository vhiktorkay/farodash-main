<?php
require_once '../includes/session_handler.php';
require_once '../includes/auth_functions.php';

$current_user = getCurrentUserOrRedirect('../auth/login.php');
$auth = new AuthManager();

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_address':
            $address_data = [
                'address_type' => $_POST['address_type'] ?? 'home',
                'label' => trim($_POST['label'] ?? ''),
                'address_line_1' => trim($_POST['address_line_1'] ?? ''),
                'address_line_2' => trim($_POST['address_line_2'] ?? ''),
                'city' => trim($_POST['city'] ?? ''),
                'state' => trim($_POST['state'] ?? ''),
                'postal_code' => trim($_POST['postal_code'] ?? ''),
                'country' => trim($_POST['country'] ?? 'Nigeria'),
                'is_default' => isset($_POST['is_default'])
            ];

            $result = $auth->addUserAddress($current_user['id'], $address_data);
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
            break;

        case 'update_address':
            $address_id = $_POST['address_id'] ?? 0;
            $address_data = [
                'address_type' => $_POST['address_type'] ?? 'home',
                'label' => trim($_POST['label'] ?? ''),
                'address_line_1' => trim($_POST['address_line_1'] ?? ''),
                'address_line_2' => trim($_POST['address_line_2'] ?? ''),
                'city' => trim($_POST['city'] ?? ''),
                'state' => trim($_POST['state'] ?? ''),
                'postal_code' => trim($_POST['postal_code'] ?? ''),
                'country' => trim($_POST['country'] ?? 'Nigeria'),
                'is_default' => isset($_POST['is_default'])
            ];

            $result = $auth->updateUserAddress($current_user['id'], $address_id, $address_data);
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
            break;

        case 'set_default':
            $address_id = $_POST['address_id'] ?? 0;
            $result = $auth->setDefaultAddress($current_user['id'], $address_id);
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
            break;

        case 'delete_address':
            $address_id = $_POST['address_id'] ?? 0;
            $result = $auth->deleteUserAddress($current_user['id'], $address_id);
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
            break;
    }
}

// Get user addresses
$user_addresses = $auth->getUserAddresses($current_user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Addresses - FaroDash</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8f9fa;
            color: #000000;
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #000;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #666;
            font-size: 16px;
        }

        .message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success-message {
            background-color: #d1edff;
            color: #0c63e4;
            border: 1px solid #b8daff;
        }

        .error-message {
            background-color: #fee;
            color: #d63384;
            border: 1px solid #f5c2c7;
        }

        .addresses-grid {
            display: grid;
            gap: 20px;
            margin-bottom: 24px;
        }

        .address-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: relative;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .address-card.default {
            border-color: #ED1B26;
        }

        .address-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .address-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .address-label {
            font-size: 18px;
            font-weight: 600;
            color: #000;
        }

        .address-type {
            background-color: #f8f9fa;
            color: #666;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            text-transform: capitalize;
        }

        .default-badge {
            background-color: #ED1B26;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .address-content {
            color: #666;
            line-height: 1.5;
            margin-bottom: 16px;
        }

        .address-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background-color: #ED1B26;
            color: white;
        }

        .btn-primary:hover {
            background-color: #d41420;
        }

        .btn-secondary {
            background-color: #f8f9fa;
            color: #666;
            border: 1px solid #e9ecef;
        }

        .btn-secondary:hover {
            background-color: #e9ecef;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .add-address-btn {
            background-color: #ED1B26;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 16px 24px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-bottom: 24px;
            transition: background-color 0.3s ease;
        }

        .add-address-btn:hover {
            background-color: #d41420;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e9ecef;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #000;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 4px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #000;
            margin-bottom: 8px;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            font-family: 'Outfit', sans-serif;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .form-input:focus, .form-select:focus {
            border-color: #ED1B26;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #ED1B26;
        }

        .no-addresses {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .no-addresses-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            opacity: 0.5;
        }

        /* MOBILE RESPONSIVE */
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .address-actions {
                justify-content: center;
            }

            .modal-content {
                margin: 20px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">My Addresses</h1>
            <p class="page-subtitle">Manage your delivery addresses for faster checkout</p>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Add Address Button -->
        <button class="add-address-btn" onclick="openAddModal()">+ Add New Address</button>

        <!-- Addresses Grid -->
        <?php if (empty($user_addresses)): ?>
            <div class="no-addresses">
                <svg class="no-addresses-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C8.13 2 5 5.13 5 9C5 14.25 12 22 12 22C12 22 19 14.25 19 9C19 5.13 15.87 2 12 2ZM12 11.5C10.62 11.5 9.5 10.38 9.5 9C9.5 7.62 10.62 6.5 12 6.5C13.38 6.5 14.5 7.62 14.5 9C14.5 10.38 13.38 11.5 12 11.5Z" fill="#ccc"/>
                </svg>
                <h3>No Addresses Added</h3>
                <p>Add your first delivery address to get started with ordering</p>
            </div>
        <?php else: ?>
            <div class="addresses-grid">
                <?php foreach ($user_addresses as $address): ?>
                    <div class="address-card <?php echo $address['is_default'] ? 'default' : ''; ?>">
                        <div class="address-header">
                            <h3 class="address-label"><?php echo htmlspecialchars($address['label']); ?></h3>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <span class="address-type"><?php echo ucfirst($address['address_type']); ?></span>
                                <?php if ($address['is_default']): ?>
                                    <span class="default-badge">Default</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="address-content">
                            <?php echo htmlspecialchars($address['address_line_1']); ?>
                            <?php if ($address['address_line_2']): ?><br><?php echo htmlspecialchars($address['address_line_2']); ?><?php endif; ?>
                            <br><?php echo htmlspecialchars($address['city']); ?>, <?php echo htmlspecialchars($address['state']); ?> <?php echo htmlspecialchars($address['postal_code']); ?>
                            <br><?php echo htmlspecialchars($address['country']); ?>
                        </div>

                        <div class="address-actions">
                            <button class="btn btn-primary" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($address)); ?>)">Edit</button>
                            
                            <?php if (!$address['is_default']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="set_default">
                                    <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                                    <button type="submit" class="btn btn-secondary">Set as Default</button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this address?')">
                                <input type="hidden" name="action" value="delete_address">
                                <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Back Button -->
        <a href="../account.php" class="btn btn-secondary" style="display: inline-block; margin-top: 20px;">← Back to Account</a>
    </div>

    <!-- Add/Edit Address Modal -->
    <div class="modal" id="addressModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Add New Address</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>

            <form method="POST" id="addressForm">
                <input type="hidden" name="action" value="add_address" id="formAction">
                <input type="hidden" name="address_id" id="addressId">

                <div class="form-group">
                    <label class="form-label">Address Label *</label>
                    <input type="text" name="label" class="form-input" placeholder="e.g., Home, Office" required id="labelInput">
                </div>

                <div class="form-group">
                    <label class="form-label">Address Type</label>
                    <select name="address_type" class="form-select" id="typeSelect">
                        <option value="home">Home</option>
                        <option value="work">Work</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Address Line 1 *</label>
                    <input type="text" name="address_line_1" class="form-input" placeholder="Street address, building number" required id="address1Input">
                </div>

                <div class="form-group">
                    <label class="form-label">Address Line 2</label>
                    <input type="text" name="address_line_2" class="form-input" placeholder="Apartment, suite, unit (optional)" id="address2Input">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">City *</label>
                        <input type="text" name="city" class="form-input" placeholder="City" required id="cityInput">
                    </div>
                    <div class="form-group">
                        <label class="form-label">State *</label>
                        <input type="text" name="state" class="form-input" placeholder="State" required id="stateInput">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Postal Code *</label>
                        <input type="text" name="postal_code" class="form-input" placeholder="Postal code" required id="postalInput">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <select name="country" class="form-select" id="countrySelect">
                            <option value="Nigeria">Nigeria</option>
                            <option value="Ghana">Ghana</option>
                            <option value="Kenya">Kenya</option>
                        </select>
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" name="is_default" id="defaultCheckbox">
                    <label for="defaultCheckbox">Set as default address</label>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Save Address</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Address';
            document.getElementById('formAction').value = 'add_address';
            document.getElementById('addressForm').reset();
            document.getElementById('addressModal').classList.add('active');
        }

        function openEditModal(address) {
            document.getElementById('modalTitle').textContent = 'Edit Address';
            document.getElementById('formAction').value = 'update_address';
            document.getElementById('addressId').value = address.id;
            
            // Populate form fields
            document.getElementById('labelInput').value = address.label;
            document.getElementById('typeSelect').value = address.address_type;
            document.getElementById('address1Input').value = address.address_line_1;
            document.getElementById('address2Input').value = address.address_line_2 || '';
            document.getElementById('cityInput').value = address.city;
            document.getElementById('stateInput').value = address.state;
            document.getElementById('postalInput').value = address.postal_code;
            document.getElementById('countrySelect').value = address.country;
            document.getElementById('defaultCheckbox').checked = address.is_default == '1';
            
            document.getElementById('addressModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('addressModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('addressModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>