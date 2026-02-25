<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireAdminLogin();

$db        = getStoreDB();
$csrfToken = generateCsrfToken();
$success   = $error = '';
$editProduct = null;

// Handle form actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF token mismatch.';
    } elseif ($action === 'delete') {
        $id = (int)($_POST['product_id'] ?? 0);
        $db->prepare('UPDATE store_products SET status = "archived" WHERE id = ?')->execute([$id]);
        $success = 'Product archived successfully.';
    } elseif (in_array($action, ['add', 'edit'], true)) {
        $pid         = (int)($_POST['product_id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $slug        = slugify(trim($_POST['slug'] ?? $name));
        $tagline     = trim($_POST['tagline'] ?? '');
        $shortDesc   = trim($_POST['short_description'] ?? '');
        $fullDesc    = trim($_POST['full_description'] ?? '');
        $features    = trim($_POST['features'] ?? '');
        $techStack   = trim($_POST['tech_stack'] ?? '');
        $demoUrl     = trim($_POST['demo_url'] ?? '');
        $priceReg    = (float)($_POST['price_regular'] ?? 0);
        $priceExt    = (float)($_POST['price_extended'] ?? 0);
        $priceDev    = (float)($_POST['price_developer'] ?? 0);
        $category    = trim($_POST['category'] ?? 'Web Application');
        $tags        = trim($_POST['tags'] ?? '');
        $statusVal   = in_array($_POST['status'] ?? '', ['active','draft','archived']) ? $_POST['status'] : 'draft';
        $version     = trim($_POST['version'] ?? '1.0.0');

        if (empty($name)) {
            $error = 'Product name is required.';
        } else {
            // Handle thumbnail upload
            $thumbnail = trim($_POST['existing_thumbnail'] ?? '');
            if (!empty($_FILES['thumbnail']['name'])) {
                $uploaded = handleFileUpload($_FILES['thumbnail'], 'products', ['image/jpeg','image/png','image/webp','image/gif']);
                if ($uploaded) $thumbnail = $uploaded;
            }

            // Handle download file upload
            $downloadFile = trim($_POST['existing_download_file'] ?? '');
            if (!empty($_FILES['download_file']['name'])) {
                $uploaded = handleFileUpload($_FILES['download_file'], 'files', ['application/zip','application/x-zip-compressed'], 104857600);
                if ($uploaded) $downloadFile = $uploaded;
            }

            // Handle screenshots (multiple)
            $screenshots = json_decode($_POST['existing_screenshots'] ?? '[]', true) ?: [];
            if (!empty($_FILES['screenshots']['name'][0])) {
                foreach ($_FILES['screenshots']['tmp_name'] as $i => $tmpName) {
                    if ($_FILES['screenshots']['error'][$i] === UPLOAD_ERR_OK) {
                        $fileArr = [
                            'name'     => $_FILES['screenshots']['name'][$i],
                            'tmp_name' => $tmpName,
                            'error'    => $_FILES['screenshots']['error'][$i],
                            'size'     => $_FILES['screenshots']['size'][$i],
                        ];
                        $uploaded = handleFileUpload($fileArr, 'products', ['image/jpeg','image/png','image/webp']);
                        if ($uploaded) $screenshots[] = $uploaded;
                    }
                }
            }

            if ($action === 'add') {
                $stmt = $db->prepare('INSERT INTO store_products (name,slug,tagline,short_description,full_description,features,tech_stack,demo_url,price_regular,price_extended,price_developer,thumbnail,screenshots,download_file,category,tags,status,version) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$name,$slug,$tagline,$shortDesc,$fullDesc,$features,$techStack,$demoUrl,$priceReg,$priceExt,$priceDev,$thumbnail,json_encode($screenshots),$downloadFile,$category,$tags,$statusVal,$version]);
                $success = 'Product added successfully.';
            } else {
                $stmt = $db->prepare('UPDATE store_products SET name=?,slug=?,tagline=?,short_description=?,full_description=?,features=?,tech_stack=?,demo_url=?,price_regular=?,price_extended=?,price_developer=?,thumbnail=?,screenshots=?,download_file=?,category=?,tags=?,status=?,version=? WHERE id=?');
                $stmt->execute([$name,$slug,$tagline,$shortDesc,$fullDesc,$features,$techStack,$demoUrl,$priceReg,$priceExt,$priceDev,$thumbnail,json_encode($screenshots),$downloadFile,$category,$tags,$statusVal,$version,$pid]);
                $success = 'Product updated successfully.';
            }
        }
    }
}

// Load product for editing
if ($_GET['action'] ?? '' === 'edit' && isset($_GET['id'])) {
    $editProduct = getProductById((int)$_GET['id']);
}

$products = $db->query('SELECT * FROM store_products ORDER BY created_at DESC')->fetchAll();
$pageTitle = 'Products';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="admin-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-main">
  <div class="admin-header">
    <span class="admin-header-title">Products</span>
    <div class="admin-header-right">
      <button class="btn btn-primary btn-sm" data-modal-open="productModal" onclick="resetProductForm()">
        <i class="bi bi-plus"></i> Add New Product
      </button>
    </div>
  </div>
  <div class="admin-content">
    <?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= h($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div><?php endif; ?>

    <div class="admin-card">
      <div class="admin-card-header">
        <h3>All Products (<?= count($products) ?>)</h3>
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Category</th>
              <th>Regular Price</th>
              <th>Status</th>
              <th>Sales</th>
              <th>Version</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($products as $p): ?>
            <tr>
              <td>
                <div class="text-primary" style="font-size:0.875rem"><?= h($p['name']) ?></div>
                <div style="font-size:0.75rem;color:var(--text-muted)"><?= h($p['slug']) ?></div>
              </td>
              <td><?= h($p['category']) ?></td>
              <td>₹<?= number_format((float)$p['price_regular'], 0) ?></td>
              <td><span class="badge badge-<?= h($p['status']) ?>"><?= ucfirst(h($p['status'])) ?></span></td>
              <td><?= (int)$p['total_sales'] ?></td>
              <td>v<?= h($p['version']) ?></td>
              <td>
                <div style="display:flex;gap:6px">
                  <button class="btn btn-ghost btn-sm"
                          onclick='editProduct(<?= json_encode($p) ?>)'>
                    <i class="bi bi-pencil"></i>
                  </button>
                  <a href="<?= STORE_URL ?>/product.php?slug=<?= urlencode($p['slug']) ?>" target="_blank"
                     class="btn btn-ghost btn-sm"><i class="bi bi-eye"></i></a>
                  <form method="POST" style="display:inline" onsubmit="return confirm('Archive this product?')">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-archive"></i></button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>

<!-- Product Modal -->
<div class="modal-overlay" id="productModal">
  <div class="modal" style="max-width:780px">
    <div class="modal-header">
      <h3 id="modalTitle">Add New Product</h3>
      <button class="modal-close">×</button>
    </div>
    <form method="POST" action="<?= STORE_URL ?>/admin/products.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
      <input type="hidden" name="action" id="formAction" value="add">
      <input type="hidden" name="product_id" id="formProductId" value="0">
      <input type="hidden" name="existing_thumbnail" id="existingThumbnail" value="">
      <input type="hidden" name="existing_download_file" id="existingDownloadFile" value="">
      <input type="hidden" name="existing_screenshots" id="existingScreenshots" value="[]">

      <div class="admin-form-section">
        <h4>Basic Info</h4>
        <div class="admin-form-grid">
          <div class="form-group">
            <label class="form-label">Product Name *</label>
            <input type="text" name="name" id="productName" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Slug</label>
            <input type="text" name="slug" id="productSlug" class="form-control" placeholder="auto-generated">
          </div>
          <div class="form-group full-width">
            <label class="form-label">Tagline</label>
            <input type="text" name="tagline" id="productTagline" class="form-control" placeholder="One-line description">
          </div>
          <div class="form-group full-width">
            <label class="form-label">Short Description</label>
            <textarea name="short_description" id="productShortDesc" class="form-control" rows="3"></textarea>
          </div>
          <div class="form-group full-width">
            <label class="form-label">Full Description (HTML)</label>
            <textarea name="full_description" id="productFullDesc" class="form-control" rows="6"></textarea>
          </div>
          <div class="form-group full-width">
            <label class="form-label">Features (one per line, can start with ✅)</label>
            <textarea name="features" id="productFeatures" class="form-control" rows="6" placeholder="✅ Feature 1&#10;✅ Feature 2"></textarea>
          </div>
        </div>
      </div>

      <div class="admin-form-section">
        <h4>Pricing</h4>
        <div class="admin-form-grid">
          <div class="form-group">
            <label class="form-label">Regular Price (₹) *</label>
            <input type="number" name="price_regular" id="productPriceReg" class="form-control" step="0.01" min="0" value="2999">
          </div>
          <div class="form-group">
            <label class="form-label">Extended Price (₹) *</label>
            <input type="number" name="price_extended" id="productPriceExt" class="form-control" step="0.01" min="0" value="4999">
          </div>
          <div class="form-group">
            <label class="form-label">Developer Price (₹) *</label>
            <input type="number" name="price_developer" id="productPriceDev" class="form-control" step="0.01" min="0" value="9999">
          </div>
        </div>
      </div>

      <div class="admin-form-section">
        <h4>Meta</h4>
        <div class="admin-form-grid">
          <div class="form-group">
            <label class="form-label">Tech Stack</label>
            <input type="text" name="tech_stack" id="productTechStack" class="form-control" placeholder="PHP, MySQL, Bootstrap">
          </div>
          <div class="form-group">
            <label class="form-label">Demo URL</label>
            <input type="url" name="demo_url" id="productDemoUrl" class="form-control" placeholder="https://...">
          </div>
          <div class="form-group">
            <label class="form-label">Category</label>
            <input type="text" name="category" id="productCategory" class="form-control" value="Web Application">
          </div>
          <div class="form-group">
            <label class="form-label">Tags (comma-separated)</label>
            <input type="text" name="tags" id="productTags" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Version</label>
            <input type="text" name="version" id="productVersion" class="form-control" value="1.0.0">
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" id="productStatus" class="form-control">
              <option value="active">Active</option>
              <option value="draft">Draft</option>
              <option value="archived">Archived</option>
            </select>
          </div>
        </div>
      </div>

      <div class="admin-form-section" style="border-bottom:none">
        <h4>Files</h4>
        <div class="admin-form-grid">
          <div class="form-group">
            <label class="form-label">Thumbnail Image</label>
            <input type="file" name="thumbnail" class="form-control" accept="image/*">
          </div>
          <div class="form-group">
            <label class="form-label">Download File (ZIP)</label>
            <input type="file" name="download_file" class="form-control" accept=".zip">
          </div>
          <div class="form-group full-width">
            <label class="form-label">Screenshots (multiple)</label>
            <input type="file" name="screenshots[]" class="form-control" accept="image/*" multiple>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:12px;justify-content:flex-end">
        <button type="button" class="btn btn-ghost modal-close">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Save Product</button>
      </div>
    </form>
  </div>
</div>

<script src="<?= STORE_URL ?>/assets/js/main.js"></script>
<script>
function resetProductForm() {
  document.getElementById('modalTitle').textContent = 'Add New Product';
  document.getElementById('formAction').value = 'add';
  document.getElementById('formProductId').value = '0';
  document.getElementById('existingThumbnail').value = '';
  document.getElementById('existingDownloadFile').value = '';
  document.getElementById('existingScreenshots').value = '[]';
  ['productName','productSlug','productTagline','productShortDesc','productFullDesc',
   'productFeatures','productTechStack','productDemoUrl','productCategory','productTags'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  document.getElementById('productPriceReg').value = '2999';
  document.getElementById('productPriceExt').value = '4999';
  document.getElementById('productPriceDev').value = '9999';
  document.getElementById('productVersion').value = '1.0.0';
  document.getElementById('productStatus').value = 'draft';
}

function editProduct(p) {
  document.getElementById('modalTitle').textContent = 'Edit Product';
  document.getElementById('formAction').value = 'edit';
  document.getElementById('formProductId').value = p.id;
  document.getElementById('productName').value = p.name || '';
  document.getElementById('productSlug').dataset.manual = '1';
  document.getElementById('productSlug').value = p.slug || '';
  document.getElementById('productTagline').value = p.tagline || '';
  document.getElementById('productShortDesc').value = p.short_description || '';
  document.getElementById('productFullDesc').value = p.full_description || '';
  document.getElementById('productFeatures').value = p.features || '';
  document.getElementById('productTechStack').value = p.tech_stack || '';
  document.getElementById('productDemoUrl').value = p.demo_url || '';
  document.getElementById('productPriceReg').value = p.price_regular || '2999';
  document.getElementById('productPriceExt').value = p.price_extended || '4999';
  document.getElementById('productPriceDev').value = p.price_developer || '9999';
  document.getElementById('productCategory').value = p.category || 'Web Application';
  document.getElementById('productTags').value = p.tags || '';
  document.getElementById('productVersion').value = p.version || '1.0.0';
  document.getElementById('productStatus').value = p.status || 'draft';
  document.getElementById('existingThumbnail').value = p.thumbnail || '';
  document.getElementById('existingDownloadFile').value = p.download_file || '';
  document.getElementById('existingScreenshots').value = p.screenshots || '[]';
  document.getElementById('productModal').classList.add('active');
}
</script>
</body>
</html>
