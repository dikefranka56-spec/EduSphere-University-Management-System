<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Welcome to EduSphere University. Explore academic programmes, admissions and our student and administration portals.">
<title>EduSphere University | Excellence in Education</title>
<style>
:root {
    --navy:#102b50;
    --blue:#1769aa;
    --gold:#e6ad43;
    --light:#f4f7fb;
    --dark:#17263b;
    --muted:#68778b;
    --white:#fff;
}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:Inter,Arial,sans-serif;color:var(--dark);line-height:1.65;background:white}
a{text-decoration:none;color:inherit}
button{font:inherit}
.container{width:min(1160px,92%);margin:auto}
header{background:white;position:sticky;top:0;z-index:1000;box-shadow:0 3px 18px #102b5010}
.nav{height:78px;display:flex;align-items:center;justify-content:space-between;gap:20px}
.brand{display:flex;align-items:center;gap:10px;font-size:21px;font-weight:800;color:var(--navy)}
.logo{width:43px;height:43px;background:var(--navy);color:white;border-radius:12px;display:grid;place-items:center;font-size:24px}
.navlinks{display:flex;align-items:center;gap:26px;font-size:14px;font-weight:600}
.navlinks a:hover{color:var(--blue)}
.navactions{display:flex;gap:10px;align-items:center}
.btn{display:inline-flex;justify-content:center;align-items:center;padding:12px 20px;border-radius:8px;font-weight:700;font-size:14px;border:1px solid transparent;transition:.2s;cursor:pointer}
.btn:hover{transform:translateY(-2px)}
.btn-primary{background:var(--blue);color:white}
.btn-gold{background:var(--gold);color:#17263b}
.btn-outline{border-color:#d9e1ec;color:var(--navy)}
.menu{display:none;background:none;border:0;font-size:27px;color:var(--navy);cursor:pointer}
.hero{background:linear-gradient(100deg,#0c2344f5 0%,#102b50e8 48%,#102b5060 100%),url('https://images.unsplash.com/photo-1562774053-701939374585?auto=format&fit=crop&w=2000&q=85') center/cover;color:white;padding:105px 0 110px}
.hero-content{max-width:700px}
.eyebrow{color:#f3c66e;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:2px}
.hero h1{font-size:clamp(38px,6vw,66px);line-height:1.12;margin:20px 0}
.hero p{font-size:17px;color:#e2eaf5;max-width:590px;margin-bottom:30px}
.hero-buttons{display:flex;gap:13px;flex-wrap:wrap}
.hero .btn-outline{color:white;border-color:#ffffff80}
.trust{background:#fff;box-shadow:0 8px 35px #102b5010;position:relative;margin-top:-32px;border-radius:13px;padding:24px;display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.trust-item{text-align:center;border-right:1px solid #e9edf3}
.trust-item:last-child{border:0}
.trust strong{display:block;font-size:23px;color:var(--navy)}
.trust span{font-size:13px;color:var(--muted)}
section{padding:82px 0}
.section-heading{text-align:center;max-width:700px;margin:0 auto 42px}
.section-heading small{color:var(--blue);font-weight:800;text-transform:uppercase;letter-spacing:1.5px}
.section-heading h2{font-size:clamp(27px,4vw,39px);color:var(--navy);line-height:1.2;margin:10px 0}
.section-heading p{color:var(--muted)}
.about-grid{display:grid;grid-template-columns:1fr 1fr;gap:55px;align-items:center}
.about-image{min-height:360px;border-radius:18px;background:linear-gradient(#102b5020,#102b5020),url('https://images.unsplash.com/photo-1523050854058-8df90110c9f1?auto=format&fit=crop&w=1100&q=85') center/cover}
.about-copy h2{font-size:34px;line-height:1.25;color:var(--navy);margin:12px 0 18px}
.about-copy p{color:var(--muted);margin-bottom:17px}
.check{color:var(--blue);font-weight:700;margin:10px 0}
.programmes{background:var(--light)}
.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
.card{background:white;border:1px solid #e9eef5;border-radius:14px;padding:27px;transition:.25s}
.card:hover{transform:translateY(-5px);box-shadow:0 15px 35px #102b5010}
.card-icon{width:52px;height:52px;border-radius:13px;background:#e9f3fc;display:grid;place-items:center;font-size:25px;margin-bottom:17px}
.card h3{color:var(--navy);margin-bottom:9px;font-size:19px}
.card p{color:var(--muted);font-size:14px}
.portal-section{background:linear-gradient(135deg,#102b50,#174e7b);color:white}
.portal-section .section-heading h2{color:white}
.portal-section .section-heading p{color:#d5e2f1}
.portal-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}
.portal{padding:35px;border-radius:16px;background:white;color:var(--dark)}
.portal.admin{border-top:5px solid var(--gold)}
.portal.student{border-top:5px solid #318bd0}
.portal .symbol{font-size:35px}
.portal h3{font-size:24px;color:var(--navy);margin:12px 0 7px}
.portal p{color:var(--muted);margin-bottom:20px;font-size:14px}
.portal-actions{display:flex;gap:10px;flex-wrap:wrap}
.admissions{background:#f7f9fc}
.admission-box{background:white;border-radius:18px;padding:40px;display:flex;justify-content:space-between;align-items:center;gap:25px;border:1px solid #e7edf5}
.admission-box h2{font-size:28px;color:var(--navy)}
.admission-box p{color:var(--muted);margin-top:7px}
footer{background:#0b1d35;color:#d7e1ee;padding:55px 0 20px}
.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:40px}
footer h3,footer h4{color:white;margin-bottom:15px}
footer p,footer a{font-size:14px;color:#b9c7d8}
footer a{display:block;margin:8px 0}
footer a:hover{color:var(--gold)}
.copyright{border-top:1px solid #ffffff20;margin-top:35px;padding-top:20px;font-size:13px;color:#9aacc0}
@media(max-width:850px){
 .navlinks{display:none;position:absolute;top:78px;left:0;right:0;background:white;padding:22px 5%;flex-direction:column;align-items:flex-start;box-shadow:0 10px 20px #102b5015}
 .navlinks.open{display:flex}
 .menu{display:block}
 .navactions .btn-outline{display:none}
 .about-grid{grid-template-columns:1fr}
 .cards{grid-template-columns:repeat(2,1fr)}
 .footer-grid{grid-template-columns:1fr 1fr}
}
@media(max-width:560px){
 .nav{height:70px}
 .brand{font-size:18px}
 .navactions .btn{padding:10px 12px;font-size:12px}
 .hero{padding:75px 0 85px}
 .hero p{font-size:15px}
 .trust{grid-template-columns:1fr;margin-top:-20px}
 .trust-item{border-right:0;border-bottom:1px solid #e9edf3;padding-bottom:14px}
 .trust-item:last-child{border:0;padding-bottom:0}
 section{padding:60px 0}
 .cards,.portal-grid,.footer-grid{grid-template-columns:1fr}
 .admission-box{align-items:flex-start;flex-direction:column;padding:27px}
 .about-image{min-height:260px}
}
</style>
</head>
<body>

<header>
 <div class="container nav">
  <a href="index.php" class="brand"><span class="logo">🎓</span><span>EduSphere<br><small style="font-size:10px;letter-spacing:2px;color:#718198">UNIVERSITY</small></span></a>
  <nav class="navlinks" id="navlinks">
   <a href="#home">Home</a>
   <a href="#about">About</a>
   <a href="#academics">Academics</a>
   <a href="#admissions">Admissions</a>
   <a href="#portals">Portals</a>
  </nav>
  <div class="navactions">
   <a href="login.php" class="btn btn-outline">Sign In</a>
   <a href="register.php" class="btn btn-primary">Apply Now</a>
   <button class="menu" onclick="document.getElementById('navlinks').classList.toggle('open')" aria-label="Toggle menu">☰</button>
  </div>
 </div>
</header>

<main>
<section class="hero" id="home">
 <div class="container hero-content">
  <div class="eyebrow">Welcome to EduSphere University</div>
  <h1>Discover Your Potential. Shape Your Future.</h1>
  <p>Experience a learning environment built around academic excellence, innovation, leadership and the opportunities to make a meaningful impact.</p>
  <div class="hero-buttons">
   <a href="#academics" class="btn btn-gold">Explore Academics →</a>
   <a href="#portals" class="btn btn-outline">Access University Portals</a>
  </div>
 </div>
</section>

<div class="container">
 <div class="trust">
  <div class="trust-item"><strong>Academic Excellence</strong><span>Learning with purpose</span></div>
  <div class="trust-item"><strong>Student Focused</strong><span>Supporting every learner</span></div>
  <div class="trust-item"><strong>Future Ready</strong><span>Skills for tomorrow</span></div>
 </div>
</div>

<section id="about">
 <div class="container about-grid">
  <div class="about-image" role="img" aria-label="University campus"></div>
  <div class="about-copy">
   <div class="eyebrow" style="color:#1769aa">About EduSphere</div>
   <h2>Education That Opens Doors to Possibility</h2>
   <p>EduSphere University is built around the belief that education should empower people with knowledge, practical skills, confidence and the ability to contribute to society.</p>
   <p>Our university management platform brings essential academic services together, helping students and administrators access important information in one place.</p>
   <div class="check">✓ A supportive academic environment</div>
   <div class="check">✓ Connected student and academic services</div>
   <div class="check">✓ Digital access to university resources</div>
   <a href="#portals" class="btn btn-primary" style="margin-top:15px">Explore the Portals</a>
  </div>
 </div>
</section>

<section class="programmes" id="academics">
 <div class="container">
  <div class="section-heading">
   <small>Learning and Discovery</small>
   <h2>Explore Our Academic Environment</h2>
   <p>Discover the academic areas and university services that support a complete learning experience.</p>
  </div>
  <div class="cards">
   <article class="card"><div class="card-icon">📚</div><h3>Faculties & Departments</h3><p>Explore academic structures, departments and areas of study across the institution.</p></article>
   <article class="card"><div class="card-icon">🎓</div><h3>Academic Programmes</h3><p>Discover learning pathways designed to develop knowledge and professional capability.</p></article>
   <article class="card"><div class="card-icon">🗓️</div><h3>Academic Calendar</h3><p>Stay informed about academic sessions, semesters and important university activities.</p></article>
   <article class="card"><div class="card-icon">📝</div><h3>Course Registration</h3><p>Access course enrolment and keep track of your registered academic activities.</p></article>
   <article class="card"><div class="card-icon">📊</div><h3>Results & Assessment</h3><p>Access academic performance information and assessment records through the student portal.</p></article>
   <article class="card"><div class="card-icon">🤝</div><h3>Student Support</h3><p>Stay connected with announcements, notifications and important academic information.</p></article>
  </div>
 </div>
</section>

<section class="portal-section" id="portals">
 <div class="container">
  <div class="section-heading">
   <small style="color:#f3c66e">Digital Campus</small>
   <h2>Access Your EduSphere Portal</h2>
   <p>Choose the portal that matches your role in the university.</p>
  </div>
  <div class="portal-grid">
   <article class="portal student">
    <div class="symbol">🎓</div>
    <h3>Student Portal</h3>
    <p>Access your student dashboard, course registration, academic results, attendance, timetable, fees and notifications.</p>
    <div class="portal-actions">
     <a href="login.php" class="btn btn-primary">Student Login</a>
     <a href="register.php" class="btn btn-outline">Create Student Account</a>
    </div>
   </article>
   <article class="portal admin">
    <div class="symbol">🛡️</div>
    <h3>Administration Portal</h3>
    <p>Access university administration tools for managing students, lecturers, courses, enrolments, results and academic operations.</p>
    <div class="portal-actions">
     <a href="login.php" class="btn btn-gold">Admin Login</a>
    </div>
    <p style="margin:13px 0 0;font-size:12px">Administrator accounts are created through an authorized setup process.</p>
   </article>
  </div>
 </div>
</section>

<section class="admissions" id="admissions">
 <div class="container">
  <div class="admission-box">
   <div><div class="eyebrow" style="color:#1769aa">Your Next Chapter</div><h2>Ready to Begin Your Journey?</h2><p>Create your student account to access the EduSphere student portal.</p></div>
   <a href="register.php" class="btn btn-primary">Get Started →</a>
  </div>
 </div>
</section>
</main>

<footer>
 <div class="container">
  <div class="footer-grid">
   <div><h3>🎓 EduSphere University</h3><p>Empowering learning through academic excellence, innovation and connected university services.</p></div>
   <div><h4>Explore</h4><a href="#about">About Us</a><a href="#academics">Academics</a><a href="#admissions">Admissions</a></div>
   <div><h4>Portal Access</h4><a href="login.php">Student Login</a><a href="register.php">Student Registration</a><a href="login.php">Admin Login</a></div>
  </div>
  <div class="copyright">© <span id="year"></span> EduSphere University. All rights reserved.</div>
 </div>
</footer>
<script>
document.getElementById('year').textContent=new Date().getFullYear();
</script>
</body>
</html>