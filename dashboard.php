<?php
// dashboard.php - User Dashboard
require_once 'config.php';

// Check if user is logged in
requireLogin();

$user_id = getCurrentUserId();
$flash = getFlashMessage();

// Get user info
$stmt = $con->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get user's properties
$my_properties = [];
$stmt = $con->prepare("SELECT * FROM properties WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$my_properties = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$total_properties = count($my_properties);
$available_properties = 0;
$sold_properties = 0;
$pending_properties = 0;

foreach ($my_properties as $prop) {
    if ($prop['status'] === 'available') $available_properties++;
    elseif ($prop['status'] === 'sold') $sold_properties++;
    elseif ($prop['status'] === 'pending') $pending_properties++;
}

// Get recent inquiries
$inquiries = [];
$stmt = $con->prepare("SELECT i.*, p.title as property_title, u.full_name, u.email, u.phone 
                       FROM inquiries i 
                       JOIN properties p ON i.property_id = p.id 
                       JOIN users u ON i.buyer_id = u.id 
                       WHERE p.user_id = ? 
                       ORDER BY i.created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$inquiries = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="ur">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
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

    <div class="dashboard-container">
        <div class="container">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div>
                    <h1>Welcome, <?php echo htmlspecialchars($user['full_name']); ?>! 👋</h1>
                    <p>Manage your properties and track your activity</p>
                </div>
                <a href="add_property.php" class="btn btn-primary">+ Add New Property</a>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">🏠</div>
                    <div class="stat-info">
                        <h3><?php echo $total_properties; ?></h3>
                        <p>Total Properties</p>
                    </div>
                </div>
                
                <div class="stat-card stat-success">
                    <div class="stat-icon">✅</div>
                    <div class="stat-info">
                        <h3><?php echo $available_properties; ?></h3>
                        <p>Available</p>
                    </div>
                </div>
                
                <div class="stat-card stat-warning">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-info">
                        <h3><?php echo $pending_properties; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                
                <div class="stat-card stat-info">
                    <div class="stat-icon">✔️</div>
                    <div class="stat-info">
                        <h3><?php echo $sold_properties; ?></h3>
                        <p>Sold</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <div class="actions-grid">
                    <a href="add_property.php" class="action-card">
                        <span class="action-icon">➕</span>
                        <h3>Add Property</h3>
                        <p>List a new property for sale</p>
                    </a>
                    
                    <a href="my_properties.php" class="action-card">
                        <span class="action-icon">📋</span>
                        <h3>My Properties</h3>
                        <p>View and manage your listings</p>
                    </a>
                    
                    <a href="properties.php" class="action-card">
                        <span class="action-icon">🔍</span>
                        <h3>Browse Properties</h3>
                        <p>Find properties to buy</p>
                    </a>
                    
                    <a href="profile.php" class="action-card">
                        <span class="action-icon">⚙️</span>
                        <h3>Profile Settings</h3>
                        <p>Update your account info</p>
                    </a>
                </div>
            </div>

            <!-- Recent Properties -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Your Recent Properties</h2>
                    <a href="my_properties.php" class="btn btn-secondary">View All</a>
                </div>
                
                <?php if (empty($my_properties)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🏠</div>
                        <h3>No Properties Yet</h3>
                        <p>Start by adding your first property listing</p>
                        <a href="add_property.php" class="btn btn-primary">Add Property</a>
                    </div>
                <?php else: ?>
                    <div class="property-grid">
                        <?php 
                        $recent = array_slice($my_properties, 0, 3);
                        foreach ($recent as $property): 
                        ?>
                            <div class="property-card">
                                <div class="property-image">
                                    <?php if ($property['featured_image']): ?>
                                        <img src="<?php echo htmlspecialchars($property['featured_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($property['title']); ?>">
                                    <?php else: ?>
                                        <img src="images/default-property.jpg" alt="Property">
                                    <?php endif; ?>
                                    <div class="property-status status-<?php echo $property['status']; ?>">
                                        <?php echo ucfirst($property['status']); ?>
                                    </div>
                                </div>
                                
                                <div class="property-info">
                                    <h3><?php echo htmlspecialchars($property['title']); ?></h3>
                                    <p class="property-location">
                                        📍 <?php echo htmlspecialchars($property['location']) . ', ' . htmlspecialchars($property['city']); ?>
                                    </p>
                                    
                                    <div class="property-footer">
                                        <div class="property-price">
                                            <?php echo formatPrice($property['price']); ?>
                                        </div>
                                        <div class="property-actions">
                                            <a href="property_details.php?id=<?php echo $property['id']; ?>" 
                                               class="btn btn-sm">View</a>
                                            <a href="edit_property.php?id=<?php echo $property['id']; ?>" 
                                               class="btn btn-sm btn-secondary">Edit</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Inquiries -->
            <?php if (!empty($inquiries)): ?>
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Recent Inquiries</h2>
                    <a href="inquiries.php" class="btn btn-secondary">View All</a>
                </div>
                
                <div class="inquiries-list">
                    <?php foreach ($inquiries as $inquiry): ?>
                        <div class="inquiry-item">
                            <div class="inquiry-info">
                                <h4><?php echo htmlspecialchars($inquiry['full_name']); ?></h4>
                                <p class="inquiry-property">
                                    Interested in: <strong><?php echo htmlspecialchars($inquiry['property_title']); ?></strong>
                                </p>
                                <p class="inquiry-message"><?php echo htmlspecialchars($inquiry['message']); ?></p>
                                <div class="inquiry-contact">
                                    <span>📧 <?php echo htmlspecialchars($inquiry['email']); ?></span>
                                    <span>📱 <?php echo htmlspecialchars($inquiry['phone']); ?></span>
                                </div>
                            </div>
                            <div class="inquiry-meta">
                                <span class="inquiry-time"><?php echo timeAgo($inquiry['created_at']); ?></span>
                                <span class="inquiry-status status-<?php echo $inquiry['status']; ?>">
                                    <?php echo ucfirst($inquiry['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
</body>
</html>