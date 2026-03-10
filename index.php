<?php
require_once __DIR__ . '/app/auth.php';
start_session();
$user = current_user();

// If already logged in, route to the correct dashboard
if ($user) {
  header('Location: ' . role_home_url($user['role']));
  exit;
}

$pageTitle = 'UCC – RDC ISSN Application Portal';
$bodyClass = 'landing';
$hideNav = true;
$inlineStyles = <<<'CSS'
.hero {
  background: radial-gradient(1200px circle at 0% 0%, #e7f1ff 0%, rgba(231, 241, 255, 0) 55%),
              radial-gradient(800px circle at 100% 20%, #fff4e7 0%, rgba(255, 244, 231, 0) 50%);
}
.card-soft {
  border: 1px solid rgba(0,0,0,.08);
  box-shadow: 0 10px 30px rgba(0,0,0,.06);
}

/* ===== Landing page theme (scoped) ===== */
body.landing{
  min-height: 100vh;
  margin: 0;
  font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  background-color: #145c32;
  color: #f8fafc;
  --green: #0f6b3a;
  --green-2: #22c55e;
  --green-soft: rgba(34,197,94,0.18);
  --green-deep: #0b4f2a;
  --slate: #0f172a;
  --glass: rgba(15,23,42,0.35);
  --card-border: rgba(15,23,42,0.08);
}

body.landing .navbar-landing{
  font-weight: 600;
  background: radial-gradient(circle at top left, rgba(15,107,58,0.92) 0%, rgba(11,79,42,0.8) 28%, rgba(15,23,42,0.9) 85%);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(148,163,184,0.22);
  height: 4.5rem;
  padding-top: 0.5rem;
  padding-bottom: 0.5rem;
}

body.landing .navbar-landing .nav-link{
  color: rgba(248,250,252,0.85);
  transition: color .2s ease, transform .2s ease;
  position: relative;
  padding-bottom: .2rem;
}
body.landing .navbar-landing .nav-link:hover{
  color: #ffffff;
  transform: translateY(-1px);
}
body.landing .navbar-landing .nav-link::after{
  content: '';
  position: absolute;
  left: 0;
  bottom: -.2rem;
  width: 0;
  height: 2px;
  background: linear-gradient(90deg, #c7f9d8, #93c5fd);
  border-radius: 999px;
  transition: width .2s ease;
}
body.landing .navbar-landing .nav-link:hover::after,
body.landing .navbar-landing .nav-link:focus-visible::after{
  width: 100%;
}

body.landing .navbar-landing.navbar-scrolled{
  padding-top: .35rem;
  padding-bottom: .35rem;
}

body.landing .navbar-landing .navbar-brand{
  gap: .6rem;
}

body.landing .navbar-landing .navbar-logo{
  width: 60px;
  height: 60px;
  object-fit: contain;
}

@media (max-width: 991.98px){
  body.landing .navbar-landing{ height: auto; }
  body.landing .navbar-landing .navbar-logo{ width: 48px; height: 48px; }
}

body.landing .navbar-landing .navbar-brand{
  gap: 0.5rem;
  line-height: 1;
  align-items: center;
}

body.landing .navbar-landing .navbar-brand img{
  width: 70px;
  height: 70px;
  object-fit: contain;
}

body.landing .navbar-landing.navbar-scrolled{
  box-shadow: 0 18px 50px rgba(0,0,0,0.55);
  background: radial-gradient(circle at top left, rgba(15,23,42,0.95) 0%, rgba(15,107,58,0.4) 35%, rgba(15,23,42,0.95) 90%);
}

body.landing .hero-overlay{
  background:
    radial-gradient(circle at top left, rgba(15,23,42,0.5) 35%, rgba(11,79,42,0.92) 90%),
    url('/uccrdc/assets/img/university-bg.jpg');
  background-size: cover;
  background-position: center;
  background-attachment: fixed;
  position: relative;
  min-height: calc(100vh - 4.5rem);
  display: flex;
  align-items: center;
}
body.landing .hero-overlay::before{
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(120deg, rgba(2,6,23,.65), rgba(34,197,94,.18), rgba(2,6,23,.65));
}
body.landing .hero-overlay > *{ position: relative; z-index: 1; }
@media (max-width: 991.98px){
  body.landing .hero-overlay{ background-attachment: scroll; }
}

body.landing .hero-kicker{
  font-size: .85rem;
  letter-spacing: .14em;
  text-transform: uppercase;
  font-weight: 800;
  color: #c7f9d8;
}
body.landing .hero-title{
  font-weight: 900;
  font-size: clamp(2.2rem, 3vw + 1rem, 3.1rem);
  line-height: 1.05;
  text-shadow: 0 6px 24px rgba(2,6,23,0.45);
}

body.landing .lead{ color: rgba(248,250,252,.9) !important; }

body.landing #typed-text{
  background: linear-gradient(90deg, #ffffff, var(--green-2), #86efac);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}

body.landing #typed-text::after{
  content: "▋";
  color: rgba(248,250,252,.85);
  margin-left: .25rem;
  animation: blink .9s steps(2) infinite;
}

@keyframes blink{
  50%{ opacity: 0; }
}

@media (prefers-reduced-motion: reduce){
  body.landing #typed-text::after{ animation: none; }
}

body.landing .hero-card{
  background: radial-gradient(circle at top left, rgba(15,23,42,0.95) 0%, rgba(34,197,94,0.32) 35%, rgba(15,23,42,0.95) 90%);
  border-radius: 1.25rem;
  border: 1px solid rgba(255,255,255,.14);
  box-shadow: 0 24px 70px rgba(0,0,0,.55);
  backdrop-filter: blur(24px) saturate(65%);
}

body.landing .section-heading{
  position: relative;
  display: inline-block;
}
body.landing .section-heading::after{
  content: '';
  display: block;
  margin: .45rem auto 0;
  width: 78px;
  height: 3px;
  border-radius: 999px;
  background: linear-gradient(90deg, var(--green-2), #14b8a6);
}

body.landing section.bg-light{
  background: linear-gradient(180deg, rgba(255,255,255,.92), rgba(255,255,255,.98)) !important;
}

body.landing .btn-success{
  border: none;
  box-shadow: 0 10px 24px rgba(15,107,58,0.3);
}
body.landing .btn-success:hover{
  transform: translateY(-1px);
}
body.landing .btn-outline-light,
body.landing .btn-outline-success{
  border-width: 2px;
}
body.landing .btn-outline-success{
  color: var(--green);
  border-color: rgba(15,107,58,0.45);
}
body.landing .btn-outline-success:hover{
  background: rgba(15,107,58,0.1);
  color: var(--green-deep);
}

body.landing .stats-strip{
  background: linear-gradient(135deg, rgba(15,23,42,0.92), rgba(15,107,58,0.28));
  border-top: 1px solid rgba(148,163,184,0.25);
  border-bottom: 1px solid rgba(148,163,184,0.25);
}

body.landing .stat-card-lite{
  background: rgba(15,23,42,0.72);
  border: 1px solid rgba(148,163,184,0.18);
  border-radius: 14px;
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  height: 100%;
  box-shadow: 0 12px 30px rgba(0,0,0,0.25);
  backdrop-filter: blur(10px);
}

body.landing .stat-card-lite .stat-icon{
  width: 38px;
  height: 38px;
  border-radius: 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

body.landing .stat-card-lite .stat-value{
  font-weight: 800;
  font-size: 1.05rem;
  color: #f8fafc;
}

body.landing .stat-card-lite .stat-label{
  color: rgba(248,250,252,0.72);
  font-size: .9rem;
}

body.landing .card.hover-lift{
  transition: transform .2s ease, box-shadow .2s ease;
  border: 1px solid var(--card-border) !important;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  position: relative;
  overflow: hidden;
}
body.landing .card.hover-lift:hover{
  transform: translateY(-8px);
  box-shadow: 0 1rem 2.5rem rgba(0,0,0,.15) !important;
}
body.landing .card.hover-lift::before{
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  width: 6px;
  height: 100%;
  background: linear-gradient(180deg, var(--green-2), var(--green));
  opacity: .85;
}
body.landing .card.hover-lift .card-body{ position: relative; z-index: 1; }
body.landing .card.hover-lift .rounded-circle{
  background: var(--green-soft) !important;
  color: var(--green) !important;
}

body.landing .faq-accordion .accordion-item{
  border: 0;
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 14px 28px rgba(15,23,42,0.1);
  margin-bottom: 14px;
}

body.landing .faq-accordion .accordion-button{
  font-weight: 700;
  padding: 16px 20px;
  background: #fff;
}

body.landing .faq-accordion .accordion-button:not(.collapsed){
  color: #0f172a;
  background: rgba(15,107,58,0.12);
  box-shadow: inset 0 -1px 0 rgba(0,0,0,0.06);
}

body.landing .faq-accordion .accordion-body{
  color: #475569;
  padding: 16px 20px 20px;
}

body.landing .contact-card{
  border: 1px solid rgba(148,163,184,0.15);
  box-shadow: 0 18px 40px rgba(15,23,42,0.14);
  border-radius: 18px;
  background: linear-gradient(180deg, #ffffff 0%, #f4f7f6 100%);
}

body.landing .contact-kicker{
  font-size: .78rem;
  letter-spacing: .12em;
  font-weight: 800;
  text-transform: uppercase;
  color: var(--green);
  margin-bottom: 0.2rem;
}

body.landing .contact-icon{
  width: 52px;
  height: 52px;
  border-radius: 16px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--green-soft);
  color: var(--green);
  font-size: 20px;
}

body.landing .contact-list{
  display: grid;
  gap: 14px;
}

body.landing .contact-item{
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

body.landing .contact-badge{
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--green-soft);
  color: var(--green);
  font-size: 16px;
  flex: 0 0 auto;
}

body.landing .contact-map-frame{
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid rgba(148,163,184,0.2);
  box-shadow: inset 0 0 0 1px rgba(255,255,255,0.2);
}

body.landing .contact-map-frame iframe{ filter: saturate(1.05) contrast(1.05); }

@media (max-width: 991.98px){
  body.landing .contact-card{ box-shadow: 0 10px 26px rgba(15,23,42,0.12); }
}

/* Auth modal (compact) */
body.landing #authModal .modal-dialog{ max-width: 560px; }
body.landing #authModal .modal-content{ border-radius: 12px; overflow: hidden; }
body.landing #authModal .auth-card{ position: relative; padding: 22px 22px 24px; }
body.landing #authModal .auth-card .btn-close{ position: absolute; top: 14px; right: 14px; }
body.landing #authModal .auth-card .form-control,
body.landing #authModal .auth-card .form-select{ border-radius: 8px; padding: 14px 16px; font-size: 1rem; }
body.landing #authModal .auth-card .btn{ border-radius: 8px; }
body.landing #authModal .auth-link{ font-weight: 800; text-decoration: none; }
body.landing #authModal .auth-link:hover{ text-decoration: underline; }
body.landing #authModal .auth-link-green{ color: #198754; }
CSS;
require_once __DIR__ . '/includes/header.php';
?>

<nav class="navbar navbar-expand-lg navbar-dark navbar-landing fixed-top">
  <div class="container">
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <a class="navbar-brand fw-bold d-flex align-items-center" href="#about">
      <span class="d-inline-flex align-items-center justify-content-center">
        <img src="rdc.png" alt="UCC" class="navbar-logo" onerror="this.style.display='none'">
      </span>
      <span class="text-truncate">UCC – RDC</span>
    </a>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto me-3 mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
        <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
        <li class="nav-item"><a class="nav-link" href="#workflow">Workflow</a></li>
        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
      </ul>
      <div class="d-flex gap-2">
        <a href="#" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#authModal" data-auth-tab="login">Login</a>
      </div>
    </div>
  </div>
</nav>

<main>

  <header class="hero-overlay" id="about">
    <div class="container-xl py-5" style="padding-top: 7rem; padding-bottom: 5rem;">
      <div class="row align-items-center g-4">
        <div class="col-lg-6 text-center text-lg-start">
          <div class="hero-kicker mb-2">WELCOME TO UCC – RDC ISSN APPLICATION PORTAL</div>
          <h1 class="hero-title mb-3"><span id="typed-text" data-texts="Submit Application, track status, and get updates|Submit Application with ease|Track status and get updates"></span></h1>
          <p class="lead" style="color: rgba(248,250,252,.86); max-width: 540px;">
            A centralized portal for authors to submit ISSN applications with required attachments,
            and for staff/admin to review and process submissions.
          </p>

          <div class="d-flex flex-wrap justify-content-center justify-content-lg-start gap-3 mb-3">
            <a href="#" class="btn btn-success btn-lg rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#authModal" data-auth-tab="login">
              <i class="fa-solid fa-arrow-right me-2"></i>Get started
            </a>
            <a href="#features" class="btn btn-outline-light btn-lg rounded-pill">
              <i class="fa-solid fa-circle-info me-2"></i>Learn more
            </a>
          </div>

          <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-lg-start gap-3" style="color: rgba(248,250,252,.72); font-size:.9rem;">
            <span><i class="fa-solid fa-check-circle me-1 text-success"></i> Status tracking</span>
            <span class="vr d-none d-md-inline"></span>
            <span><i class="fa-solid fa-lock me-1 text-info"></i> Role-based access</span>
          </div>
        </div>

        <!-- <div class="col-lg-6">
          <div class="hero-card p-4 p-md-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h5 class="mb-1 text-white">Guidelines snapshot</h5>
                <small class="text-white-50">What you need before submitting</small>
              </div>
              <span class="badge bg-success-subtle text-success border border-success-subtle">Ready</span>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-6">
                <div class="border rounded-3 p-3">
                  <div class="small text-white-50 mb-1">Valid ID</div>
                  <div class="h5 mb-0 text-white"><i class="fa-solid fa-id-card"></i></div>
                </div>
              </div>
              <div class="col-6">
                <div class="border rounded-3 p-3">
                  <div class="small text-white-50 mb-1">Publication PDF</div>
                  <div class="h5 mb-0 text-white"><i class="fa-solid fa-file-pdf"></i></div>
                </div>
              </div>
            </div>

            <div class="border-top border-secondary pt-3 mt-2">
              <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                  <span class="badge bg-primary bg-opacity-25 text-primary me-2"><i class="fa-solid fa-list-check"></i></span>
                  <div class="small">
                    <div class="fw-semibold text-white">Submit once, track anytime</div>
                    <div class="text-white-50">Pending → Approved/Rejected with admin comments emailed.</div>
                  </div>
                </div>
                <i class="fa-solid fa-arrow-trend-up text-success"></i>
              </div>
            </div>

          </div>
        </div> -->
      </div>
    </div>
  </header>

  <!-- <section class="stats-strip py-4">
    <div class="container">
      <div class="row g-3">
        <div class="col-sm-6 col-lg-3">
          <div class="stat-card-lite">
            <div class="stat-icon bg-success-subtle text-success"><i class="fa-solid fa-bolt"></i></div>
            <div class="stat-value">Fast reviews</div>
            <div class="stat-label">Streamlined by RDC staff</div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="stat-card-lite">
            <div class="stat-icon bg-primary-subtle text-primary"><i class="fa-solid fa-shield-halved"></i></div>
            <div class="stat-value">Secure storage</div>
            <div class="stat-label">Protected PDF handling</div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="stat-card-lite">
            <div class="stat-icon bg-warning-subtle text-warning"><i class="fa-solid fa-bell"></i></div>
            <div class="stat-value">Live updates</div>
            <div class="stat-label">Email + dashboard alerts</div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="stat-card-lite">
            <div class="stat-icon bg-info-subtle text-info"><i class="fa-solid fa-layer-group"></i></div>
            <div class="stat-value">Centralized</div>
            <div class="stat-label">Single portal for ISSN</div>
          </div>
        </div>
      </div>
    </div>
  </section> -->

  <section class="py-5 bg-light" id="features">
    <div class="container text-center mb-4">
      <h2 class="fw-bold mb-2 text-dark section-heading">Everything you need for ISSN processing</h2>
      <p class="text-muted mb-0">From author submission to admin approval, all in one portal.</p>
    </div>

    <div class="container">
      <div class="row g-4">
        <div class="col-md-4">
          <div class="card hover-lift h-100 border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center" style="width:52px;height:52px;">
                  <i class="fa-solid fa-file-circle-plus fa-lg"></i>
                </div>
                <div>
                  <div class="fw-semibold text-dark">Submit publications</div>
                  <div class="small text-muted">Upload PDF + choose formats</div>
                </div>
              </div>
              <p class="text-muted mb-0">Submit publication details, attach PDF, and optionally enter former publication information.</p>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card hover-lift h-100 border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center" style="width:52px;height:52px;">
                  <i class="fa-solid fa-gauge fa-lg"></i>
                </div>
                <div>
                  <div class="fw-semibold text-dark">Track status</div>
                  <div class="small text-muted">Pending / Approved / Rejected</div>
                </div>
              </div>
              <p class="text-muted mb-0">See the exact status of each submission from your dashboard.</p>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card hover-lift h-100 border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="rounded-circle bg-warning-subtle text-warning d-inline-flex align-items-center justify-content-center" style="width:52px;height:52px;">
                  <i class="fa-solid fa-envelope-circle-check fa-lg"></i>
                </div>
                <div>
                  <div class="fw-semibold text-dark">Email updates</div>
                  <div class="small text-muted">Admin actions + comments</div>
                </div>
              </div>
              <p class="text-muted mb-0">Authors get emailed on submission receipt and admin approve/reject with comments.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="py-5" id="workflow">
    <div class="container text-center mb-4">
      <h2 class="fw-bold mb-2 text-white section-heading">How it works</h2>
      <p class="mb-0" style="color: rgba(248,250,252,.80);">Three steps from submission to decision.</p>
    </div>

    <div class="container mb-2">
      <div class="row g-4">
        <div class="col-md-4">
          <div class="card hover-lift h-100 border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
              <span class="badge rounded-pill bg-primary-subtle text-primary mb-3 px-3 py-2">Step 1</span>
              <h6 class="fw-semibold mb-2 text-dark">Register and submit</h6>
              <p class="text-muted mb-0">Create an Author account, then submit a new publication with PDF attachment and formats.</p>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card hover-lift h-100 border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
              <span class="badge rounded-pill bg-success-subtle text-success mb-3 px-3 py-2">Step 2</span>
              <h6 class="fw-semibold mb-2 text-dark">Review</h6>
              <p class="text-muted mb-0">Staff can review and recommend; admin reviews details and file and may leave a comment.</p>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card hover-lift h-100 border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
              <span class="badge rounded-pill bg-warning-subtle text-warning mb-3 px-3 py-2">Step 3</span>
              <h6 class="fw-semibold mb-2 text-dark">Approve or reject</h6>
              <p class="text-muted mb-0">Admin sets status to Approved/Rejected; author is notified via email and in the dashboard.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="py-5 bg-light" id="faq">
    <div class="container">
      <div class="text-center mb-4">
        <h2 class="fw-bold text-dark section-heading">Frequently asked questions</h2>
        <p class="text-muted mb-0">Quick answers before you submit.</p>
      </div>

      <div class="row justify-content-center">
        <div class="col-lg-9">
          <div class="accordion faq-accordion" id="faqAccordion">
            <div class="accordion-item">
              <h2 class="accordion-header" id="faqOne">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseOne" aria-expanded="true" aria-controls="faqCollapseOne">
                  What files do I need for submission?
                </button>
              </h2>
              <div id="faqCollapseOne" class="accordion-collapse collapse show" aria-labelledby="faqOne" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                  Prepare a valid ID (image) and your publication PDF. Optional details for former publications can be added during submission.
                </div>
              </div>
            </div>

            <div class="accordion-item">
              <h2 class="accordion-header" id="faqTwo">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseTwo" aria-expanded="false" aria-controls="faqCollapseTwo">
                  How do I track my application?
                </button>
              </h2>
              <div id="faqCollapseTwo" class="accordion-collapse collapse" aria-labelledby="faqTwo" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                  Log in to your Author dashboard to view status updates and admin comments in real time.
                </div>
              </div>
            </div>

            <div class="accordion-item">
              <h2 class="accordion-header" id="faqThree">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseThree" aria-expanded="false" aria-controls="faqCollapseThree">
                  Will I receive email updates?
                </button>
              </h2>
              <div id="faqCollapseThree" class="accordion-collapse collapse" aria-labelledby="faqThree" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                  Yes. You will receive emails after submission and when the admin approves or rejects your application.
                </div>
              </div>
            </div>

            <div class="accordion-item">
              <h2 class="accordion-header" id="faqFour">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseFour" aria-expanded="false" aria-controls="faqCollapseFour">
                  Can I reset my password?
                </button>
              </h2>
              <div id="faqCollapseFour" class="accordion-collapse collapse" aria-labelledby="faqFour" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                  Use the “Forgot Password” link on the login tab to receive a secure reset link.
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="py-2 bg-light" id="contact">
    <div class="container">
      <div class="text-center mb-2">
        <h2 class="fw-bold text-dark section-heading">Contact</h2>
        <p class="text-muted mb-0">Questions or concerns? Reach out to the RDC office.</p>
      </div>

      <div class="row g-4 align-items-stretch">
        <div class="col-lg-5">
          <div class="card contact-card h-100">
            <div class="card-body p-4 p-md-5 d-flex flex-column">
              <div class="d-flex align-items-center gap-3 mb-4">
                <div class="contact-icon">
                  <i class="fa-solid fa-headset"></i>
                </div>
                <div>
                  <div class="contact-kicker">Get in touch</div>
                  <div class="fw-semibold text-dark">Research Development Center</div>
                  <div class="small text-muted">University of Caloocan City</div>
                </div>
              </div>

              <div class="contact-list">
                <div class="contact-item">
                  <span class="contact-badge"><i class="fa-solid fa-envelope"></i></span>
                  <div>
                    <div class="small text-muted">Email</div>
                    <div class="fw-semibold">csdsg@ucc-caloocan.edu.ph</div>
                  </div>
                </div>
                <div class="contact-item">
                  <span class="contact-badge"><i class="fa-solid fa-phone"></i></span>
                  <div>
                    <div class="small text-muted">Phone</div>
                    <div class="fw-semibold">(02) 0000 0000</div>
                  </div>
                </div>
                <div class="contact-item">
                  <span class="contact-badge"><i class="fa-solid fa-location-dot"></i></span>
                  <div>
                    <div class="small text-muted">Office</div>
                    <div class="fw-semibold">Caloocan City, Metro Manila</div>
                  </div>
                </div>
              </div>

              <div class="pt-3 border-top">
                <div class="small text-muted mb-2">Office hours</div>
                <div class="d-flex flex-wrap gap-2">
                  <span class="badge rounded-pill text-bg-light border">Mon–Fri</span>
                  <span class="badge rounded-pill text-bg-light border">8:00 AM – 5:00 PM</span>
                </div>
              </div>

              <!-- <div class="mt-4">
                <a href="#" class="btn btn-success rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#authModal" data-auth-tab="login">
                  <i class="fa-solid fa-right-to-bracket me-2"></i>Login
                </a>
                <a href="#" class="btn btn-outline-success rounded-pill px-4 ms-2" data-bs-toggle="modal" data-bs-target="#authModal" data-auth-tab="register">
                  <i class="fa-solid fa-user-plus me-2"></i>Register
                </a>
              </div> -->
            </div>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="card contact-card h-auto">
            <div class="card-body py-2">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div>
                  <div class="contact-kicker">Location</div>
                  <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                    <h5 class="mb-0 text-dark fw-bold">University of Caloocan City</h5>
                    <a class="btn btn-outline-success btn-sm rounded-pill px-3 justify-content-end" target="_blank" rel="noopener"
                      href="https://www.google.com/maps?q=MX3V%2B9VH%2C%20Biglang%20Awa%20St%20Cor%2011th%20Ave%20Catleya%2C%20Grace%20Park%20East%2C%20Caloocan%2C%201400%20Metro%20Manila">
                      <i class="fas fa-location-arrow me-1"></i> Get directions
                    </a>
                  </div>
                  <div class="small text-muted">
                    MX3V+9VH, Biglang Awa St Cor 11th Ave Catleya, Grace Park East, Caloocan, 1400 Metro Manila
                  </div>
                </div>
              </div>

              <div class="contact-map-frame ratio ratio-21x9">
                <iframe
                  src="https://www.google.com/maps?q=MX3V%2B9VH%2C%20Biglang%20Awa%20St%20Cor%2011th%20Ave%20Catleya%2C%20Grace%20Park%20East%2C%20Caloocan%2C%201400%20Metro%20Manila&output=embed"
                  loading="lazy"
                  referrerpolicy="no-referrer-when-downgrade"
                  style="border:0;"
                  allowfullscreen
                  title="University of Caloocan City location map"
                ></iframe>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

</main>

<!-- Auth Modal (Login / Register / Forgot Password) -->
<div class="modal fade" id="authModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-body p-0">
        <div class="auth-card bg-white">
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

          <!-- Hidden tab buttons for Bootstrap Tabs -->
          <ul class="nav nav-pills d-none" id="authTabs" role="tablist" aria-hidden="true">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="tab-login-btn" data-bs-toggle="pill" data-bs-target="#tab-login" type="button" role="tab">Login</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-register-btn" data-bs-toggle="pill" data-bs-target="#tab-register" type="button" role="tab">Register</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-forgot-btn" data-bs-toggle="pill" data-bs-target="#tab-forgot" type="button" role="tab">Forgot</button>
            </li>
          </ul>

          <div class="text-center mb-3">
            <img src="rdc.png" alt="UCC" style="width:96px;height:96px;object-fit:contain;" onerror="this.style.display='none'">
            <div class="fw-bold text-dark">UCC – RDC ISSN Portal</div>
            <div class="text-muted small">Sign in to continue</div>
          </div>

          <div class="tab-content" id="authTabContent">
            <!-- Login -->
            <div class="tab-pane fade show active" id="tab-login" role="tabpanel" tabindex="0">
              <form id="loginForm" class="auth-form">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div data-alerts></div>
                <div class="mb-3">
                  <input type="email" class="form-control form-control-lg" name="email" placeholder="Email" autocomplete="username" required>
                </div>
                <div class="mb-3">
                  <div class="input-group input-group-lg">
                    <input type="password" class="form-control" id="loginPassword" name="password" placeholder="Password" autocomplete="current-password" required>
                    <button class="btn btn-outline-secondary js-toggle-password" type="button" data-target="#loginPassword" aria-label="Show password">
                      <i class="fa-regular fa-eye"></i>
                    </button>
                  </div>
                </div>
                <button type="submit" class="btn btn-success w-100">Login</button>

                <div class="text-center mt-3">
                  <a href="#" class="auth-link auth-link-green" data-switch-tab="forgot">Forgot Password?</a>
                </div>
                <div class="text-center text-muted">
                  Don't have an account?
                  <a href="#" class="auth-link auth-link-green" data-switch-tab="register">Register here</a>
                </div>
              </form>
            </div>

            <!-- Register -->
            <div class="tab-pane fade" id="tab-register" role="tabpanel" tabindex="0">
              <form id="registerForm" class="auth-form" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div data-alerts></div>

                <!-- Email Verification Step -->
                <div id="emailVerificationStep" class="mb-3 pb-3 border-bottom">
                  <label class="form-label fw-semibold">Email *</label>
                  <div class="input-group">
                    <input class="form-control" type="email" id="registerEmail" placeholder="Enter your email" autocomplete="email" required>
                    <button class="btn btn-success" id="sendOtpBtn" type="button">Send OTP</button>
                  </div>
                  <div class="form-text mt-2">We'll send a verification code to your email.</div>
                </div>

                <!-- OTP Verification Step (hidden initially) -->
                <div id="otpVerificationStep" class="mb-3 pb-3 border-bottom" style="display: none;">
                  <label class="form-label fw-semibold">Verification Code *</label>
                  <div class="row g-2">
                    <div class="col-auto flex-grow-1">
                      <input class="form-control form-control-lg" type="text" id="otpInput" inputmode="numeric" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required style="letter-spacing: 5px; text-align: center; font-size: 24px;">
                    </div>
                    <div class="col-auto">
                      <button class="btn btn-success btn-lg" id="verifyOtpBtn" type="button">Verify</button>
                    </div>
                  </div>
                  <div class="form-text mt-2">
                    <span id="otpTimer">Check your email for the 6-digit code</span>
                    <button class="btn btn-link btn-sm" id="resendOtpBtn" type="button" style="display: none;">Resend code</button>
                  </div>
                </div>

                <!-- Main Registration Fields (hidden until OTP verified) -->
                <div id="mainRegistrationFields" style="display: none;">
                <div class="row g-2">
                  <div class="col-6">
                    <input class="form-control" name="last_name" placeholder="Last name" required>
                  </div>
                  <div class="col-6">
                    <input class="form-control" name="first_name" placeholder="First name" required>
                  </div>
                  <input type="hidden" name="email" id="registrationEmail">
                  <div class="col-6">
                    <input class="form-control" name="contact_number" placeholder="Contact number" autocomplete="tel" required>
                  </div>
                  <div class="col-6">
  <select class="form-control" name="course" required>
    <option value="" disabled selected>Select Course & Section</option>

    <optgroup label="BSIT">
      <option value="BSIT 1A">BSIT 1A</option>
      <option value="BSIT 1B">BSIT 1B</option>
      <option value="BSIT 1C">BSIT 1C</option>
      <option value="BSIT 2A">BSIT 2A</option>
      <option value="BSIT 2B">BSIT 2B</option>
      <option value="BSIT 2C">BSIT 2C</option>
      <option value="BSIT 3A">BSIT 3A</option>
      <option value="BSIT 3B">BSIT 3B</option>
      <option value="BSIT 3C">BSIT 3C</option>
      <option value="BSIT 4A">BSIT 4A</option>
      <option value="BSIT 4B">BSIT 4B</option>
      <option value="BSIT 4C">BSIT 4C</option>
    </optgroup>

    <optgroup label="BSIS">
      <option value="BSIS 1A">BSIS 1A</option>
      <option value="BSIS 1B">BSIS 1B</option>
      <option value="BSIS 1C">BSIS 1C</option>
      <option value="BSIS 2A">BSIS 2A</option>
      <option value="BSIS 2B">BSIS 2B</option>
      <option value="BSIS 2C">BSIS 2C</option>
      <option value="BSIS 3A">BSIS 3A</option>
      <option value="BSIS 3B">BSIS 3B</option>
      <option value="BSIS 3C">BSIS 3C</option>
      <option value="BSIS 4A">BSIS 4A</option>
      <option value="BSIS 4B">BSIS 4B</option>
      <option value="BSIS 4C">BSIS 4C</option>
    </optgroup>

    <optgroup label="BSEMC">
      <option value="BSEMC 1A">BSEMC 1A</option>
      <option value="BSEMC 1B">BSEMC 1B</option>
      <option value="BSEMC 1C">BSEMC 1C</option>
      <option value="BSEMC 2A">BSEMC 2A</option>
      <option value="BSEMC 2B">BSEMC 2B</option>
      <option value="BSEMC 2C">BSEMC 2C</option>
      <option value="BSEMC 3A">BSEMC 3A</option>
      <option value="BSEMC 3B">BSEMC 3B</option>
      <option value="BSEMC 3C">BSEMC 3C</option>
      <option value="BSEMC 4A">BSEMC 4A</option>
      <option value="BSEMC 4B">BSEMC 4B</option>
      <option value="BSEMC 4C">BSEMC 4C</option>
    </optgroup>

    <optgroup label="BSCS">
      <option value="BSCS 1A">BSCS 1A</option>
      <option value="BSCS 1B">BSCS 1B</option>
      <option value="BSCS 1C">BSCS 1C</option>
      <option value="BSCS 2A">BSCS 2A</option>
      <option value="BSCS 2B">BSCS 2B</option>
      <option value="BSCS 2C">BSCS 2C</option>
      <option value="BSCS 3A">BSCS 3A</option>
      <option value="BSCS 3B">BSCS 3B</option>
      <option value="BSCS 3C">BSCS 3C</option>
      <option value="BSCS 4A">BSCS 4A</option>
      <option value="BSCS 4B">BSCS 4B</option>
      <option value="BSCS 4C">BSCS 4C</option>
    </optgroup>

  </select>
</div>
                  <div class="col-6">
  <select class="form-control" name="region" required>
    <option value="" disabled selected>Select Region</option>

    <option value="NCR">NCR – National Capital Region</option>
    <option value="CAR">CAR – Cordillera Administrative Region</option>

    <option value="Region I">Region I – Ilocos Region</option>
    <option value="Region II">Region II – Cagayan Valley</option>
    <option value="Region III">Region III – Central Luzon</option>
    <option value="Region IV-A">Region IV-A – CALABARZON</option>
    <option value="Region IV-B">Region IV-B – MIMAROPA</option>
    <option value="Region V">Region V – Bicol Region</option>

    <option value="Region VI">Region VI – Western Visayas</option>
    <option value="Region VII">Region VII – Central Visayas</option>
    <option value="Region VIII">Region VIII – Eastern Visayas</option>

    <option value="Region IX">Region IX – Zamboanga Peninsula</option>
    <option value="Region X">Region X – Northern Mindanao</option>
    <option value="Region XI">Region XI – Davao Region</option>
    <option value="Region XII">Region XII – SOCCSKSARGEN</option>
    <option value="Region XIII">Region XIII – Caraga</option>

    <option value="BARMM">BARMM – Bangsamoro Autonomous Region in Muslim Mindanao</option>
  </select>
</div>
                  <div class="col-8">
                    <select class="form-control" name="address" id="municipalitySelect" required>
                      <option value="" disabled selected>Select Municipality</option>
                    </select>
                  </div>
                  <div class="col-4">
                    <input class="form-control" name="postal_code" id="postalCodeInput" placeholder="Postal code" readonly required>
                  </div>
                  <div class="col-12">
                    <input class="form-control" type="file" name="valid_id" accept="image/*" required>
                    <div class="form-text">Upload valid ID (JPG/PNG, max 5MB)</div>
                  </div>
                  <div class="col-6">
                    <div class="input-group">
                      <input class="form-control" type="password" id="registerPassword" name="password" placeholder="Password (min 8 characters)" autocomplete="new-password" required>
                      <button class="btn btn-outline-secondary js-toggle-password" type="button" data-target="#registerPassword" aria-label="Show password">
                        <i class="fa-regular fa-eye"></i>
                      </button>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="input-group">
                      <input class="form-control" type="password" id="registerConfirmPassword" name="confirm_password" placeholder="Confirm password" autocomplete="new-password" required>
                      <button class="btn btn-outline-secondary js-toggle-password" type="button" data-target="#registerConfirmPassword" aria-label="Show password">
                        <i class="fa-regular fa-eye"></i>
                      </button>
                    </div>
                  </div>
                </div>
                </div>

                <button type="submit" class="btn btn-success w-100 mt-3" id="registerSubmitBtn" disabled>Register</button>
                <div class="text-center text-muted">
                  Already have an account?
                  <a href="#" class="auth-link auth-link-green" data-switch-tab="login">Login here</a>
                </div>
              </form>
            </div>

            <!-- Forgot (secure: email reset link) -->
            <div class="tab-pane fade" id="tab-forgot" role="tabpanel" tabindex="0">
              <form id="forgotForm" class="auth-form">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div data-alerts></div>
                <div class="mb-3">
                  <input type="email" class="form-control form-control-lg" name="email" placeholder="Enter your email" autocomplete="email" required>
                  <div class="form-text">We'll send a password reset link to your email.</div>
                </div>
                <button type="submit" class="btn btn-success w-100">Send reset link</button>
                <div class="text-center text-muted">
                  Remembered your password?
                  <a href="#" class="auth-link auth-link-green" data-switch-tab="login">Login here</a>
                </div>
              </form>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // Landing navbar scroll effect
  document.addEventListener('DOMContentLoaded', function () {
    const navbar = document.querySelector('.navbar-landing');
    window.addEventListener('scroll', () => {
      const scrollY = window.scrollY || window.pageYOffset;
      if (navbar) navbar.classList.toggle('navbar-scrolled', scrollY > 20);
    });

    const typed = document.getElementById('typed-text');
    if (!typed) return;

    const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const rawTexts = (typed.dataset.texts || '').split('|').map(t => t.trim()).filter(Boolean);
    const texts = rawTexts.length ? rawTexts : ['Submit publications, track status, and get updates'];

    if (prefersReduced) {
      typed.textContent = texts[0];
      return;
    }

    let i = 0;
    let j = 0;
    let isDeleting = false;

    function type() {
      if (i >= texts.length) i = 0;

      if (!isDeleting) {
        typed.textContent = texts[i].substring(0, j + 1);
        j++;

        if (typed.textContent === texts[i]) {
          isDeleting = true;
          setTimeout(type, 1400);
          return;
        }
      } else {
        typed.textContent = texts[i].substring(0, j - 1);
        j--;

        if (typed.textContent === '') {
          isDeleting = false;
          i++;
        }
      }

      setTimeout(type, isDeleting ? 45 : 85);
    }

    type();
  });

  document.getElementById("registerForm").addEventListener("submit", function(e){
  e.preventDefault();

  const form = this;
  const formData = new FormData(form);

  fetch("/uccrdc/app/register.php", {
    method: "POST",
    body: formData
  })
  .then(res => res.json())
  .then(data => {

    if(data.success){

      Swal.fire({
        icon: "success",
        title: "Registration Successful",
        text: "Your account has been created. You can now login.",
        confirmButtonColor: "#198754"
      }).then(() => {

        // close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('authModal'));
        modal.hide();

        // switch to login tab
        document.querySelector('[data-switch-tab="login"]').click();

        // reset form
        form.reset();

      });

    } else {

      Swal.fire({
        icon: "error",
        title: "Registration Failed",
        text: data.message || "Something went wrong"
      });

    }

  })
  .catch(() => {
    Swal.fire({
      icon: "error",
      title: "Server Error",
      text: "Please try again later."
    });
  });

});
</script>

<?php require_once __DIR__ . '/includes/footer.php';
