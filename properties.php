<?php
// properties.php - Browse Properties (Buy)
require_once 'config.php';

// Get search parameters
$search_city = isset($_GET['city']) ? sanitizeInput($_GET['city']) : '';
$search_type = isset($_GET['property_type']) ? sanitizeInput($_GET['property_type']) : '';
$search_price = isset($_GET['price_range']) ? sanitizeInput($_GET['price_range']) : '';
$search_area = isset($_GET['area_size']) ? sanitizeInput($_GET['area_size']) : '';
$search_keyword = isset($_GET['keyword']) ? sanitizeInput($_GET['keyword']) : '';

// Build query
$query = "SELECT p.*, u.full_name, u.phone, u.email FROM properties p 
          JOIN users u ON p.user_id = u.id 
          WHERE p.status = 'available'";

$params = [];
$types = '';

if (!empty($search_city)) {
    $query .= " AND p.city = ?";
    $params[] = $search_city;
    $types .= 's';
}

if (!empty($search_type)) {
    $query .= " AND p.property_type = ?";
    $params[] = $search_type;
    $types .= 's';
}

if (!empty($search_price)) {
    list($min_price, $max_price) = explode('-', $search_price);
    $query .= " AND p.price BETWEEN ? AND ?";
    $params[] = intval($min_price);
    $params[] = intval($max_price);
    $types .= 'ii';
}

if (!empty($search_area)) {
    list($min_area, $max_area) = explode('-', $search_area);
    $query .= " AND p.area_size BETWEEN ? AND ?";
    $params[] = intval($min_area);
    $params[] = intval($max_area);
    $types .= 'ii';
}

if (!empty($search_keyword)) {
    $query .= " AND (p.title LIKE ? OR p.description LIKE ? OR p.location LIKE ?)";
    $keyword_param = '%' . $search_keyword . '%';
    $params[] = $keyword_param;
    $params[] = $keyword_param;
    $params[] = $keyword_param;
    $types .= 'sss';
}

$query .= " ORDER BY p.created_at DESC";

// Execute query
$stmt = $con->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$properties = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get cities for filter
$cities_list = [];
$city_result = $con->query("SELECT DISTINCT city FROM properties WHERE status = 'available' ORDER BY city");
if ($city_result) {
    while ($row = $city_result->fetch_assoc()) {
        $cities_list[] = $row['city'];
    }
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="ur">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Properties - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/header.php'; ?>

    <!-- Flash Message -->
    <?php if ($flash): ?>
    <div class="flash-message flash-<?php echo htmlspecialchars($flash['type']); ?>">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
    <?php endif; ?>

    <div class="page-container">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Browse Properties</h1>
                <p>Find your dream property from <?php echo count($properties); ?> available listings</p>
            </div>

            <!-- Search & Filter Section -->
            <div class="search-filter-section">
                <form method="GET" action="" class="filter-form">
                    <div class="filter-grid">
                        <div class="filter-item">
                            <label for="keyword">Search</label>
                            <input type="text" id="keyword" name="keyword" 
                                   placeholder="Search by keyword..."
                                   value="<?php echo htmlspecialchars($search_keyword); ?>">
                        </div>
                        
                        <div class="filter-item">
                            <label for="city">City</label>
                            <select id="city" name="city">
                                <option value="">All Cities</option>
                                <?php foreach ($cities_list as $city): ?>
                                    <option value="<?php echo htmlspecialchars($city); ?>"
                                            <?php echo ($search_city === $city) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($city); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-item">
                            <label for="property_type">Type</label>
                            <select id="property_type" name="property_type">
                                <option value="">All Types</option>
                                <option value="house" <?php echo ($search_type === 'house') ? 'selected' : ''; ?>>House</option>
                                <option value="apartment" <?php echo ($search_type === 'apartment') ? 'selected' : ''; ?>>Apartment</option>
                                <option value="plot" <?php echo ($search_type === 'plot') ? 'selected' : ''; ?>>Plot</option>
                                <option value="commercial" <?php echo ($search_type === 'commercial') ? 'selected' : ''; ?>>Commercial</option>
                                <option value="farmhouse" <?php echo ($search_type === 'farmhouse') ? 'selected' : ''; ?>>Farmhouse</option>
                            </select>
                        </div>
                        
                        <div class="filter-item">
                            <label for="price_range">Price Range</label>
                            <select id="price_range" name="price_range">
                                <option value="">Any Budget</option>
                                <option value="0-5000000" <?php echo ($search_price === '0-5000000') ? 'selected' : ''; ?>>Under 50 Lac</option>
                                <option value="5000000-10000000" <?php echo ($search_price === '5000000-10000000') ? 'selected' : ''; ?>>50 Lac - 1 Crore</option>
                                <option value="10000000-25000000" <?php echo ($search_price === '10000000-25000000') ? 'selected' : ''; ?>>1 Crore - 2.5 Crore</option>
                                <option value="25000000-50000000" <?php echo ($search_price === '25000000-50000000') ? 'selected' : ''; ?>>2.5 Crore - 5 Crore</option>
                                <option value="50000000-999999999" <?php echo ($search_price === '50000000-999999999') ? 'selected' : ''; ?>>Above 5 Crore</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="properties.php" class="btn btn-secondary">Clear Filters</a>
                    </div>
                </form>
            </div>

            <!-- Properties Grid -->
            <?php if (empty($properties)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🔍</div>
                    <h3>No Properties Found</h3>
                    <p>Try adjusting your search filters or browse all properties</p>
                    <a href="properties.php" class="btn btn-primary">View All Properties</a>
                </div>
            <?php else: ?>
                <div class="results-header">
                    <p>Showing <strong><?php echo count($properties); ?></strong> properties</p>
                </div>
                
                <div class="property-grid">
                    <?php foreach ($properties as $property): ?>
                        <div class="property-card">
                            <div class="property-image">
                                <?php if ($property['featured_image']): ?>
                                    <img src="<?php echo htmlspecialchars($property['featured_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($property['title']); ?>">
                                <?php else: ?>
                                    <img src="images/default-property.jpg" alt="Property">
                                <?php endif; ?>
                                <div class="property-badge">
                                    <?php echo ucfirst(htmlspecialchars($property['property_type'])); ?>
                                </div>
                            </div>
                            
                            <div class="property-info">
                                <h3><?php echo htmlspecialchars($property['title']); ?></h3>
                                <p class="property-location">
                                    📍 <?php echo htmlspecialchars($property['location']) . ', ' . htmlspecialchars($property['city']); ?>
                                </p>
                                
                                <p class="property-description">
                                    <?php echo htmlspecialchars(substr($property['description'], 0, 100)) . '...'; ?>
                                </p>
                                
                                <div class="property-details">
                                    <?php if ($property['bedrooms'] > 0): ?>
                                        <span>🛏️ <?php echo $property['bedrooms']; ?> Beds</span>
                                    <?php endif; ?>
                                    <?php if ($property['bathrooms'] > 0): ?>
                                        <span>🚿 <?php echo $property['bathrooms']; ?> Baths</span>
                                    <?php endif; ?>
                                    <span>📐 <?php echo number_format($property['area_size']); ?> <?php echo strtoupper($property['area_unit']); ?></span>
                                </div>
                                
                                <div class="property-footer">
                                    <div class="property-price">
                                        <?php echo formatPrice($property['price']); ?>
                                    </div>
                                    <a href="property_details.php?id=<?php echo $property['id']; ?>" 
                                       class="btn btn-view">View Details</a>
                                </div>
                                
                                <div class="property-meta">
                                    <span class="property-time">⏰ <?php echo timeAgo($property['created_at']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
</body>
</html>