<?php
// add_property.php - Add/Sell Property
require_once 'config.php';

// Check if user is logged in
requireLogin();

$user_id = getCurrentUserId();
$errors = [];
$success = false;

// Get cities
$cities_list = [];
$city_result = $con->query("SELECT DISTINCT name FROM cities ORDER BY name");
if ($city_result) {
    while ($row = $city_result->fetch_assoc()) {
        $cities_list[] = $row['name'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token. Please try again.";
    } else {
        // Sanitize and validate inputs
        $title = sanitizeInput($_POST['title'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $property_type = sanitizeInput($_POST['property_type'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $area_size = intval($_POST['area_size'] ?? 0);
        $area_unit = sanitizeInput($_POST['area_unit'] ?? 'sqft');
        $bedrooms = intval($_POST['bedrooms'] ?? 0);
        $bathrooms = intval($_POST['bathrooms'] ?? 0);
        $location = sanitizeInput($_POST['location'] ?? '');
        $city = sanitizeInput($_POST['city'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        
        // Validation
        if (empty($title) || strlen($title) < 10) {
            $errors[] = "Title must be at least 10 characters long";
        }
        
        if (empty($description) || strlen($description) < 50) {
            $errors[] = "Description must be at least 50 characters long";
        }
        
        if (!in_array($property_type, ['house', 'apartment', 'plot', 'commercial', 'farmhouse'])) {
            $errors[] = "Please select a valid property type";
        }
        
        if ($price <= 0) {
            $errors[] = "Please enter a valid price";
        }
        
        if ($area_size <= 0) {
            $errors[] = "Please enter a valid area size";
        }
        
        if (empty($location)) {
            $errors[] = "Please enter property location";
        }
        
        if (empty($city)) {
            $errors[] = "Please select a city";
        }
        
        // Handle image upload
        $featured_image = null;
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            $file_type = $_FILES['featured_image']['type'];
            $file_size = $_FILES['featured_image']['size'];
            
            if (!in_array($file_type, $allowed_types)) {
                $errors[] = "Only JPG, JPEG, and PNG images are allowed";
            } elseif ($file_size > 5 * 1024 * 1024) { // 5MB max
                $errors[] = "Image size must be less than 5MB";
            } else {
                // Create upload directory if it doesn't exist
                $upload_dir = UPLOAD_PATH;
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Generate unique filename
                $file_extension = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('prop_') . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $upload_path)) {
                    $featured_image = $upload_path;
                } else {
                    $errors[] = "Failed to upload image. Please try again.";
                }
            }
        }
        
        // Insert property if no errors
        if (empty($errors)) {
            $stmt = $con->prepare("INSERT INTO properties (user_id, title, description, property_type, price, area_size, area_unit, bedrooms, bathrooms, location, city, address, featured_image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')");
            
            $stmt->bind_param("isssdisiiisss", 
                $user_id, 
                $title, 
                $description, 
                $property_type, 
                $price, 
                $area_size, 
                $area_unit, 
                $bedrooms, 
                $bathrooms, 
                $location, 
                $city, 
                $address, 
                $featured_image
            );
            
            if ($stmt->execute()) {
                $property_id = $stmt->insert_id;
                setFlashMessage('success', 'Property listed successfully!');
                redirect('property_details.php?id=' . $property_id);
            } else {
                $errors[] = "Failed to add property. Please try again.";
            }
            $stmt->close();
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ur">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Property - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/header.php'; ?>

    <div class="page-container">
        <div class="container">
            <div class="page-header">
                <h1>List Your Property</h1>
                <p>Fill in the details to sell your property</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data" class="property-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-section">
                    <h3>Basic Information</h3>
                    
                    <div class="form-group">
                        <label for="title">Property Title *</label>
                        <input type="text" id="title" name="title" required 
                               placeholder="e.g., Beautiful 3 Bedroom House in DHA"
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                        <small>Minimum 10 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Property Description *</label>
                        <textarea id="description" name="description" rows="6" required 
                                  placeholder="Describe your property in detail..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        <small>Minimum 50 characters</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="property_type">Property Type *</label>
                            <select id="property_type" name="property_type" required>
                                <option value="">Select Type</option>
                                <option value="house" <?php echo (isset($_POST['property_type']) && $_POST['property_type'] === 'house') ? 'selected' : ''; ?>>House</option>
                                <option value="apartment" <?php echo (isset($_POST['property_type']) && $_POST['property_type'] === 'apartment') ? 'selected' : ''; ?>>Apartment</option>
                                <option value="plot" <?php echo (isset($_POST['property_type']) && $_POST['property_type'] === 'plot') ? 'selected' : ''; ?>>Plot</option>
                                <option value="commercial" <?php echo (isset($_POST['property_type']) && $_POST['property_type'] === 'commercial') ? 'selected' : ''; ?>>Commercial</option>
                                <option value="farmhouse" <?php echo (isset($_POST['property_type']) && $_POST['property_type'] === 'farmhouse') ? 'selected' : ''; ?>>Farmhouse</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Price (PKR) *</label>
                            <input type="number" id="price" name="price" required min="0" step="1000"
                                   placeholder="e.g., 15000000"
                                   value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Property Details</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="area_size">Area Size *</label>
                            <input type="number" id="area_size" name="area_size" required min="1"
                                   placeholder="e.g., 2000"
                                   value="<?php echo isset($_POST['area_size']) ? htmlspecialchars($_POST['area_size']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="area_unit">Area Unit *</label>
                            <select id="area_unit" name="area_unit" required>
                                <option value="sqft" <?php echo (isset($_POST['area_unit']) && $_POST['area_unit'] === 'sqft') ? 'selected' : ''; ?>>Square Feet</option>
                                <option value="sqyd" <?php echo (isset($_POST['area_unit']) && $_POST['area_unit'] === 'sqyd') ? 'selected' : ''; ?>>Square Yards</option>
                                <option value="marla" <?php echo (isset($_POST['area_unit']) && $_POST['area_unit'] === 'marla') ? 'selected' : ''; ?>>Marla</option>
                                <option value="kanal" <?php echo (isset($_POST['area_unit']) && $_POST['area_unit'] === 'kanal') ? 'selected' : ''; ?>>Kanal</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bedrooms">Bedrooms</label>
                            <input type="number" id="bedrooms" name="bedrooms" min="0" max="20"
                                   placeholder="e.g., 3"
                                   value="<?php echo isset($_POST['bedrooms']) ? htmlspecialchars($_POST['bedrooms']) : '0'; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="bathrooms">Bathrooms</label>
                            <input type="number" id="bathrooms" name="bathrooms" min="0" max="20"
                                   placeholder="e.g., 2"
                                   value="<?php echo isset($_POST['bathrooms']) ? htmlspecialchars($_POST['bathrooms']) : '0'; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Location</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City *</label>
                            <select id="city" name="city" required>
                                <option value="">Select City</option>
                                <?php foreach ($cities_list as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>" 
                                            <?php echo (isset($_POST['city']) && $_POST['city'] === $c) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="location">Location/Area *</label>
                            <input type="text" id="location" name="location" required
                                   placeholder="e.g., DHA Phase 5"
                                   value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Complete Address</label>
                        <textarea id="address" name="address" rows="3"
                                  placeholder="Enter complete address..."><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Property Image</h3>
                    
                    <div class="form-group">
                        <label for="featured_image">Featured Image</label>
                        <input type="file" id="featured_image" name="featured_image" accept="image/jpeg,image/png,image/jpg">
                        <small>Maximum file size: 5MB. Allowed formats: JPG, JPEG, PNG</small>
                    </div>
                    
                    <div id="imagePreview"></div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">List Property</button>
                    <a href="dashboard.php" class="btn btn-secondary btn-lg">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script>
        // Image preview
        document.getElementById('featured_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const preview = document.getElementById('imagePreview');
                    preview.innerHTML = '<img src="' + event.target.result + '" alt="Preview" style="max-width: 300px; margin-top: 10px; border-radius: 8px;">';
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>