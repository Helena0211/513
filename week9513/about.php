<?php
require_once 'config/config.php';

// Load statistics from JSON files instead of database
function getWorkshopsCount() {
    try {
        $products_json = file_get_contents('data/products.json');
        $products_data = json_decode($products_json, true);
        return count($products_data['products'] ?? []);
    } catch (Exception $e) {
        return 12; // Fallback number
    }
}

function getInstructorsCount() {
    // Count unique instructors from products data
    try {
        $products_json = file_get_contents('data/products.json');
        $products_data = json_decode($products_json, true);
        $instructors = [];
        
        foreach ($products_data['products'] ?? [] as $product) {
            if (isset($product['instructor'])) {
                $instructors[$product['instructor']] = true;
            }
        }
        return count($instructors);
    } catch (Exception $e) {
        return 8; // Fallback number
    }
}

function getBookingsCount() {
    // Use session cart data or fallback
    $total_bookings = 0;
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $total_bookings += $item['quantity'];
        }
    }
    return $total_bookings > 0 ? $total_bookings : 156; // Fallback number
}

function getCustomersCount() {
    // Estimate based on common conversion rates
    return 89; // Fallback number
}

$total_workshops = getWorkshopsCount();
$total_instructors = getInstructorsCount();
$total_bookings = getBookingsCount();
$happy_customers = getCustomersCount();
?>

<?php include 'includes/header.php'; ?>

<div class="container">

    <!-- Hero Section -->
    <section class="hero-section text-center py-5 mb-5 about-php-fix">
        <div class="container">
            <h1 class="display-4 fw-bold text-primary mb-4">About SkillCraft Workshops</h1>
            <p class="lead mb-4">Connecting passionate instructors with curious learners through hands-on creative experiences.</p>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <p class="text-muted">SkillCraft was born from a simple belief: everyone has creative potential waiting to be unlocked. We provide the platform, expert instructors, and supportive community to help you discover and develop new skills.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Mission & Vision -->
    <section class="mission-section py-5 bg-light rounded mb-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="mb-4">Our Mission</h2>
                    <p class="mb-4">To make quality hands-on education accessible to everyone by connecting expert instructors with enthusiastic learners in a supportive, community-driven environment.</p>
                    
                    <h2 class="mb-4">Our Vision</h2>
                    <p class="mb-4">We envision a world where lifelong learning is joyful, accessible, and community-centered—where anyone can discover their creative potential and share their skills with others.</p>
                </div>
                <div class="col-lg-6 text-center">
                    <div class="about-image-placeholder bg-primary text-white rounded p-5">
                        <i class="fas fa-hands-helping fa-5x mb-3"></i>
                        <h4>Community & Learning</h4>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics -->
    <section class="stats-section py-5 mb-5">
        <div class="container">
            <h2 class="text-center mb-5">Our Impact</h2>
            <div class="row text-center">
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card">
                        <i class="fas fa-graduation-cap fa-3x text-primary mb-3"></i>
                        <h3 class="text-primary fw-bold"><?php echo $total_workshops; ?>+</h3>
                        <p class="text-muted">Workshops Offered</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card">
                        <i class="fas fa-chalkboard-teacher fa-3x text-success mb-3"></i>
                        <h3 class="text-success fw-bold"><?php echo $total_instructors; ?>+</h3>
                        <p class="text-muted">Expert Instructors</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card">
                        <i class="fas fa-shopping-cart fa-3x text-info mb-3"></i>
                        <h3 class="text-info fw-bold"><?php echo $total_bookings; ?>+</h3>
                        <p class="text-muted">Successful Bookings</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card">
                        <i class="fas fa-smile fa-3x text-warning mb-3"></i>
                        <h3 class="text-warning fw-bold"><?php echo $happy_customers; ?>+</h3>
                        <p class="text-muted">Happy Learners</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Values -->
    <section class="values-section py-5 mb-5">
        <div class="container">
            <h2 class="text-center mb-5">Our Values</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card value-card h-100 text-center">
                        <div class="card-body">
                            <div class="value-icon bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-hands fa-2x"></i>
                            </div>
                            <h4>Hands-On Learning</h4>
                            <p class="text-muted">We believe the best way to learn is by doing. All our workshops emphasize practical, hands-on experience over theoretical knowledge.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card value-card h-100 text-center">
                        <div class="card-body">
                            <div class="value-icon bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                            <h4>Community First</h4>
                            <p class="text-muted">Learning is better together. We foster a supportive community where learners and instructors connect, share, and grow.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card value-card h-100 text-center">
                        <div class="card-body">
                            <div class="value-icon bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-star fa-2x"></i>
                            </div>
                            <h4>Quality Focus</h4>
                            <p class="text-muted">We carefully vet all instructors and workshops to ensure high-quality, engaging learning experiences for every participant.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="process-section py-5 bg-light rounded mb-5">
        <div class="container">
            <h2 class="text-center mb-5">How SkillCraft Works</h2>
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="process-step text-center">
                        <div class="step-number bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3">1</div>
                        <h5>Browse Workshops</h5>
                        <p class="text-muted">Explore our diverse range of workshops across various creative disciplines and skill levels.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="process-step text-center">
                        <div class="step-number bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3">2</div>
                        <h5>Book Your Spot</h5>
                        <p class="text-muted">Reserve your place in any workshop with our easy online booking system.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="process-step text-center">
                        <div class="step-number bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3">3</div>
                        <h5>Learn & Create</h5>
                        <p class="text-muted">Join the workshop and learn new skills through hands-on experience with expert guidance.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="process-step text-center">
                        <div class="step-number bg-warning text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3">4</div>
                        <h5>Join Community</h5>
                        <p class="text-muted">Share your creations, ask questions, and connect with fellow learners in our community.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="team-section py-5 mb-5">
        <div class="container">
            <h2 class="text-center mb-5">Meet Our Team</h2>
            <div class="row justify-content-center">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card team-card text-center">
                        <div class="card-body">
                            <div class="team-avatar bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 mx-auto" style="width: 100px; height: 100px;">
                                <i class="fas fa-user fa-2x"></i>
                            </div>
                            <h5>Helena Johnson</h5>
                            <p class="text-primary mb-2">Founder & Lead Instructor</p>
                            <p class="text-muted">Professional ceramic artist with 8 years of teaching experience. Passionate about making creative education accessible to all.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card team-card text-center">
                        <div class="card-body">
                            <div class="team-avatar bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 mx-auto" style="width: 100px; height: 100px;">
                                <i class="fas fa-user fa-2x"></i>
                            </div>
                            <h5>Mike Anderson</h5>
                            <p class="text-success mb-2">Woodworking Expert</p>
                            <p class="text-muted">Master woodworker specializing in traditional furniture making and restoration with over 15 years of experience.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card team-card text-center">
                        <div class="card-body">
                            <div class="team-avatar bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 mx-auto" style="width: 100px; height: 100px;">
                                <i class="fas fa-user fa-2x"></i>
                            </div>
                            <h5>Sarah Chen</h5>
                            <p class="text-info mb-2">Digital Design Instructor</p>
                            <p class="text-muted">UI/UX designer and digital artist with 6 years of industry experience, passionate about teaching design thinking.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

   <!-- Location & Map Section -->
<section class="map-section py-5 mb-5">
    <div class="container">
        <h2 class="text-center mb-4">Find Our Training Center</h2>
        
        <div class="row">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h4 class="card-title mb-4">IMC Wetherill Park Martial Arts Centre</h4>
                        
                        
                        <div class="mb-4">
                            <h6><i class="fas fa-map-marker-alt text-primary me-2"></i> Address</h6>
                            <p class="text-muted mb-0">Unit 4/25 Powells Rd,<br>Wetherill Park NSW 2164,<br>Australia</p>
                        </div>
                        
                        
                        <div class="mb-4">
                            <h6><i class="fas fa-clock text-success me-2"></i> Training Hours</h6>
                            <p class="text-muted mb-0">
                                Mon-Fri: 6:00 AM - 10:00 PM<br>
                                Saturday: 8:00 AM - 8:00 PM<br>
                                Sunday: 8:00 AM - 6:00 PM
                            </p>
                        </div>
                    
                         <div class="mb-4">
                            <h6><i class="fas fa-phone text-info me-2"></i> Contact</h6>
                            <p class="text-muted mb-0">
                                Phone: (02) 9756 1234<br>
                                Email: info@imcmartialarts.com
                            </p>
                        </div>
                        
                        <div class="mb-4">
                            <h6><i class="fas fa-bus text-warning me-2"></i> Transport</h6>
                            <p class="text-muted mb-0">
                                • Bus: 805, 806, 810<br>
                                • Free onsite parking<br>
                                • Wheelchair accessible
                            </p>
                        </div>
                        
                 
                        <div>
                            <h6><i class="fas fa-check-circle text-primary me-2"></i> Facilities</h6>
                            <p class="text-muted mb-0">
                                Professional mats • Changing rooms • Showers<br>
                                Equipment hire • Pro shop • Wi-Fi
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body p-0">
                      
                        <div class="ratio ratio-16x9">
                            <iframe 
                                src="https://map.baidu.com/poi/IMC%20Wetherill%20Park%20Martial%20Arts%20Centre/@16796293.528097983,-3981741.316210375,12.26z?uid=b655125ff13d905e72455b95&ugc_type=3&ugc_ver=1&device_ratio=2&compat=1&en_uid=b655125ff13d905e72455b95&pcevaname=pc4.1&querytype=detailConInfo&da_src=shareurl" 
                                width="100%" 
                                height="100%" 
                                style="border:0;" 
                                allowfullscreen="" 
                                loading="lazy" 
                                referrerpolicy="no-referrer-when-downgrade"
                                title="IMC Wetherill Park Martial Arts Centre Map">
                            </iframe>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent text-center">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Click map for directions and street view
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

</div>

<?php include 'includes/footer.php'; ?>

<style>
.hero-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.value-card, .team-card {
    border: none;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.value-card:hover, .team-card:hover {
    transform: translateY(-5px);
}

.value-icon, .team-avatar, .step-number {
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.step-number {
    font-size: 1.5rem;
    font-weight: bold;
}

.about-image-placeholder {
    border-radius: 15px;
}

.stat-card {
    padding: 20px 0;
}
    .map-section .card {
    border: none;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border-radius: 10px;
}

.map-section .ratio {
    border-radius: 8px;
    overflow: hidden;
}

.map-section .card-footer {
    border-top: 1px solid #eee;
    padding: 15px;
}

.map-section h4 {
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
}

.map-section h6 {
    color: #34495e;
    margin-bottom: 8px;
}
</style>