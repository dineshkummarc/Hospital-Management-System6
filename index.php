<?php
require_once 'config.php';
// Redirect logged-in users
if (!empty($_SESSION['user_id'])) {
    redirect(APP_URL . '/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Smart Hospital Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<style>
.landing-nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
  padding: 16px 0;
  transition: all .3s ease;
}
.landing-nav.scrolled {
  background: rgba(15,23,42,.95);
  backdrop-filter: blur(12px);
  padding: 10px 0;
  box-shadow: 0 4px 20px rgba(0,0,0,.3);
}
.nav-logo { font-family: var(--font-display); font-weight: 800; font-size: 1.3rem; color: #fff; text-decoration: none; display: flex; align-items: center; gap: 10px; }
.nav-logo-icon { width: 38px; height: 38px; background: linear-gradient(135deg,var(--primary),var(--accent)); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.1rem; }
.nav-links { display: flex; align-items: center; gap: 32px; list-style: none; margin: 0; padding: 0; }
.nav-links a { color: rgba(255,255,255,.8); text-decoration: none; font-size: .875rem; font-weight: 500; transition: color .2s; }
.nav-links a:hover { color: #fff; }
.section-badge { display: inline-flex; align-items: center; gap: 8px; background: var(--primary-light); color: var(--primary); padding: 6px 14px; border-radius: 20px; font-size: .8rem; font-weight: 600; margin-bottom: 12px; }
.testimonial-card { background: #fff; border-radius: 16px; padding: 28px; box-shadow: var(--shadow-sm); border: 1px solid #F1F5F9; }
.testimonial-stars { color: #F59E0B; margin-bottom: 12px; }
.faq-item { border: 1.5px solid #E2E8F0; border-radius: 12px; margin-bottom: 10px; overflow: hidden; }
.faq-question { padding: 16px 20px; cursor: pointer; font-weight: 600; font-size: .9rem; display: flex; justify-content: space-between; align-items: center; }
.faq-answer { display: none; padding: 0 20px 16px; color: #475569; font-size: .875rem; line-height: 1.7; }
.faq-item.open .faq-answer { display: block; }
.faq-item.open .faq-icon { transform: rotate(45deg); }
.faq-icon { transition: transform .2s; }
.emergency-banner { background: linear-gradient(135deg, #EF4444, #DC2626); color: #fff; padding: 12px 0; text-align: center; font-size: .875rem; font-weight: 600; }
footer { background: #0F172A; color: #94A3B8; }
.footer-heading { color: #fff; font-weight: 700; margin-bottom: 16px; }
.footer-links { list-style: none; padding: 0; }
.footer-links li { margin-bottom: 8px; }
.footer-links a { color: #64748B; text-decoration: none; font-size: .875rem; transition: color .2s; }
.footer-links a:hover { color: #fff; }
</style>
</head>
<body style="margin:0">

<!-- Emergency Banner -->
<div class="emergency-banner">
  <i class="bi bi-telephone-fill me-2"></i>
  Emergency Helpline: <strong>+91 98765 43210</strong> &nbsp;|&nbsp; Available 24/7
</div>

<!-- Navbar -->
<nav class="landing-nav" id="main-nav">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between">
      <a href="#" class="nav-logo">
        <div class="nav-logo-icon"><i class="bi bi-heart-pulse-fill"></i></div>
        Smart Hospital
      </a>
      <ul class="nav-links d-none d-lg-flex">
        <li><a href="#about">About</a></li>
        <li><a href="#services">Services</a></li>
        <li><a href="#doctors">Doctors</a></li>
        <li><a href="#testimonials">Reviews</a></li>
        <li><a href="#contact">Contact</a></li>
      </ul>
      <div class="d-flex gap-2">
        <a href="login.php" style="padding:9px 20px;border:1.5px solid rgba(255,255,255,.3);border-radius:10px;color:#fff;text-decoration:none;font-size:.875rem;font-weight:600">Login</a>
        <a href="register.php" class="btn-primary-custom" style="padding:9px 20px">Get Started</a>
      </div>
    </div>
  </div>
</nav>

<!-- Hero Section -->
<section class="hero-section" id="home" style="padding-top:90px">
  <div class="container" style="position:relative;z-index:1">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <div class="hero-badge"><i class="bi bi-shield-check"></i> Trusted by 50,000+ Patients</div>
        <h1 class="hero-title">Your Health is Our <span>Top Priority</span></h1>
        <p class="hero-subtitle">Experience world-class healthcare with our advanced hospital management system. Book appointments, access records, and connect with expert doctors — all in one place.</p>
        <div class="d-flex gap-3 flex-wrap mb-48px" style="margin-bottom:48px">
          <a href="register.php" class="btn-primary-custom" style="padding:14px 28px;font-size:1rem">
            <i class="bi bi-calendar-plus"></i> Book Appointment
          </a>
          <a href="#services" style="padding:13px 28px;border:2px solid rgba(255,255,255,.25);border-radius:10px;color:#fff;text-decoration:none;font-size:.95rem;font-weight:600;display:inline-flex;align-items:center;gap:8px">
            <i class="bi bi-play-circle"></i> Learn More
          </a>
        </div>
        <div class="row g-3" style="max-width:420px">
          <div class="col-4 hero-stat">
            <div class="hero-stat-value" data-count="500">0</div>
            <div class="hero-stat-label">Expert Doctors</div>
          </div>
          <div class="col-4 hero-stat">
            <div class="hero-stat-value" data-count="50000">0</div>
            <div class="hero-stat-label">Patients Served</div>
          </div>
          <div class="col-4 hero-stat">
            <div class="hero-stat-value" data-count="25">0</div>
            <div class="hero-stat-label">Departments</div>
          </div>
        </div>
      </div>
      <div class="col-lg-6 d-none d-lg-block">
        <div style="background:rgba(255,255,255,.08);border-radius:28px;padding:32px;backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.12)">
          <!-- Quick Appointment Form -->
          <h4 style="color:#fff;font-family:var(--font-display);margin-bottom:20px;font-size:1.2rem"><i class="bi bi-calendar-check me-2" style="color:#60A5FA"></i>Quick Appointment</h4>
          <form action="register.php" method="get">
            <div class="mb-3">
              <select class="form-control-custom" style="background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.2);color:#fff" name="dept">
                <option value="">Select Department</option>
                <?php
                try {
                  $db = getDB();
                  $depts = $db->query("SELECT name FROM departments WHERE status='active'")->fetchAll();
                  foreach($depts as $d) echo "<option>" . e($d['name']) . "</option>";
                } catch(Exception $e) {
                  echo "<option>Cardiology</option><option>Neurology</option><option>Orthopedics</option>";
                }
                ?>
              </select>
            </div>
            <div class="mb-3">
              <input type="date" class="form-control-custom" style="background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.2);color:#fff" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="mb-3">
              <input type="text" placeholder="Your Name" class="form-control-custom" style="background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.2);color:#fff">
            </div>
            <button type="submit" class="btn-primary-custom w-100" style="padding:13px;font-size:.95rem;justify-content:center">
              <i class="bi bi-arrow-right-circle"></i> Book Now
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- About Section -->
<section id="about" style="padding:100px 0;background:#fff">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <div class="section-badge"><i class="bi bi-hospital"></i> About Us</div>
        <h2 class="section-title mb-3">Advanced Healthcare <br>for Everyone</h2>
        <p style="color:#475569;font-size:.95rem;line-height:1.8">Smart Hospital has been providing exceptional healthcare services for over 25 years. Our state-of-the-art facilities, combined with compassionate care from expert medical professionals, ensure the best outcomes for our patients.</p>
        <div class="row g-3 mt-2">
          <?php foreach([['bi-award','Accredited Hospital'],['bi-clock','24/7 Emergency'],['bi-shield-check','Safe & Secure'],['bi-people','Expert Team']] as [$icon,$text]): ?>
          <div class="col-6">
            <div style="display:flex;align-items:center;gap:10px;padding:14px;border-radius:12px;background:var(--primary-light);border:1px solid #BFDBFE">
              <i class="bi <?= $icon ?>" style="color:var(--primary);font-size:1.2rem"></i>
              <span style="font-weight:600;font-size:.875rem;color:#1E40AF"><?= $text ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="row g-3">
          <?php foreach([
            ['bi-heart-pulse','1,200+','Successful Surgeries','green'],
            ['bi-person-badge','500+','Expert Doctors','blue'],
            ['bi-building','50+','Specialty Wards','purple'],
            ['bi-star-fill','98%','Patient Satisfaction','orange'],
          ] as [$icon,$val,$label,$color]): ?>
          <div class="col-6">
            <div class="stat-card <?= $color ?> text-center">
              <div class="stat-icon <?= $color ?>" style="margin:0 auto 12px"><i class="bi <?= $icon ?>"></i></div>
              <div class="stat-value"><?= $val ?></div>
              <div class="stat-label"><?= $label ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Services Section -->
<section id="services" style="padding:100px 0;background:var(--bg)">
  <div class="container">
    <div class="text-center mb-60px" style="margin-bottom:60px">
      <div class="section-badge" style="justify-content:center"><i class="bi bi-grid-3x3-gap"></i> Our Services</div>
      <h2 class="section-title">World-Class Medical Services</h2>
      <p style="color:#64748B;max-width:560px;margin:12px auto">Comprehensive healthcare services delivered with precision and compassion by our experienced medical team.</p>
    </div>
    <div class="row g-4">
      <?php
      $services = [
        ['bi-heart-pulse','Cardiology','Advanced cardiac care with cutting-edge diagnostic and treatment technologies.'],
        ['bi-activity','Neurology','Expert care for brain, spine and nervous system disorders.'],
        ['bi-clipboard2-pulse','Orthopedics','Complete bone and joint care including minimally invasive surgeries.'],
        ['bi-emoji-smile','Pediatrics','Specialized healthcare for children from birth through adolescence.'],
        ['bi-droplet','Dermatology','Treatment of skin disorders with modern dermatological techniques.'],
        ['bi-eye','Ophthalmology','Complete eye care services including LASIK and cataract surgery.'],
        ['bi-gender-female','Gynecology','Comprehensive women\'s health services and maternity care.'],
        ['bi-camera','Radiology','Advanced imaging services including MRI, CT scan and ultrasound.'],
      ];
      foreach($services as [$icon,$name,$desc]):
      ?>
      <div class="col-lg-3 col-md-4 col-sm-6">
        <div class="service-card h-100">
          <div class="service-icon"><i class="bi <?= $icon ?>"></i></div>
          <h5 style="font-weight:700;font-size:.95rem;margin-bottom:8px"><?= $name ?></h5>
          <p style="color:#64748B;font-size:.82rem;margin:0;line-height:1.6"><?= $desc ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Doctors Section -->
<section id="doctors" style="padding:100px 0;background:#fff">
  <div class="container">
    <div class="text-center mb-60px" style="margin-bottom:60px">
      <div class="section-badge" style="justify-content:center"><i class="bi bi-people"></i> Our Team</div>
      <h2 class="section-title">Meet Our Expert Doctors</h2>
    </div>
    <div class="row g-4">
      <?php
      try {
        $db = getDB();
        $docs = $db->query("SELECT u.name, d.specialization, d.experience_years, d.consultation_fee, dep.name AS dept FROM doctors d JOIN users u ON d.user_id=u.id JOIN departments dep ON d.department_id=dep.id WHERE d.status='active' LIMIT 4")->fetchAll();
      } catch(Exception $e) { $docs = []; }
      $fallback = [
        ['Dr. Rajesh Kumar','Interventional Cardiologist',15,800,'Cardiology'],
        ['Dr. Priya Sharma','Neurologist',12,700,'Neurology'],
        ['Dr. Anil Verma','Orthopedic Surgeon',10,600,'Orthopedics'],
        ['Dr. Sunita Patel','Dermatologist',8,500,'Dermatology'],
      ];
      if (empty($docs)) $docs = array_map(fn($d) => ['name'=>$d[0],'specialization'=>$d[1],'experience_years'=>$d[2],'consultation_fee'=>$d[3],'dept'=>$d[4]], $fallback);
      foreach($docs as $doc):
      ?>
      <div class="col-lg-3 col-md-6">
        <div class="doctor-card">
          <div class="doctor-card-img"><i class="bi bi-person-circle"></i></div>
          <div class="doctor-card-body">
            <div class="doctor-card-name"><?= e($doc['name']) ?></div>
            <div class="doctor-card-spec"><?= e($doc['specialization'] ?? '') ?></div>
            <div class="doctor-card-exp"><i class="bi bi-briefcase me-1"></i><?= e($doc['experience_years']) ?> yrs exp &nbsp;·&nbsp; <i class="bi bi-currency-rupee"></i><?= e($doc['consultation_fee']) ?></div>
            <div style="display:flex;gap:6px;margin-top:12px">
              <a href="register.php" class="btn-primary-custom" style="flex:1;justify-content:center;padding:8px;font-size:.8rem">Book</a>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Testimonials -->
<section id="testimonials" style="padding:100px 0;background:var(--bg)">
  <div class="container">
    <div class="text-center mb-60px" style="margin-bottom:60px">
      <div class="section-badge" style="justify-content:center"><i class="bi bi-chat-quote"></i> Testimonials</div>
      <h2 class="section-title">What Our Patients Say</h2>
    </div>
    <div class="row g-4">
      <?php
      $testimonials = [
        ['Rahul Mehta','The online appointment system is amazing. I got a confirmed slot within minutes!','Cardiology Patient',5],
        ['Priya Gupta','Dr. Sharma was very thorough and explained everything clearly. Highly recommend!','Neurology Patient',5],
        ['Suresh Patel','The best hospital I have visited. Clean, modern, and the staff is very caring.','Orthopedics Patient',5],
      ];
      foreach($testimonials as [$name,$text,$dept,$rating]):
      ?>
      <div class="col-md-4">
        <div class="testimonial-card h-100">
          <div class="testimonial-stars"><?= str_repeat('<i class="bi bi-star-fill"></i>', $rating) ?></div>
          <p style="color:#334155;font-size:.9rem;line-height:1.7;margin-bottom:16px">"<?= e($text) ?>"</p>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="avatar-placeholder" style="width:40px;height:40px;font-size:.9rem"><?= strtoupper(substr($name,0,1)) ?></div>
            <div>
              <div style="font-weight:700;font-size:.875rem"><?= e($name) ?></div>
              <div style="font-size:.78rem;color:#64748B"><?= e($dept) ?></div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- FAQ -->
<section style="padding:80px 0;background:#fff">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="text-center mb-48px" style="margin-bottom:48px">
          <div class="section-badge" style="justify-content:center"><i class="bi bi-question-circle"></i> FAQ</div>
          <h2 class="section-title">Frequently Asked Questions</h2>
        </div>
        <?php
        $faqs = [
          ['How do I book an appointment?','You can book an appointment by registering as a patient, logging in, and using the Book Appointment feature. Choose your preferred doctor, date and time slot.'],
          ['Can I cancel or reschedule my appointment?','Yes, you can cancel or request rescheduling of appointments from your patient dashboard up to 2 hours before the appointment time.'],
          ['How can I access my prescriptions?','All prescriptions issued by your doctor are available in your patient portal under the Prescriptions section. You can view, download and print them.'],
          ['Is my medical data secure?','Yes, we use industry-standard encryption and security practices to protect all patient data. Your information is never shared without your consent.'],
        ];
        foreach($faqs as $i => [$q,$a]):
        ?>
        <div class="faq-item" onclick="this.classList.toggle('open')">
          <div class="faq-question">
            <?= e($q) ?>
            <i class="bi bi-plus faq-icon"></i>
          </div>
          <div class="faq-answer"><?= e($a) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- Contact + CTA -->
<section id="contact" style="padding:100px 0;background:linear-gradient(135deg,#0F172A,#1E3A8A)">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-5">
        <div class="section-badge" style="background:rgba(255,255,255,.1);color:#93C5FD"><i class="bi bi-geo-alt"></i> Contact Us</div>
        <h2 class="section-title text-white mb-4">Get In Touch With Us</h2>
        <div class="d-flex flex-column gap-3">
          <?php foreach([['bi-geo-alt','Address','123 Hospital Road, Medical District, Raipur, CG 492001'],['bi-telephone','Phone','+91 98765 43210'],['bi-envelope','Email','info@smarthospital.com'],['bi-clock','Hours','Mon-Sat 8:00 AM – 8:00 PM, Emergency 24/7']] as [$icon,$label,$value]): ?>
          <div style="display:flex;gap:14px">
            <div style="width:42px;height:42px;background:rgba(255,255,255,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <i class="bi <?= $icon ?>" style="color:#60A5FA"></i>
            </div>
            <div>
              <div style="font-size:.75rem;color:#64748B;font-weight:600"><?= $label ?></div>
              <div style="color:#CBD5E1;font-size:.875rem;margin-top:2px"><?= e($value) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-lg-7">
        <div style="background:rgba(255,255,255,.07);border-radius:20px;padding:32px;border:1px solid rgba(255,255,255,.1)">
          <h4 style="color:#fff;margin-bottom:24px;font-family:var(--font-display)">Send Us a Message</h4>
          <form id="contact-form">
            <div class="row g-3">
              <div class="col-md-6">
                <input type="text" placeholder="Your Name" class="form-control-custom" style="background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.15);color:#fff" required>
              </div>
              <div class="col-md-6">
                <input type="email" placeholder="Email Address" class="form-control-custom" style="background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.15);color:#fff" required>
              </div>
              <div class="col-12">
                <input type="text" placeholder="Subject" class="form-control-custom" style="background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.15);color:#fff">
              </div>
              <div class="col-12">
                <textarea rows="4" placeholder="Your Message" class="form-control-custom" style="background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.15);color:#fff;resize:vertical"></textarea>
              </div>
              <div class="col-12">
                <button type="submit" class="btn-primary-custom" style="padding:13px 28px">
                  <i class="bi bi-send"></i> Send Message
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Footer -->
<footer style="padding:60px 0 0">
  <div class="container">
    <div class="row g-5 pb-5" style="border-bottom:1px solid rgba(255,255,255,.07)">
      <div class="col-lg-4">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
          <div class="nav-logo-icon"><i class="bi bi-heart-pulse-fill"></i></div>
          <span style="color:#fff;font-family:var(--font-display);font-weight:800;font-size:1.15rem">Smart Hospital</span>
        </div>
        <p style="font-size:.875rem;line-height:1.7;max-width:280px">Providing world-class healthcare with compassion and cutting-edge technology since 1999.</p>
      </div>
      <div class="col-lg-2 col-md-4">
        <div class="footer-heading">Quick Links</div>
        <ul class="footer-links">
          <li><a href="#about">About Us</a></li>
          <li><a href="#services">Services</a></li>
          <li><a href="#doctors">Doctors</a></li>
          <li><a href="register.php">Book Appointment</a></li>
        </ul>
      </div>
      <div class="col-lg-2 col-md-4">
        <div class="footer-heading">Departments</div>
        <ul class="footer-links">
          <li><a href="#">Cardiology</a></li>
          <li><a href="#">Neurology</a></li>
          <li><a href="#">Orthopedics</a></li>
          <li><a href="#">Pediatrics</a></li>
        </ul>
      </div>
      <div class="col-lg-4 col-md-4">
        <div class="footer-heading">Emergency</div>
        <div style="background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);border-radius:12px;padding:16px">
          <div style="color:#FCA5A5;font-size:.8rem;margin-bottom:6px">24/7 Emergency Hotline</div>
          <div style="color:#fff;font-size:1.4rem;font-weight:800"><i class="bi bi-telephone-fill me-2" style="color:#F87171"></i>+91 98765 43210</div>
        </div>
      </div>
    </div>
    <div style="padding:20px 0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;font-size:.8rem">
      <span>© <?= date('Y') ?> Smart Hospital Management System. All rights reserved.</span>
      <span>Built with <i class="bi bi-heart-fill" style="color:#EF4444"></i> for better healthcare</span>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Navbar scroll effect
window.addEventListener('scroll', () => {
  document.getElementById('main-nav').classList.toggle('scrolled', window.scrollY > 50);
});

// Counter animation
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-count]').forEach(el => {
    const target = parseInt(el.dataset.count);
    let n = 0;
    const step = Math.ceil(target / 60);
    const t = setInterval(() => {
      n = Math.min(n + step, target);
      el.textContent = n.toLocaleString();
      if (n >= target) clearInterval(t);
    }, 20);
  });
});

// Contact form
document.getElementById('contact-form')?.addEventListener('submit', e => {
  e.preventDefault();
  e.target.reset();
  alert('Thank you for your message! We will get back to you soon.');
});
</script>
</body>
</html>
