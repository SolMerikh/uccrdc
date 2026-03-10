<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_role(['Author']);

$pageTitle = 'New Submission';
require_once __DIR__ . '/../includes/dashboard_header.php';

$user = current_user();

$formats = db()->query('SELECT id, name FROM publication_formats WHERE is_active = 1 ORDER BY name')->fetchAll();

$stmt = db()->prepare('SELECT
  s.id,
  s.title,
  s.status,
  s.pdf_path,
  s.manuscript_path,
  s.editorialPage_path,
  s.created_at,
  s.starting_date,
  s.frequency,
  s.language,
  s.suggested_retail_price,
  s.formerly_published,
  s.former_title,
  s.former_form_of_publication,
  s.former_starting_date,
  s.former_ending_date,
  s.former_url,
  s.admin_comment,
  (SELECT GROUP_CONCAT(pf.name ORDER BY pf.name SEPARATOR "||")
     FROM submission_formats sf
     JOIN publication_formats pf ON pf.id = sf.format_id
    WHERE sf.submission_id = s.id) AS format_names
FROM submissions s
WHERE s.author_id = ?
ORDER BY s.created_at DESC');
$stmt->execute([$user['id']]);
$submissions = $stmt->fetchAll();

$authorStmt = db()->prepare('SELECT last_name, first_name, email, contact_number, course, address, postal_code, region FROM authors WHERE id = ?');
$authorStmt->execute([$user['id']]);
$author = $authorStmt->fetch() ?: [];
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

<div class="container-fluid p-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold text-success mb-0">My Submissions</h2>
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#publicationModal">
      <i class="fa-solid fa-file-circle-plus me-2"></i>New Submission
    </button>
  </div>
  <div class="row">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
          <h5 class="mb-0"><i class="fa-solid fa-inbox me-2"></i>My Submissions</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive d-none d-md-block">
            <table id="submissionsTable" class="table table-hover mb-0">
              <thead class="table-success">
                <tr>
                  <th>Title</th>
                  <th style="width:140px">Status</th>
                  <th style="width:180px">Submitted</th>
                  <th class="text-center" style="width:140px">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($submissions): ?>
                <?php foreach ($submissions as $s): ?>
                  <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($s['title']) ?></td>
                    <td>
                      <?php
                        $badge = match ($s['status']) {
                          'Approved' => 'success',
                          'Rejected' => 'danger',
                          default => 'warning',
                        };
                      ?>
                      <span class="badge text-bg-<?= $badge ?>"><?= htmlspecialchars($s['status']) ?></span>
                    </td>
                    <td class="text-muted small"><?= htmlspecialchars($s['created_at']) ?></td>
                    <td class="text-center">
                      <button
                        type="button"
                        class="btn btn-outline-secondary btn-sm view-submission-btn"
                        data-bs-toggle="modal"
                        data-bs-target="#viewSubmissionModal"
                        data-id="<?= (int)$s['id'] ?>"
                        data-title="<?= htmlspecialchars($s['title'], ENT_QUOTES) ?>"
                        data-status="<?= htmlspecialchars($s['status'], ENT_QUOTES) ?>"
                        data-pdf="<?= htmlspecialchars($s['pdf_path'] ?? '', ENT_QUOTES) ?>"
                        data-manuscript="<?= htmlspecialchars($s['manuscript_path'] ?? '', ENT_QUOTES) ?>"
                        data-editorial="<?= htmlspecialchars($s['editorial_page_path'] ?? '', ENT_QUOTES) ?>"
                        data-submitted="<?= htmlspecialchars($s['created_at'], ENT_QUOTES) ?>"
                        data-starting="<?= htmlspecialchars($s['starting_date'] ?? '', ENT_QUOTES) ?>"
                        data-frequency="<?= htmlspecialchars($s['frequency'] ?? '', ENT_QUOTES) ?>"
                        data-language="<?= htmlspecialchars($s['language'] ?? '', ENT_QUOTES) ?>"
                        data-price="<?= htmlspecialchars((string)($s['suggested_retail_price'] ?? ''), ENT_QUOTES) ?>"
                        data-formats="<?= htmlspecialchars((string)($s['format_names'] ?? ''), ENT_QUOTES) ?>"
                        data-formerly="<?= (int)($s['formerly_published'] ?? 0) ?>"
                        data-former-title="<?= htmlspecialchars((string)($s['former_title'] ?? ''), ENT_QUOTES) ?>"
                        data-former-form="<?= htmlspecialchars((string)($s['former_form_of_publication'] ?? ''), ENT_QUOTES) ?>"
                        data-former-start="<?= htmlspecialchars((string)($s['former_starting_date'] ?? ''), ENT_QUOTES) ?>"
                        data-former-end="<?= htmlspecialchars((string)($s['former_ending_date'] ?? ''), ENT_QUOTES) ?>"
                        data-former-url="<?= htmlspecialchars((string)($s['former_url'] ?? ''), ENT_QUOTES) ?>"
                        data-comment="<?= htmlspecialchars((string)($s['admin_comment'] ?? ''), ENT_QUOTES) ?>"
                      >
                        <i class="fa-solid fa-eye"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="d-block d-md-none mt-3">
            <?php if ($submissions): ?>
              <?php foreach ($submissions as $s): ?>
                <?php $collapseId = 'submissionMobile' . (int)$s['id']; ?>
                <div class="card mb-2 border-0 shadow-sm">
                  <button class="btn w-100 text-start p-3 d-flex align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="false">
                    <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px;">
                      <i class="fa-solid fa-chevron-down"></i>
                    </div>
                    <div class="flex-grow-1">
                      <div class="fw-semibold"><?= htmlspecialchars($s['title']) ?></div>
                    </div>
                  </button>
                  <div id="<?= $collapseId ?>" class="collapse border-top px-3 pt-2 pb-3">
                    <div class="mb-2">
                      <small class="text-muted d-block">Status</small>
                      <span class="badge text-bg-<?= $badge = match ($s['status']) { 'Approved' => 'success', 'Rejected' => 'danger', default => 'warning', } ?>"><?= htmlspecialchars($s['status']) ?></span>
                    </div>
                    <div class="mb-2">
                      <small class="text-muted d-block">Submitted</small>
                      <span><?= htmlspecialchars($s['created_at']) ?></span>
                    </div>
                    <div class="mt-2">
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary view-submission-btn"
                        data-bs-toggle="modal"
                        data-bs-target="#viewSubmissionModal"
                        data-id="<?= (int)$s['id'] ?>"
                        data-title="<?= htmlspecialchars($s['title'], ENT_QUOTES) ?>"
                        data-status="<?= htmlspecialchars($s['status'], ENT_QUOTES) ?>"
                        data-submitted="<?= htmlspecialchars($s['created_at'], ENT_QUOTES) ?>"
                        data-starting="<?= htmlspecialchars($s['starting_date'] ?? '', ENT_QUOTES) ?>"
                        data-frequency="<?= htmlspecialchars($s['frequency'] ?? '', ENT_QUOTES) ?>"
                        data-language="<?= htmlspecialchars($s['language'] ?? '', ENT_QUOTES) ?>"
                        data-price="<?= htmlspecialchars((string)($s['suggested_retail_price'] ?? ''), ENT_QUOTES) ?>"
                        data-formats="<?= htmlspecialchars((string)($s['format_names'] ?? ''), ENT_QUOTES) ?>"
                        data-formerly="<?= (int)($s['formerly_published'] ?? 0) ?>"
                        data-former-title="<?= htmlspecialchars((string)($s['former_title'] ?? ''), ENT_QUOTES) ?>"
                        data-former-form="<?= htmlspecialchars((string)($s['former_form_of_publication'] ?? ''), ENT_QUOTES) ?>"
                        data-former-start="<?= htmlspecialchars((string)($s['former_starting_date'] ?? ''), ENT_QUOTES) ?>"
                        data-former-end="<?= htmlspecialchars((string)($s['former_ending_date'] ?? ''), ENT_QUOTES) ?>"
                        data-former-url="<?= htmlspecialchars((string)($s['former_url'] ?? ''), ENT_QUOTES) ?>"
                        data-comment="<?= htmlspecialchars((string)($s['admin_comment'] ?? ''), ENT_QUOTES) ?>"
                      >
                        <i class="fa-solid fa-eye"></i> View
                      </button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="text-muted mb-0">No submissions yet.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- View Submission Modal -->
<div class="modal fade" id="viewSubmissionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <div>
          <h5 class="modal-title mb-0"><i class="fa-solid fa-eye me-2"></i>Submission Details</h5>
          <div class="small opacity-75">Review your submitted publication information.</div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-lg-8">
            <div class="card shadow-sm">
              <div class="card-header bg-white d-flex align-items-center justify-content-between">
                <div class="fw-semibold"><i class="fa-solid fa-file-lines me-2"></i>Publication Details</div>
                <span class="badge" id="viewStatusBadge">-</span>
              </div>
              <div class="card-body">
                  <div class="mb-3">
                  <div class="text-muted small">Title</div>
                  <div class="h5 mb-0" id="viewTitle">-</div>
                </div>

                <div class="row g-3">

                  <div class="col-md-4">
                    <div class="text-muted small">
                      <i class="fa-solid fa-rotate me-1"></i>Frequency
                    </div>
                    <div class="fw-semibold" id="viewFrequency">-</div>
                  </div>

                  <div class="col-md-4">
                    <div class="text-muted small">
                      <i class="fa-solid fa-language me-1"></i>Language
                    </div>
                    <div class="fw-semibold" id="viewLanguage">-</div>
                  </div>

                  <div class="col-md-4">
                    <div class="text-muted small">
                      <i class="fa-solid fa-tag me-1"></i>Suggested Retail Price
                    </div>
                    <div class="fw-semibold" id="viewPrice">-</div>
                  </div>

                  <div class="col-12">
                    <div class="text-muted small"><i class="fa-solid fa-layer-group me-1"></i>Format</div>
                    <div class="mt-1" id="viewFormats"></div>
                  </div><div class="col-12">
  <div class="text-muted small">
    <i class="fa-solid fa-clock-rotate-left me-1"></i>Former Publication
  </div>
  <div class="fw-semibold" id="viewFormerFlag">-</div>
</div>

<div class="col-12 d-none" id="viewFormerBlock">
  <div class="border rounded-3 p-3 bg-light">
    <div class="fw-semibold mb-2">Former Publication Details</div>

    <div class="text-muted small">Former Title</div>
    <div class="fw-semibold mb-2" id="viewFormerTitle">-</div>
    
    <!-- NEW URL FIELD -->
    <div class="mb-2 d-none" id="viewFormerUrlBlock">
      <div class="text-muted small">Former URL</div>
      <div class="fw-semibold">
        <a href="#" target="_blank" id="viewFormerUrl">-</a>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-md-6 d-none" id="viewFormerFormBlock">
        <div class="text-muted small">Former Form of Publication</div>
        <div class="fw-semibold" id="viewFormerForm">-</div>
      </div>

      <div class="col-md-3 d-none" id="viewFormerStartBlock">
        <div class="text-muted small">Former Start</div>
        <div class="fw-semibold" id="viewFormerStart">-</div>
      </div>

      <div class="col-md-3 d-none" id="viewFormerEndBlock">
        <div class="text-muted small">Former End</div>
        <div class="fw-semibold" id="viewFormerEnd">-</div>
      </div>
    </div>

  </div>
</div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card shadow-sm">
              <div class="card-header bg-white fw-semibold"><i class="fa-solid fa-circle-info me-2"></i>Files & Notes</div>
              <div class="card-body">
                <div class="mb-3">
                  <div class="text-muted small">Submitted</div>
                  <div class="fw-semibold" id="viewSubmitted">-</div>
                </div>

                <div class="mb-2">
                    <div class="text-muted small">Cover</div>
                    <a id="viewPdfLink" href="#" target="_blank" class="btn btn-outline-secondary w-100">
                      <i class="fa-solid fa-file-pdf text-danger me-1"></i> View Cover
                    </a>
                  </div>

                  <div class="mb-2">
                    <div class="text-muted small">Manuscript File</div>
                    <a id="viewManuscriptLink" href="#" target="_blank" class="btn btn-outline-secondary w-100">
                      <i class="fa-solid fa-file-lines me-1"></i> View Manuscript
                    </a>
                  </div>

                  <div class="mb-3">
                    <div class="text-muted small">Editorial Page</div>
                    <a id="viewEditorialLink" href="#" target="_blank" class="btn btn-outline-secondary w-100">
                      <i class="fa-solid fa-file-signature me-1"></i> View Editorial Page
                    </a>
                  </div>

                <div>
                  <div class="text-muted small">Admin Comment</div>
                  <div class="border rounded-3 p-3 bg-light small" id="viewComment">—</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
    <button type="button" id="resubmitBtn" class="btn btn-success d-none">
        <i class="fa-solid fa-rotate-left me-2"></i>Re-submit
    </button>
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
</div>
    </div>
  </div>
</div>

<!-- Publication Details Modal -->
<div class="modal fade" id="publicationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <div>
          <h5 class="modal-title mb-0"><i class="fa-solid fa-file-circle-plus me-2"></i>Publication Details</h5>
          <div class="small opacity-75">Fill out the details before submitting for review.</div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="/uccrdc/author/submit.php" enctype="multipart/form-data" id="publicationForm">
        <div class="modal-body">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

          <div class="row g-4">
            <div class="col-lg-8">
              <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="fa-solid fa-pen-to-square me-2"></i>Publication Information</div>
                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-12">
                      <label class="form-label fw-semibold">Title of Publication <span class="text-danger">*</span></label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-heading"></i></span>
                        <input class="form-control" name="title" required placeholder="Enter the publication title">
                      </div>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold">Starting Date <span class="text-danger">*</span></label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="fa-regular fa-calendar"></i></span>
                        <input class="form-control" type="date" name="starting_date" required>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold">Frequency <span class="text-danger">*</span></label>
                      <select class="form-select" name="frequency" required>
                        <option value="">Select Frequency</option>
                        <option>Weekly</option>
                    <option>Biweekly</option>
                    <option>Monthly</option>
                    <option>Bi-Monthly</option>
                    <option>Semi-annual</option>
                    <option>Bi-annual</option>
                    <option>Triannual</option>
                    <option>Semi-quarterly</option>
                    <option>Quarterly</option>
                    <option>Annually</option>
                    <option>Biennial</option>
                    <option>Triennial</option>
                    <option>Quadrennial</option>
                    <option>Quinquennial</option>
                    <option>Sexennial</option>
                    <option>Septennial</option>
                    <option>Octennial</option>
                    <option>Novennial</option>
                    <option>Decennial</option>
                    <option>Semestral</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold">Language <span class="text-danger">*</span></label>
                      <select class="form-select" name="language" required>
                        <option value="">Select Language</option>

                      <option>English</option>
                      <option>Filipino</option>
                      <option>Spanish</option>
                      <option>Chinese</option>
                      <option>Mandarin</option>
                      <option>Japanese</option>
                      <option>Korean</option>
                      <option>Arabic</option>
                      <option>French</option>
                      <option>German</option>
                      <option>Portuguese</option>
                      <option>Russian</option>
                      <option>Italian</option>
                      <option>Hindi</option>
                      <option>Bengali</option>
                      <option>Punjabi</option>
                      <option>Urdu</option>
                      <option>Turkish</option>
                      <option>Vietnamese</option>
                      <option>Thai</option>
                      <option>Indonesian</option>
                      <option>Malay</option>
                      <option>Dutch</option>
                      <option>Greek</option>
                      <option>Hebrew</option>
                      <option>Swedish</option>
                      <option>Norwegian</option>
                      <option>Danish</option>
                      <option>Finnish</option>
                      <option>Polish</option>
                      <option>Czech</option>
                      <option>Hungarian</option>
                      <option>Romanian</option>
                      <option>Ukrainian</option>
                      <option>Persian</option>
                      <option>Swahili</option>
                      <option>Tamil</option>
                      <option>Telugu</option>
                      <option>Marathi</option>
                      </select>
                    </div>
                  
                    <div class="col-md-4">
                      <label class="form-label">Suggested Retail Price <span class="text-muted small">(optional)</span></label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-tag"></i></span>
                        <input class="form-control" type="number" name="suggested_retail_price" step="1" min="0" placeholder="e.g., 199">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-lg-4">
              <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="fa-solid fa-clock-rotate-left me-2"></i>Former Publication</div>
                <div class="card-body">
                  <div class="text-muted small mb-2">Was this publication formerly published under a different title?</div>
                  <div class="btn-group w-100" role="group" aria-label="Former publication">
                    <input class="btn-check" type="radio" name="formerly_published" id="modal_formerly_no" value="0" checked>
                    <label class="btn btn-outline-secondary" for="modal_formerly_no"><i class="fa-solid fa-ban me-1"></i>No</label>

                    <input class="btn-check" type="radio" name="formerly_published" id="modal_formerly_yes" value="1">
                    <label class="btn btn-outline-secondary" for="modal_formerly_yes"><i class="fa-solid fa-check me-1"></i>Yes</label>
                  </div>

                  <div id="modalFormerPublicationSection" class="row g-3 mt-3 d-none">
                    <div class="col-12">
                      <label class="form-label">Former Title</label>
                      <input class="form-control" name="former_title" disabled>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Form of Publication (text)</label>
                      <input class="form-control" name="former_form_of_publication" disabled placeholder="e.g., Print">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Starting Date</label>
                      <input class="form-control" type="date" name="former_starting_date" disabled>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Ending Date</label>
                      <input class="form-control" type="date" name="former_ending_date" disabled>
                    </div>

                      <div class="col-12">
                      <label class="form-label">Former URL</label>
                      <input
                        class="form-control"
                        type="url"
                        name="former_url"
                        placeholder="https://example.com"
                        disabled
                      >
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="fa-solid fa-layer-group me-2"></i>Form of Publication</div>
                <div class="card-body">
                  <div class="text-muted small mb-2">Select at least one format.</div>
                  <div class="row">
                    <?php foreach ($formats as $f): ?>
                      <div class="col-md-4">
                        <div class="border rounded-3 p-2 bg-light h-100">
                          <div class="form-check m-0">
                            <input class="form-check-input" type="checkbox" name="format_ids[]" id="modal_fmt<?= (int)$f['id'] ?>" value="<?= (int)$f['id'] ?>">
                            <label class="form-check-label fw-semibold" for="modal_fmt<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['name']) ?></label>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12">
  <div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">
      <i class="fa-solid fa-file-pdf me-2"></i>Publication Files
    </div>

<div class="card-body">

  <!-- Publication PDF -->
  <div class="mb-3">
    <label class="form-label">PDF File of Cover <span class="text-danger">*</span></label>
    <div class="input-group">
      <span class="input-group-text"><i class="fa-solid fa-paperclip"></i></span>
      <input class="form-control" type="file" name="publication_pdf" accept="application/pdf,.pdf" required>
    </div>
    <div class="form-text">Upload the final cover PDF. Max 20MB.</div>
  </div>

  <!-- Manuscript -->
  <div class="mb-3">
    <label class="form-label">Manuscript File <span class="text-danger">*</span></label>
    <div class="input-group">
      <span class="input-group-text"><i class="fa-solid fa-file-lines"></i></span>
      <input class="form-control" type="file" name="manuscript_file" accept="application/pdf,.pdf" required>
    </div>
    <div class="form-text">Upload the manuscript version of the publication.</div>
  </div>

  <!-- Editorial Page -->
  <div>
    <label class="form-label">Editorial Page File <span class="text-danger">*</span></label>
    <div class="input-group">
      <span class="input-group-text"><i class="fa-solid fa-file-signature"></i></span>
      <input class="form-control" type="file" name="editorial_page_file" accept="application/pdf,.pdf" required>
    </div>
    <div class="form-text">Upload the editorial board page.</div>
  </div>

</div>

  </div>
</div>



          </div>
        </div>
        <div class="modal-footer bg-white d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-success px-4" type="button" id="previewBtn"><i class="fa-solid fa-magnifying-glass me-2"></i>Preview</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Preview Modal -->
<!-- Preview Modal -->

<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content">


  <div class="modal-header bg-success text-white">
    <div>
      <h5 class="modal-title mb-0">
        <i class="fa-solid fa-eye me-2"></i>Preview Submission
      </h5>
      <div class="small opacity-75">
        Double-check everything before submitting.
      </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>

  <div class="modal-body">

    <div class="row g-4">

      <!-- AUTHOR INFORMATION -->
      <div class="col-lg-5">

        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold">
            <i class="fa-solid fa-user me-2"></i>Author Information
          </div>

          <div class="card-body">

            <div class="mb-3">
              <div class="text-muted small">Name</div>
              <div class="fw-semibold">
                <?= htmlspecialchars(($author['first_name'] ?? '') . ' ' . ($author['last_name'] ?? '')) ?>
              </div>
            </div>

            <div class="mb-3">
              <div class="text-muted small">Email</div>
              <div><?= htmlspecialchars($author['email'] ?? '') ?></div>
            </div>

            <div class="mb-3">
              <div class="text-muted small">Contact Number</div>
              <div><?= htmlspecialchars($author['contact_number'] ?? '') ?></div>
            </div>

            <div class="mb-3">
              <div class="text-muted small">Course</div>
              <div><?= htmlspecialchars($author['course'] ?? '-') ?></div>
            </div>

            <div class="mb-3">
              <div class="text-muted small">Address</div>
              <div><?= htmlspecialchars($author['address'] ?? '') ?></div>
            </div>

            <div>
              <div class="text-muted small">Postal Code / Region</div>
              <div>
                <?= htmlspecialchars(($author['postal_code'] ?? '') . ' / ' . ($author['region'] ?? '')) ?>
              </div>
            </div>

          </div>
        </div>

        <div class="alert alert-warning mt-3 mb-0">
          <i class="fa-solid fa-circle-info me-1"></i>
          Submissions are saved with status <b>Pending</b> until reviewed by Admin.
        </div>

      </div>


      <!-- PUBLICATION DETAILS -->
      <div class="col-lg-7">

        <div class="card shadow-sm">

          <div class="card-header bg-white fw-semibold">
            <i class="fa-solid fa-file-lines me-2"></i>Publication Details
          </div>

          <div class="card-body">

            <!-- TITLE -->
            <div class="mb-4">
              <div class="text-muted small">Title</div>
              <div class="h5 mb-0" id="previewTitle">-</div>
            </div>

            <!-- BASIC DETAILS -->
            <div class="row g-3 mb-3">

              <div class="col-md-4">
                <div class="text-muted small">
                  <i class="fa-regular fa-calendar me-1"></i>Starting Date
                </div>
                <div class="fw-semibold" id="previewStart">-</div>
              </div>

              <div class="col-md-4">
                <div class="text-muted small">
                  <i class="fa-solid fa-rotate me-1"></i>Frequency
                </div>
                <div class="fw-semibold" id="previewFrequency">-</div>
              </div>

              <div class="col-md-4">
                <div class="text-muted small">
                  <i class="fa-solid fa-language me-1"></i>Language
                </div>
                <div class="fw-semibold" id="previewLanguage">-</div>
              </div>

            </div>

            <!-- PRICE -->
            <div class="mb-3">
              <div class="text-muted small">
                <i class="fa-solid fa-tag me-1"></i>Suggested Retail Price
              </div>
              <div class="fw-semibold" id="previewPrice">-</div>
            </div>

            <!-- FORMAT -->
            <div class="mb-3">
              <div class="text-muted small">
                <i class="fa-solid fa-layer-group me-1"></i>Selected Format
              </div>
              <div id="previewFormats" class="mt-1"></div>
            </div>

            <!-- FORMER PUBLICATION -->
            <div class="mb-2">
              <div class="text-muted small">
                <i class="fa-solid fa-clock-rotate-left me-1"></i>Former Publication
              </div>
              <div id="previewFormerFlag">-</div>
            </div>

            <!-- FORMER PUBLICATION DETAILS -->
            <div id="previewFormerBlock" class="border rounded-3 p-3 bg-light mt-2 d-none">

              <div class="fw-semibold mb-2">
                Former Publication Details
              </div>

              <div class="mb-2">
                <div class="text-muted small">Former Title</div>
                <div class="fw-semibold" id="previewFormerTitle">-</div>
              </div>

              <div class="row g-3">

                <div class="col-md-6">
                  <div class="text-muted small">Former Form of Publication</div>
                  <div class="fw-semibold" id="previewFormerForm">-</div>
                </div>

                <div class="col-md-3">
                  <div class="text-muted small">Former Start</div>
                  <div class="fw-semibold" id="previewFormerStart">-</div>
                </div>

                <div class="col-md-3">
                  <div class="text-muted small">Former End</div>
                  <div class="fw-semibold" id="previewFormerEnd">-</div>
                </div>

              </div>

            </div>

            <!-- FILE -->
            <div class="mt-3">
              <div class="text-muted small">
                <i class="fa-solid fa-paperclip me-1"></i>Uploaded Files
              </div>
              <div class="fw-semibold" id="previewFile">-</div>
            </div>
          
          </div>
        </div>

      </div>

    </div>

  </div>

  <div class="modal-footer bg-white d-flex justify-content-end gap-2">

    <button type="button" class="btn btn-outline-secondary" id="editFromPreview">
      <i class="fa-solid fa-pen me-1"></i>Edit
    </button>

    <button class="btn btn-success" type="submit" form="publicationForm">
      <i class="fa-solid fa-paper-plane me-1"></i>Submit
    </button>

  </div>

</div>


  </div>
</div>

<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Helper function for price formatting
    function formatWholePrice(value) {
        const v = (value ?? '').toString().trim();
        if (v === '') return '';
        const num = Number(v);
        if (!Number.isFinite(num)) return v;
        return String(Math.trunc(num));
    }

    // Initialize DataTable
    if (window.jQuery && jQuery.fn?.DataTable) {
        jQuery('#submissionsTable').DataTable({
            order: [[2, 'desc']],
            pageLength: 10,
            language: {
                search: 'Search submissions:',
                lengthMenu: 'Show _MENU_ submissions per page',
                info: 'Showing _START_ to _END_ of _TOTAL_ submissions',
                emptyTable: 'No submissions found.'
            }
        });
    }

    // Form and Modal Elements
    const form = document.getElementById('publicationForm');
    const previewBtn = document.getElementById('previewBtn');
    const previewModalEl = document.getElementById('previewModal');
    const publicationModalEl = document.getElementById('publicationModal');
    const editFromPreview = document.getElementById('editFromPreview');

    const yes = document.getElementById('modal_formerly_yes');
    const no = document.getElementById('modal_formerly_no');
    const section = document.getElementById('modalFormerPublicationSection');

    // Toggle logic for Former Publication section
    function toggleFormerFields(isYes) {
        if (!section) return;
        section.classList.toggle('d-none', !isYes);
        section.querySelectorAll('input').forEach((input) => {
            input.disabled = !isYes;
        });
    }

    if (yes && no) {
        yes.addEventListener('change', () => toggleFormerFields(true));
        no.addEventListener('change', () => toggleFormerFields(false));
        toggleFormerFields(false);
    }

    // --- PREVIEW LOGIC ---
    if (previewBtn && form && previewModalEl) {
        previewBtn.addEventListener('click', function () {
            if (!form.reportValidity()) return;

            const formatsChecked = form.querySelectorAll('input[name="format_ids[]"]:checked');
            if (!formatsChecked.length) {
                alert('Please select at least one Form of Publication.');
                return;
            }

            if (yes && yes.checked) {
                const formerTitle = form.querySelector('input[name="former_title"]');
                const formerForm = form.querySelector('input[name="former_form_of_publication"]');
                if (!formerTitle?.value || !formerForm?.value) {
                    alert('Former Title and Former Form of Publication are required when formerly published is YES.');
                    return;
                }
            }

            document.getElementById('previewTitle').textContent = form.querySelector('input[name="title"]').value || '-';
            document.getElementById('previewStart').textContent = form.querySelector('input[name="starting_date"]').value || '-';
            document.getElementById('previewFrequency').textContent = form.querySelector('select[name="frequency"]').value || '-';
            document.getElementById('previewLanguage').textContent = form.querySelector('select[name="language"]').value || '-';
            document.getElementById('previewPrice').textContent = formatWholePrice(form.querySelector('input[name="suggested_retail_price"]').value) || '-';

            const formatsWrap = document.getElementById('previewFormats');
            formatsWrap.innerHTML = '';
            formatsChecked.forEach((cb) => {
                const label = form.querySelector(`label[for="${cb.id}"]`);
                const badge = document.createElement('span');
                badge.className = 'badge text-bg-secondary me-1';
                badge.textContent = label ? label.textContent : cb.value;
                formatsWrap.appendChild(badge);
            });

            const formerBlock = document.getElementById('previewFormerBlock');
            const formerFlag = document.getElementById('previewFormerFlag');
            if (yes && yes.checked) {
                formerFlag.innerHTML = '<span class="badge text-bg-info">YES</span>';
                formerBlock.classList.remove('d-none');
                document.getElementById('previewFormerTitle').textContent = form.querySelector('input[name="former_title"]').value || '-';
                document.getElementById('previewFormerForm').textContent = form.querySelector('input[name="former_form_of_publication"]').value || '-';
                document.getElementById('previewFormerStart').textContent = form.querySelector('input[name="former_starting_date"]').value || '-';
                document.getElementById('previewFormerEnd').textContent = form.querySelector('input[name="former_ending_date"]').value || '-';
                
                const formerUrlInput = form.querySelector('input[name="former_url"]').value || '';
                const previewUrl = document.getElementById('previewFormerUrl');
                const previewUrlBlock = document.getElementById('previewFormerUrlBlock');

                if (formerUrlInput) {
                    previewUrlBlock.classList.remove('d-none');
                    previewUrl.href = formerUrlInput;
                    previewUrl.textContent = formerUrlInput;
                } else {
                    previewUrlBlock.classList.add('d-none');
                }
            } else {
                formerFlag.innerHTML = '<span class="badge text-bg-light">NO</span>';
                formerBlock.classList.add('d-none');
            }

            const pubFile = form.querySelector('input[name="publication_pdf"]')?.files?.[0]?.name || 'None';
            const manuscriptFile = form.querySelector('input[name="manuscript_file"]')?.files?.[0]?.name || 'None';
            const editorialFile = form.querySelector('input[name="editorial_page_file"]')?.files?.[0]?.name || 'None';

            document.getElementById('previewFile').innerHTML =
                `<b>Cover:</b> ${pubFile}<br>
                <b>Manuscript:</b> ${manuscriptFile}<br>
                <b>Editorial Page:</b> ${editorialFile}`;

            bootstrap.Modal.getOrCreateInstance(previewModalEl).show();
        });
    }

    if (editFromPreview && previewModalEl && publicationModalEl) {
        editFromPreview.addEventListener('click', function () {
            bootstrap.Modal.getOrCreateInstance(previewModalEl).hide();
            bootstrap.Modal.getOrCreateInstance(publicationModalEl).show();
        });
    }

    // --- VIEW SUBMISSION DETAILS LOGIC ---
    document.querySelectorAll('.view-submission-btn').forEach((btn) => {
        btn.addEventListener('click', function () {
            const data = btn.dataset;
            const status = data.status || '-';

            // 1. Badge Handling
            const badgeMap = { Approved: 'text-bg-success', Rejected: 'text-bg-danger', Pending: 'text-bg-warning' };
            const statusBadge = document.getElementById('viewStatusBadge');
            statusBadge.className = `badge ${badgeMap[status] || 'text-bg-secondary'}`;
            statusBadge.textContent = status;

            // 2. Basic Information
            document.getElementById('viewTitle').textContent = data.title || '-';
            document.getElementById('viewSubmitted').textContent = data.submitted || '-';
            document.getElementById('viewFrequency').textContent = data.frequency || '-';
            document.getElementById('viewLanguage').textContent = data.language || '-';
            document.getElementById('viewPrice').textContent = formatWholePrice(data.price) || '-';

            // 3. Formats Badges
            const formatsWrap = document.getElementById('viewFormats');
            formatsWrap.innerHTML = '';
            const formats = (data.formats || '').split('||').filter(Boolean);
            if (formats.length) {
                formats.forEach((name) => {
                    const badge = document.createElement('span');
                    badge.className = 'badge text-bg-secondary me-1';
                    badge.textContent = name;
                    formatsWrap.appendChild(badge);
                });
            } else {
                formatsWrap.textContent = '-';
            }

            // 4. Former Publication Handling
            const isFormer = data.formerly === '1';
            document.getElementById('viewFormerFlag').textContent = isFormer ? 'Yes' : 'No';
            document.getElementById('viewFormerBlock').classList.toggle('d-none', !isFormer);
            document.getElementById('viewFormerTitle').textContent = data.formerTitle || '-';
            document.getElementById('viewFormerForm').textContent = data.formerForm || '-';
            document.getElementById('viewFormerStart').textContent = data.formerStart || '-';
            document.getElementById('viewFormerEnd').textContent = data.formerEnd || '-';

            const urlBlock = document.getElementById('viewFormerUrlBlock');
            const urlLink = document.getElementById('viewFormerUrl');
            if (data.formerUrl) {
                urlBlock.classList.remove('d-none');
                urlLink.href = data.formerUrl;
                urlLink.textContent = data.formerUrl;
            } else {
                urlBlock.classList.add('d-none');
            }

            // 5. PDF Link & Comments
            document.getElementById('viewPdfLink').href = `/uccrdc/files/submission_pdf.php?id=${data.id}`;
            document.getElementById('viewPdfLink').href =
                `/uccrdc/files/submission_pdf.php?id=${data.id}&type=publication`;

                document.getElementById('viewManuscriptLink').href =
                `/uccrdc/files/submission_pdf.php?id=${data.id}&type=manuscript`;

                document.getElementById('viewEditorialLink').href =
                `/uccrdc/files/submission_pdf.php?id=${data.id}&type=editorial`;
            const comment = data.comment || '—';
            document.getElementById('viewComment').textContent = comment === '' ? '—' : comment;

            // 6. RE-SUBMIT BUTTON LOGIC
            const resubmitBtn = document.getElementById('resubmitBtn');
            if (status === 'Rejected') {
                resubmitBtn.classList.remove('d-none');
                resubmitBtn.onclick = function() {
                    populateFormForEdit(data);
                };
            } else {
                resubmitBtn.classList.add('d-none');
            }
        });
    });

    // --- POPULATE FORM FOR RE-SUBMISSION ---
    function populateFormForEdit(data) {
        const form = document.getElementById('publicationForm');
        if (!form) return;

        // Populate Main Fields
        form.querySelector('[name="title"]').value = data.title || '';
        form.querySelector('[name="starting_date"]').value = data.startingDate || '';
        form.querySelector('[name="frequency"]').value = data.frequency || '';
        form.querySelector('[name="language"]').value = data.language || '';
        form.querySelector('[name="suggested_retail_price"]').value = Math.trunc(data.price || 0);

        // Populate Checkboxes
        const selectedFormats = (data.formats || '').split('||');
        form.querySelectorAll('input[name="format_ids[]"]').forEach(cb => {
            const label = form.querySelector(`label[for="${cb.id}"]`)?.textContent.trim();
            cb.checked = selectedFormats.includes(label);
        });

        // Former Publication Section
        if (data.formerly === '1') {
            document.getElementById('modal_formerly_yes').checked = true;
            toggleFormerFields(true);
            form.querySelector('[name="former_title"]').value = data.formerTitle || '';
            form.querySelector('[name="former_form_of_publication"]').value = data.formerForm || '';
            form.querySelector('[name="former_starting_date"]').value = data.formerStart || '';
            form.querySelector('[name="former_ending_date"]').value = data.formerEnd || '';
            form.querySelector('[name="former_url"]').value = data.formerUrl || '';
        } else {
            document.getElementById('modal_formerly_no').checked = true;
            toggleFormerFields(false);
        }

        // Hidden Resubmission ID
        let idInput = form.querySelector('[name="resubmission_id"]');
        if (!idInput) {
            idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'resubmission_id';
            form.appendChild(idInput);
        }
        idInput.value = data.id;

        // UI Transition
        bootstrap.Modal.getInstance(document.getElementById('viewSubmissionModal')).hide();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('publicationModal')).show();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>

