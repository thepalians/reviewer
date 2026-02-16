<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$current_page = 'bulk-upload';

// Fetch upload history
$upload_history = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM bulk_upload_history 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute();
    $upload_history = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch upload history error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Task Upload - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php include __DIR__ . '/includes/styles.php'; ?>
    <style>
        .upload-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            background: #f8fafc;
            transition: all 0.3s;
            cursor: pointer;
        }
        .upload-zone:hover {
            border-color: #667eea;
            background: #f1f5f9;
        }
        .upload-zone.dragging {
            border-color: #667eea;
            background: #e0e7ff;
        }
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        #preview-table-container {
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
        }
        .progress-container {
            display: none;
            margin-top: 20px;
        }
        .error-row {
            background-color: #fef2f2;
        }
        .upload-actions {
            margin-top: 20px;
            display: none;
        }
        .results-container {
            display: none;
            margin-top: 20px;
        }
        .stat-box {
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            color: white;
        }
        .stat-box.success {
            background: linear-gradient(135deg, #059669, #047857);
        }
        .stat-box.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }
        .stat-box.total {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
        .stat-box h3 {
            font-size: 32px;
            margin: 0;
        }
        .stat-box p {
            margin: 5px 0 0;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <div>
                    <h1 class="page-title"><i class="bi bi-upload"></i> Bulk Task Upload</h1>
                    <p class="page-subtitle">Upload CSV/Excel files to assign multiple tasks at once</p>
                </div>
                <div class="header-actions">
                    <a href="download-template.php" 
                       class="header-btn secondary">
                        <i class="bi bi-download"></i> Download Template
                    </a>
                </div>
            </div>

            <!-- Upload Section -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Upload File</h5>
                </div>
                <div class="card-body">
                    <div class="upload-zone" id="uploadZone">
                        <i class="bi bi-cloud-upload" style="font-size: 48px; color: #667eea;"></i>
                        <h4 class="mt-3">Drag & Drop CSV file here</h4>
                        <p class="text-muted">or click to browse</p>
                        <input type="file" id="fileInput" accept=".csv" style="display: none;">
                    </div>

                    <div id="file-info" class="mt-3" style="display: none;">
                        <div class="alert alert-info">
                            <i class="bi bi-file-earmark-text"></i>
                            <strong>Selected File:</strong> <span id="fileName"></span>
                            <span class="text-muted">(<span id="fileSize"></span>)</span>
                        </div>
                    </div>

                    <!-- Preview Table -->
                    <div id="preview-table-container"></div>

                    <!-- Upload Actions -->
                    <div class="upload-actions" id="uploadActions">
                        <button type="button" class="btn btn-primary btn-lg" id="uploadBtn">
                            <i class="bi bi-upload"></i> Upload & Process
                        </button>
                        <button type="button" class="btn btn-secondary" id="cancelBtn">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                    </div>

                    <!-- Progress Bar -->
                    <div class="progress-container" id="progressContainer">
                        <h5>Processing...</h5>
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 id="progressBar" 
                                 role="progressbar" 
                                 style="width: 0%">
                                0%
                            </div>
                        </div>
                        <p class="text-muted mt-2" id="progressText">Initializing...</p>
                    </div>

                    <!-- Results -->
                    <div class="results-container" id="resultsContainer">
                        <h5 class="mb-3">Upload Results</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="stat-box total">
                                    <h3 id="totalRows">0</h3>
                                    <p>Total Rows</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-box success">
                                    <h3 id="successCount">0</h3>
                                    <p>Successfully Processed</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-box error">
                                    <h3 id="errorCount">0</h3>
                                    <p>Errors</p>
                                </div>
                            </div>
                        </div>

                        <!-- Error Details -->
                        <div id="errorDetails" class="mt-4" style="display: none;">
                            <h6 class="text-danger">Error Details:</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Row</th>
                                            <th>Error</th>
                                        </tr>
                                    </thead>
                                    <tbody id="errorTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload History -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Upload History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($upload_history)): ?>
                        <p class="text-muted text-center py-4">No upload history yet</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Filename</th>
                                        <th>Date</th>
                                        <th>Total Rows</th>
                                        <th>Success</th>
                                        <th>Errors</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upload_history as $upload): ?>
                                        <tr>
                                            <td><?= $upload['id'] ?></td>
                                            <td><?= htmlspecialchars($upload['filename']) ?></td>
                                            <td><?= date('Y-m-d H:i', strtotime($upload['created_at'])) ?></td>
                                            <td><?= $upload['total_rows'] ?></td>
                                            <td><span class="badge bg-success"><?= $upload['success_count'] ?></span></td>
                                            <td><span class="badge bg-danger"><?= $upload['error_count'] ?></span></td>
                                            <td>
                                                <?php
                                                $statusClass = [
                                                    'completed' => 'bg-success',
                                                    'processing' => 'bg-warning',
                                                    'failed' => 'bg-danger'
                                                ];
                                                ?>
                                                <span class="badge <?= $statusClass[$upload['status']] ?? 'bg-secondary' ?>">
                                                    <?= ucfirst($upload['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedFile = null;
        let csvData = [];

        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('file-info');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const previewContainer = document.getElementById('preview-table-container');
        const uploadActions = document.getElementById('uploadActions');
        const uploadBtn = document.getElementById('uploadBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const resultsContainer = document.getElementById('resultsContainer');

        // Click to upload
        uploadZone.addEventListener('click', () => fileInput.click());

        // Drag and drop
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragging');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragging');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragging');
            
            const files = e.dataTransfer.files;
            if (files.length > 0 && files[0].name.endsWith('.csv')) {
                handleFileSelect(files[0]);
            } else {
                alert('Please upload a CSV file');
            }
        });

        // File input change
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });

        function handleFileSelect(file) {
            selectedFile = file;
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileInfo.style.display = 'block';
            
            // Parse CSV for preview
            parseCSV(file);
        }

        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
        }

        function parseCSV(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const text = e.target.result;
                const lines = text.split('\n').filter(line => line.trim());
                
                if (lines.length < 2) {
                    alert('CSV file must contain header and at least one data row');
                    return;
                }

                csvData = lines.map(line => parseCSVLine(line));
                displayPreview(csvData);
                uploadActions.style.display = 'block';
            };
            reader.readAsText(file);
        }

        function parseCSVLine(line) {
            const result = [];
            let current = '';
            let inQuotes = false;
            
            for (let i = 0; i < line.length; i++) {
                const char = line[i];
                
                if (char === '"') {
                    inQuotes = !inQuotes;
                } else if (char === ',' && !inQuotes) {
                    result.push(current.trim());
                    current = '';
                } else {
                    current += char;
                }
            }
            result.push(current.trim());
            return result;
        }

        function displayPreview(data) {
            if (data.length === 0) return;

            const headers = data[0];
            const rows = data.slice(1, 11); // Show first 10 rows

            let html = '<h5 class="mt-4">Preview (First 10 rows)</h5>';
            html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
            html += '<thead class="table-light"><tr>';
            headers.forEach(header => {
                html += `<th>${escapeHtml(header)}</th>`;
            });
            html += '</tr></thead><tbody>';

            rows.forEach(row => {
                html += '<tr>';
                row.forEach(cell => {
                    html += `<td>${escapeHtml(cell)}</td>`;
                });
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            html += `<p class="text-muted">Showing ${rows.length} of ${data.length - 1} data rows</p>`;
            previewContainer.innerHTML = html;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        cancelBtn.addEventListener('click', () => {
            resetForm();
        });

        uploadBtn.addEventListener('click', () => {
            if (!selectedFile) return;

            uploadActions.style.display = 'none';
            progressContainer.style.display = 'block';
            resultsContainer.style.display = 'none';

            const formData = new FormData();
            formData.append('file', selectedFile);
            formData.append('csrf_token', '<?= generateCSRFToken() ?>');

            fetch('bulk-upload-process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                progressContainer.style.display = 'none';
                displayResults(data);
                
                // Reload page after 5 seconds to refresh history
                setTimeout(() => {
                    window.location.reload();
                }, 5000);
            })
            .catch(error => {
                progressContainer.style.display = 'none';
                alert('Upload failed: ' + error.message);
            });

            // Simulate progress
            let progress = 0;
            const interval = setInterval(() => {
                progress += 10;
                if (progress > 90) {
                    clearInterval(interval);
                    progressBar.style.width = '90%';
                    progressBar.textContent = '90%';
                    progressText.textContent = 'Finalizing...';
                } else {
                    progressBar.style.width = progress + '%';
                    progressBar.textContent = progress + '%';
                    progressText.textContent = 'Processing rows...';
                }
            }, 500);
        });

        function displayResults(data) {
            resultsContainer.style.display = 'block';
            
            document.getElementById('totalRows').textContent = data.total_rows || 0;
            document.getElementById('successCount').textContent = data.success_count || 0;
            document.getElementById('errorCount').textContent = data.error_count || 0;

            if (data.errors && data.errors.length > 0) {
                const errorDetails = document.getElementById('errorDetails');
                const errorTableBody = document.getElementById('errorTableBody');
                
                errorDetails.style.display = 'block';
                errorTableBody.innerHTML = '';
                
                data.errors.forEach(error => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${error.row}</td>
                        <td>${escapeHtml(error.error)}</td>
                    `;
                    errorTableBody.appendChild(tr);
                });
            }
        }

        function resetForm() {
            selectedFile = null;
            csvData = [];
            fileInfo.style.display = 'none';
            previewContainer.innerHTML = '';
            uploadActions.style.display = 'none';
            progressContainer.style.display = 'none';
            resultsContainer.style.display = 'none';
            fileInput.value = '';
        }
    </script>
</body>
</html>
