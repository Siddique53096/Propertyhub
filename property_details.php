<?php
// property_details.php - Property Details Page
require_once 'config.php';

// Get property ID
$property_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($property_id === 0) {
    setFlashMessage('error', 'Invalid property ID');
    redirect('properties.php');
}

// Fetch property details
$stmt = $con->prepare("SELECT p.*, u.full_name, u.email, u.phone, u.id as seller_id 
                       FROM properties p 
                       JOIN users u ON p.user_id = u.id 
                       WHERE p.id = ?");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('error', 'Property not found');
    redirect('properties.php');
}

$property = $result->fetch_assoc();
$stmt->close();

// Handle inquiry submission
$inquiry_sent = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token";
    } else {
        $message = sanitizeInput($_POST['message'] ?? '');
        $buyer_id = getCurrentUserId();
        
        if (empty($message) || strlen($message) < 10) {
            $errors[] = "Message must be at least 10 characters";
        }
        
        if (empty($errors)) {
            $stmt = $con->prepare("INSERT INTO inquiries (property_id, buyer_id, message, status) VALUES (?, ?, ?, 'pending')");
            $stmt->bind_param("iis", $property_id, $buyer_id, $message);
            
            if ($stmt->execute()) {
                $inquiry_sent = true;
                setFlashMessage('success', 'Your inquiry has been sent successfully!');
            } else {
                $errors[] = "Failed to send inquiry. Please try again.";
            }
            $stmt->close();
        }
    }
}

$csrf_token = generateCSRFToken();
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="ur">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($property['title']); ?> - <?php echo SITE_NAME; ?></title>
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

    <div class="property-details-container">
        <div class="container">
            <!-- Property Header -->
            <div class="property-header">
                <div class="property-title-section">
                    <h1><?php echo htmlspecialchars($property['title']); ?></h1>
                    <p class="property-location-large">
                        📍 <?php echo htmlspecialchars($property['location']) . ', ' . htmlspecialchars($property['city']); ?>
                    </p>
                </div>
                <div class="property-price-large">
                    <?php echo formatPrice($property['price']); ?>
                </div>
            </div>

            <div class="property-details-grid">
                <!-- Main Content -->
                <div class="property-main-content">
                    <!-- Property Image -->
                    <div class="property-detail-image">
                        <?php if ($property['featured_image']): ?>
                            <img src="<?php echo htmlspecialchars($property['featured_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($property['title']); ?>">
                        <?php else: ?>
                            <img src="images/default-property.jpg" alt="Property">
                        <?php endif; ?>
                        <div class="property-status-badge status-<?php echo $property['status']; ?>">
                            <?php echo ucfirst($property['status']); ?>
                        </div>
                    </div>

                    <!-- Property Info -->
                    <div class="property-info-section">
                        <h2>Property Overview</h2>
                        <div class="property-specs">
                            <div class="spec-item">
                                <span class="spec-icon">🏠</span>
                                <div class="spec-info">
                                    <p class="spec-label">Type</p>
                                    <p class="spec-value"><?php echo ucfirst(htmlspecialchars($property['property_type'])); ?></p>
                                </div>
                            </div>
                            
                            <div class="spec-item">
                                <span class="spec-icon">📐</span>
                                <div class="spec-info">
                                    <p class="spec-label">Area</p>
                                    <p class="spec-value"><?php echo number_format($property['area_size']); ?> <?php echo strtoupper($property['area_unit']); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($property['bedrooms'] > 0): ?>
                            <div class="spec-item">
                                <span class="spec-icon">🛏️</span>
                                <div class="spec-info">
                                    <p class="spec-label">Bedrooms</p>
                                    <p class="spec-value"><?php echo $property['bedrooms']; ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($property['bathrooms'] > 0): ?>
                            <div class="spec-item">
                                <span class="spec-icon">🚿</span>
                                <div class="spec-info">
                                    <p class="spec-label">Bathrooms</p>
                                    <p class="spec-value"><?php echo $property['bathrooms']; ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="property-description-section">
                        <h2>Property Description</h2>
                        <p><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>
                    </div>

                    <!-- Location Details -->
                    <?php if ($property['address']): ?>
                    <div class="property-location-section">
                        <h2>Location Details</h2>
                        <p class="address-detail">
                            <strong>Address:</strong> <?php echo nl2br(htmlspecialchars($property['address'])); ?>
                        </p>
                        <p class="address-detail">
                            <strong>City:</strong> <?php echo htmlspecialchars($property['city']); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="property-sidebar">
                    <!-- Seller Info Card -->
                    <div class="seller-card">
                        <h3>Contact Seller</h3>
                        <div class="seller-info">
                            <div class="seller-avatar">
                                <span class="avatar-icon">👤</span>
                            </div>
                            <div class="seller-details">
                                <h4><?php echo htmlspecialchars($property['full_name']); ?></h4>
                                <p>Property Owner</p>
                            </div>
                        </div>
                        
                        <div class="seller-contact">
                            <a href="tel:<?php echo htmlspecialchars($property['phone']); ?>" class="contact-btn">
                                📱 <?php echo htmlspecialchars($property['phone']); ?>
                            </a>
                            <a href="mailto:<?php echo htmlspecialchars($property['email']); ?>" class="contact-btn">
                                📧 <?php echo htmlspecialchars($property['email']); ?>
                            </a>
                        </div>

                        <!-- Inquiry Form -->
                        <?php if (isLoggedIn() && getCurrentUserId() != $property['seller_id']): ?>
                            <?php if ($inquiry_sent): ?>
                                <div class="inquiry-success">
                                    ✅ Your inquiry has been sent!
                                </div>
                            <?php else: ?>
                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-error">
                                        <ul>
                                            <?php foreach ($errors as $error): ?>
                                                <li><?php echo htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" class="inquiry-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <h4>Send Inquiry</h4>
                                    <textarea name="message" rows="4" placeholder="I'm interested in this property..." required></textarea>
                                    <button type="submit" class="btn btn-primary btn-full">Send Message</button>
                                </form>
                            <?php endif; ?>
                        <?php elseif (!isLoggedIn()): ?>
                            <div class="login-prompt">
                                <p>Please login to contact the seller</p>
                                <a href="login.php?redirect=property_details.php?id=<?php echo $property_id; ?>" class="btn btn-primary btn-full">Login</a>
                            </div>
                        <?php else: ?>
                            <div class="own-property">
                                <p>This is your property</p>
                                <a href="edit_property.php?id=<?php echo $property_id; ?>" class="btn btn-secondary btn-full">Edit Property</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Property Stats -->
                    <div class="property-stats-card">
                        <h3>Property Statistics</h3>
                        <div class="stat-item">
                            <span>📅 Listed</span>
                            <span><?php echo date('d M Y', strtotime($property['created_at'])); ?></span>
                        </div>
                        <div class="stat-item">
                            <span>⏰ Posted</span>
                            <span><?php echo timeAgo($property['created_at']); ?></span>
                        </div>
                        <div class="stat-item">
                            <span>🏷️ Status</span>
                            <span class="status-badge-small status-<?php echo $property['status']; ?>">
                                <?php echo ucfirst($property['status']); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Share Property -->
                    <div class="share-card">
                        <h3>Share Property</h3>
                        <div class="share-buttons">
                            <a href="https://wa.me/?text=Check out this property: <?php echo urlencode(SITE_URL . '/property_details.php?id=' . $property_id); ?>" 
                               target="_blank" class="share-btn whatsapp">
                                WhatsApp
                            </a>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . '/property_details.php?id=' . $property_id); ?>" 
                               target="_blank" class="share-btn facebook">
                                Facebook
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Similar Properties -->
            <div class="similar-properties-section">
                <h2>Similar Properties</h2>
                <?php
                // Fetch similar properties
                $stmt = $con->prepare("SELECT p.*, u.full_name FROM properties p 
                                       JOIN users u ON p.user_id = u.id 
                                       WHERE p.city = ? AND p.property_type = ? AND p.id != ? AND p.status = 'available' 
                                       LIMIT 3");
                $stmt->bind_param("ssi", $property['city'], $property['property_type'], $property_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $similar_properties = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                ?>
                
                <?php if (!empty($similar_properties)): ?>
                    <div class="property-grid">
                        <?php foreach ($similar_properties as $sim_prop): ?>
                            <div class="property-card">
                                <div class="property-image">
                                    <?php if ($sim_prop['featured_image']): ?>
                                        <img src="<?php echo htmlspecialchars($sim_prop['featured_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($sim_prop['title']); ?>">
                                    <?php else: ?>
                                        <img src="images/default-property.jpg" alt="Property">
                                    <?php endif; ?>
                                    <div class="property-badge">
                                        <?php echo ucfirst(htmlspecialchars($sim_prop['property_type'])); ?>
                                    </div>
                                </div>
                                
                                <div class="property-info">
                                    <h3><?php echo htmlspecialchars($sim_prop['title']); ?></h3>
                                    <p class="property-location">
                                        📍 <?php echo htmlspecialchars($sim_prop['location']) . ', ' . htmlspecialchars($sim_prop['city']); ?>
                                    </p>
                                    
                                    <div class="property-footer">
                                        <div class="property-price">
                                            <?php echo formatPrice($sim_prop['price']); ?>
                                        </div>
                                        <a href="property_details.php?id=<?php echo $sim_prop['id']; ?>" 
                                           class="btn btn-view">View</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-secondary);">No similar properties found</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <style>
    .property-details-container {
        padding: 40px 20px;
        min-height: calc(100vh - 200px);
    }

    .property-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 40px;
        padding-bottom: 30px;
        border-bottom: 2px solid var(--border-color);
    }

    .property-title-section h1 {
        font-size: 36px;
        margin-bottom: 15px;
        color: var(--text-primary);
    }

    .property-location-large {
        font-size: 18px;
        color: var(--text-secondary);
    }

    .property-price-large {
        font-size: 42px;
        font-weight: 800;
        background: var(--primary-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .property-details-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 40px;
    }

    .property-detail-image {
        position: relative;
        height: 500px;
        border-radius: var(--border-radius);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .property-detail-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .property-status-badge {
        position: absolute;
        top: 20px;
        right: 20px;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 14px;
    }

    .property-info-section,
    .property-description-section,
    .property-location-section {
        background: var(--bg-white);
        padding: 30px;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        margin-bottom: 30px;
    }

    .property-info-section h2,
    .property-description-section h2,
    .property-location-section h2 {
        font-size: 24px;
        margin-bottom: 25px;
    }

    .property-specs {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .spec-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 20px;
        background: var(--bg-light);
        border-radius: 10px;
    }

    .spec-icon {
        font-size: 32px;
    }

    .spec-label {
        font-size: 13px;
        color: var(--text-secondary);
        margin-bottom: 5px;
    }

    .spec-value {
        font-size: 18px;
        font-weight: 700;
    }

    .property-description-section p {
        line-height: 1.8;
        color: var(--text-secondary);
    }

    .address-detail {
        margin-bottom: 15px;
        line-height: 1.6;
    }

    .seller-card,
    .property-stats-card,
    .share-card {
        background: var(--bg-white);
        padding: 30px;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        margin-bottom: 25px;
    }

    .seller-card h3,
    .property-stats-card h3,
    .share-card h3 {
        font-size: 20px;
        margin-bottom: 20px;
    }

    .seller-info {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 25px;
        padding-bottom: 25px;
        border-bottom: 1px solid var(--border-color);
    }

    .seller-avatar {
        width: 60px;
        height: 60px;
        background: var(--primary-gradient);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .avatar-icon {
        font-size: 30px;
    }

    .seller-details h4 {
        font-size: 18px;
        margin-bottom: 5px;
    }

    .seller-details p {
        font-size: 14px;
        color: var(--text-secondary);
    }

    .seller-contact {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 25px;
    }

    .contact-btn {
        padding: 12px;
        background: var(--bg-light);
        border-radius: 8px;
        text-align: center;
        transition: var(--transition);
        font-weight: 500;
    }

    .contact-btn:hover {
        background: var(--primary-gradient);
        color: white;
    }

    .inquiry-form h4 {
        font-size: 16px;
        margin-bottom: 15px;
    }

    .inquiry-form textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        margin-bottom: 15px;
        font-family: inherit;
    }

    .inquiry-success {
        padding: 15px;
        background: #d1fae5;
        color: #065f46;
        border-radius: 8px;
        text-align: center;
        font-weight: 600;
    }

    .login-prompt,
    .own-property {
        text-align: center;
        padding: 20px 0;
    }

    .login-prompt p,
    .own-property p {
        margin-bottom: 15px;
        color: var(--text-secondary);
    }

    .stat-item {
        display: flex;
        justify-content: space-between;
        padding: 15px 0;
        border-bottom: 1px solid var(--border-color);
    }

    .stat-item:last-child {
        border-bottom: none;
    }

    .status-badge-small {
        padding: 4px 10px;
        border-radius: 5px;
        font-size: 12px;
        font-weight: 600;
    }

    .share-buttons {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .share-btn {
        padding: 12px;
        border-radius: 8px;
        text-align: center;
        font-weight: 600;
        color: white;
        transition: var(--transition);
    }

    .share-btn.whatsapp {
        background: #25d366;
    }

    .share-btn.facebook {
        background: #1877f2;
    }

    .share-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .similar-properties-section {
        margin-top: 60px;
    }

    .similar-properties-section h2 {
        font-size: 32px;
        margin-bottom: 30px;
        text-align: center;
    }

    @media (max-width: 768px) {
        .property-details-grid {
            grid-template-columns: 1fr;
        }

        .property-header {
            flex-direction: column;
            gap: 20px;
        }

        .property-detail-image {
            height: 300px;
        }

        .property-specs {
            grid-template-columns: 1fr;
        }
    }
    </style>
</body>
</html>