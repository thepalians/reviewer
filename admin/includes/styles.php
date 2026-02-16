<?php
// This file contains shared CSS styles for admin pages
?>
<style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f6fa;min-height:100vh}
    
    /* Layout */
    .admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
    
    /* Sidebar */
    .sidebar{background:linear-gradient(180deg,#2c3e50 0%,#1a252f 100%);color:#fff;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
    .sidebar-header{padding:25px 20px;border-bottom:1px solid rgba(255,255,255,0.1)}
    .sidebar-header h2{font-size:20px;display:flex;align-items:center;gap:10px}
    .sidebar-menu{list-style:none;padding:15px 0}
    .sidebar-menu li{margin-bottom:5px}
    .sidebar-menu a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#94a3b8;text-decoration:none;transition:all 0.2s;border-left:3px solid transparent}
    .sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(255,255,255,0.05);color:#fff;border-left-color:#667eea}
    .sidebar-menu .badge{background:#e74c3c;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:auto}
    .sidebar-divider{height:1px;background:rgba(255,255,255,0.1);margin:15px 20px}
    .sidebar-menu a.logout{color:#e74c3c}
    .sidebar-menu a.logout:hover{background:rgba(231,76,60,0.1)}
    .menu-section-label{padding:12px 20px;color:#64748b;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;pointer-events:none}
    
    /* Main Content */
    .main-content{padding:25px;overflow-x:hidden}
    
    /* Page Header */
    .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;flex-wrap:wrap;gap:15px}
    .page-title{font-size:28px;font-weight:700;color:#1e293b}
    .page-subtitle{color:#64748b;font-size:14px;margin-top:5px}
    .header-actions{display:flex;gap:10px}
    .header-btn{padding:10px 20px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all 0.2s;display:flex;align-items:center;gap:8px;text-decoration:none}
    .header-btn.primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
    .header-btn.secondary{background:#fff;color:#64748b;border:1px solid #e2e8f0}
    .header-btn:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(0,0,0,0.1)}
    
    /* Cards */
    .card{background:#fff;border-radius:15px;box-shadow:0 2px 10px rgba(0,0,0,0.04);overflow:hidden;margin-bottom:25px}
    .card-header{padding:20px;border-bottom:1px solid #f1f5f9}
    .card-title{font-size:16px;font-weight:600;color:#1e293b}
    .card-body{padding:20px}
    
    /* Tables */
    table{width:100%;border-collapse:collapse}
    th{background:#f8fafc;padding:12px 20px;text-align:left;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px}
    td{padding:15px 20px;border-bottom:1px solid #f1f5f9;font-size:14px;color:#334155}
    tr:last-child td{border-bottom:none}
    tr:hover{background:#f8fafc}
    
    /* Badges */
    .badge{padding:5px 12px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block}
    .badge.bg-success,.status-badge.active{background:#ecfdf5;color:#059669}
    .badge.bg-warning,.status-badge.pending{background:#fffbeb;color:#d97706}
    .badge.bg-danger,.status-badge.rejected{background:#fef2f2;color:#dc2626}
    .badge.bg-secondary{background:#f1f5f9;color:#475569}
    .badge.bg-info{background:#eff6ff;color:#2563eb}
    .badge.bg-primary{background:#e0e7ff;color:#4f46e5}
    
    /* Buttons */
    .btn{padding:8px 16px;border-radius:8px;font-size:13px;font-weight:500;border:none;cursor:pointer;transition:all 0.2s;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
    .btn-sm{padding:6px 12px;font-size:12px}
    .btn-primary{background:#667eea;color:#fff}
    .btn-primary:hover{background:#5a67d8}
    .btn-success{background:#059669;color:#fff}
    .btn-success:hover{background:#047857}
    .btn-danger{background:#dc2626;color:#fff}
    .btn-danger:hover{background:#b91c1c}
    .btn-secondary{background:#64748b;color:#fff}
    .btn-secondary:hover{background:#475569}
    .btn-outline-primary{background:#fff;color:#667eea;border:1px solid #667eea}
    .btn-outline-primary:hover{background:#667eea;color:#fff}
    
    /* Alerts */
    .alert{padding:15px 20px;border-radius:12px;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px}
    .alert-success{background:#ecfdf5;color:#047857;border-left:4px solid #059669}
    .alert-danger{background:#fef2f2;color:#b91c1c;border-left:4px solid #dc2626}
    .alert-warning{background:#fffbeb;color:#b45309;border-left:4px solid #f59e0b}
    .alert-info{background:#eff6ff;color:#1e40af;border-left:4px solid #3b82f6}
    .alert-dismissible .btn-close{margin-left:auto;background:none;border:none;font-size:20px;cursor:pointer;opacity:0.5;padding:0}
    .alert-dismissible .btn-close:hover{opacity:1}
    
    /* Forms */
    .form-label{display:block;margin-bottom:8px;font-weight:500;color:#334155;font-size:14px}
    .form-control{width:100%;padding:10px 15px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;transition:border-color 0.2s}
    .form-control:focus{outline:none;border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,0.1)}
    textarea.form-control{resize:vertical;min-height:80px}
    
    /* Nav Tabs */
    .nav-tabs{display:flex;gap:5px;border-bottom:2px solid #e2e8f0;margin-bottom:20px}
    .nav-tabs .nav-item{list-style:none}
    .nav-tabs .nav-link{padding:12px 20px;text-decoration:none;color:#64748b;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all 0.2s;display:flex;align-items:center;gap:8px}
    .nav-tabs .nav-link:hover{color:#1e293b}
    .nav-tabs .nav-link.active{color:#667eea;border-bottom-color:#667eea;font-weight:600}
    
    /* Breadcrumb */
    .breadcrumb{display:flex;gap:8px;margin-bottom:15px;font-size:14px;color:#64748b}
    .breadcrumb-item{list-style:none}
    .breadcrumb-item+.breadcrumb-item::before{content:'/';padding-right:8px}
    .breadcrumb-item a{color:#667eea;text-decoration:none}
    .breadcrumb-item.active{color:#334155}
    
    /* Utility */
    .text-center{text-align:center}
    .text-muted{color:#64748b}
    .text-primary{color:#667eea}
    .text-success{color:#059669}
    .text-danger{color:#dc2626}
    .text-warning{color:#f59e0b}
    .mb-0{margin-bottom:0}
    .mb-1{margin-bottom:5px}
    .mb-2{margin-bottom:10px}
    .mb-3{margin-bottom:15px}
    .mb-4{margin-bottom:20px}
    .mt-2{margin-top:10px}
    .mt-3{margin-top:15px}
    .py-5{padding-top:40px;padding-bottom:40px}
    .w-100{width:100%}
    .d-flex{display:flex}
    .align-items-center{align-items:center}
    .justify-content-between{justify-content:space-between}
    .gap-2{gap:10px}
    
    /* Responsive */
    @media(max-width:992px){
        .admin-layout{grid-template-columns:1fr}
        .sidebar{display:none}
    }
</style>
