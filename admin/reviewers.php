<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/security.php";
require_once __DIR__ . "/../includes/functions.php";

if (!isset($_SESSION["admin_name"])) { header("Location: " . ADMIN_URL); exit; }

$admin_name = escape($_SESSION["admin_name"] ?? "Admin");
$error_message = null;

if (isset($_GET["login_as"]) && isset($_GET["token"])) {
    $login_user_id = intval($_GET["login_as"]);
    if (!verifyCSRFToken($_GET["token"] ?? "")) { die("Invalid token"); }
    
    if ($login_user_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND user_type = \"user\" AND status = \"active\"");
            $stmt->execute([":id" => $login_user_id]);
            $target_user = $stmt->fetch();
            
            if ($target_user) {
                $_SESSION["admin_logged_in_as_user"] = true;
                $_SESSION["original_admin_name"] = $_SESSION["admin_name"];
                $_SESSION["original_admin_login_time"] = $_SESSION["admin_login_time"] ?? time();
                $_SESSION["user_id"] = (int)$target_user["id"];
                $_SESSION["user_name"] = $target_user["name"];
                $_SESSION["user_email"] = $target_user["email"];
                $_SESSION["user_mobile"] = $target_user["mobile"];
                logActivity("Admin " . $admin_name . " logged in as user " . $target_user["name"], null, $login_user_id);
                header("Location: " . APP_URL . "/user/");
                exit;
            } else { $error_message = "User not found or inactive"; }
        } catch (PDOException $e) { $error_message = "Database error"; }
    }
}

$whatsapp_channel = "https://whatsapp.com/channel/0029VbC3kV4A89MjSMOyFX1o";
$telegram_channel = "https://t.me/palimall";

// WhatsApp message with channel links
$whatsapp_message = "🎉 Welcome to Review Task Management!

📢 Join our official channels for important updates:

✅ WhatsApp Channel: " . $whatsapp_channel . "

✅ Telegram Channel: " . $telegram_channel . "

Stay connected for task updates, announcements & support!";

$users = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.mobile, u.status, u.created_at,
            COUNT(DISTINCT t.id) as assigned_tasks,
            SUM(CASE WHEN t.task_status = \"completed\" THEN 1 ELSE 0 END) as completed_tasks,
            (SELECT balance FROM user_wallet WHERE user_id = u.id LIMIT 1) as wallet_balance
        FROM users u LEFT JOIN tasks t ON u.id = t.user_id
        WHERE u.user_type = \"user\"
        GROUP BY u.id ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) { $error_message = "Database error"; }

$csrf_token = generateCSRFToken();
$total_users = count($users);
$active_users = count(array_filter($users, fn($u) => ($u["status"] ?? "active") === "active"));
$users_with_tasks = count(array_filter($users, fn($u) => ($u["assigned_tasks"] ?? 0) > 0));
$total_wallet = array_sum(array_column($users, "wallet_balance"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Admin Panel</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .sidebar h3{text-align:center;margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1)}
        .sidebar ul{list-style:none}
        .sidebar a{color:#bbb;text-decoration:none;padding:12px 15px;display:block;border-radius:8px;margin-bottom:5px}
        .sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,0.1);color:#fff}
        .content{padding:30px}
        .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:25px}
        .stat{background:#fff;padding:20px;border-radius:12px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.05)}
        .stat .val{font-size:28px;font-weight:700}
        .stat .lbl{font-size:13px;color:#888;margin-top:5px}
        .stat.p .val{color:#667eea}.stat.s .val{color:#27ae60}.stat.w .val{color:#f39c12}.stat.d .val{color:#e74c3c}
        .channel{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:20px;border-radius:12px;margin-bottom:25px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px}
        .channel h4{margin:0 0 5px 0}.channel p{margin:0;font-size:13px;opacity:0.9}
        .channel-links{display:flex;gap:10px}
        .channel-links a{padding:10px 20px;border-radius:8px;font-weight:600;text-decoration:none;color:#fff}
        .channel-links .wa{background:#25D366}.channel-links .tg{background:#0088cc}
        .search{background:#fff;padding:15px;border-radius:10px;margin-bottom:20px;display:flex;gap:15px;align-items:center;flex-wrap:wrap}
        .search input{flex:1;min-width:200px;padding:10px 15px;border:2px solid #e0e0e0;border-radius:8px}
        .search input:focus{border-color:#667eea;outline:none}
        .filters{display:flex;gap:8px}
        .filters button{padding:8px 15px;border:none;border-radius:6px;cursor:pointer;font-weight:600;background:#f5f5f5;color:#666}
        .filters button:hover,.filters button.active{background:#667eea;color:#fff}
        .table-wrap{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.1);overflow-x:auto}
        table{width:100%;border-collapse:collapse}
        th{background:#f8f9fa;border-bottom:2px solid #ddd;padding:15px;text-align:left;font-size:13px;color:#2c3e50}
        td{padding:15px;border-bottom:1px solid #f0f0f0;font-size:14px}
        tr:hover{background:#f9f9f9}
        .user-cell{display:flex;align-items:center;gap:12px}
        .avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold}
        .user-info h4{margin:0;font-size:14px}.user-info p{margin:3px 0 0;font-size:12px;color:#888}
        .badge{padding:4px 10px;border-radius:15px;font-size:11px;font-weight:600}
        .badge.blue{background:#3498db;color:#fff}.badge.green{background:#27ae60;color:#fff}
        .status-active{color:#27ae60;font-weight:600}.status-inactive{color:#e74c3c;font-weight:600}
        .wallet{font-weight:600;color:#27ae60}
        .actions{display:flex;gap:6px;flex-wrap:wrap}
        .btn{padding:6px 12px;border:none;border-radius:6px;cursor:pointer;text-decoration:none;font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:4px}
        .btn:hover{transform:translateY(-1px)}
        .btn-login{background:linear-gradient(135deg,#e67e22,#d35400);color:#fff}
        .btn-task{background:#3498db;color:#fff}
        .mobile-wrap{display:flex;align-items:center;gap:8px}
        .wa-btn{padding:4px 8px;border-radius:4px;font-size:11px;background:#25D366;color:#fff;text-decoration:none;font-weight:600}
        .wa-btn:hover{background:#1ebe5d}
        .alert{padding:15px;border-radius:10px;margin-bottom:20px;background:#f8d7da;color:#721c24}
        .empty{text-align:center;padding:40px;color:#666}
        @media(max-width:1200px){.stats{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}}
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="content">
        <h1 style="margin-bottom:25px;color:#2c3e50;">👥 All Users</h1>
        
        <div class="stats">
            <div class="stat p"><div class="val"><?php echo $total_users; ?></div><div class="lbl">Total Users</div></div>
            <div class="stat s"><div class="val"><?php echo $active_users; ?></div><div class="lbl">Active</div></div>
            <div class="stat w"><div class="val"><?php echo $users_with_tasks; ?></div><div class="lbl">With Tasks</div></div>
            <div class="stat d"><div class="val">₹<?php echo number_format($total_wallet, 0); ?></div><div class="lbl">Total Wallet</div></div>
        </div>
        
        <div class="channel">
            <div><h4>📢 Official Channels</h4><p>Share with users for updates</p></div>
            <div class="channel-links">
                <a href="<?php echo $whatsapp_channel; ?>" target="_blank" class="wa">📱 WhatsApp</a>
                <a href="<?php echo $telegram_channel; ?>" target="_blank" class="tg">✈️ Telegram</a>
            </div>
        </div>
        
        <?php if ($error_message): ?><div class="alert">❌ <?php echo escape($error_message); ?></div><?php endif; ?>
        
        <div class="search">
            <input type="text" id="search" placeholder="🔍 Search by name, email, mobile..." onkeyup="filter()">
            <div class="filters">
                <button class="active" onclick="filterStatus(this,`all`)">All</button>
                <button onclick="filterStatus(this,`active`)">Active</button>
                <button onclick="filterStatus(this,`notask`)">No Task</button>
            </div>
        </div>
        
        <div class="table-wrap">
            <?php if (empty($users)): ?>
                <div class="empty"><h3>📭 No users yet</h3></div>
            <?php else: ?>
                <table id="tbl">
                    <thead><tr><th>User</th><th>Mobile</th><th>Tasks</th><th>Wallet</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $u): 
                            $m = $u["mobile"] ?? "";
                            $wam = $m ? "91".ltrim($m,"0") : "";
                            $st = $u["status"] ?? "active";
                            $ht = ($u["assigned_tasks"] ?? 0) > 0;
                            $wa_msg_encoded = urlencode($whatsapp_message);
                        ?>
                        <tr data-st="<?php echo $st; ?>" data-ht="<?php echo $ht?"y":"n"; ?>">
                            <td><div class="user-cell"><div class="avatar"><?php echo strtoupper(substr($u["name"]??"U",0,1)); ?></div><div class="user-info"><h4><?php echo escape($u["name"]??"N/A"); ?></h4><p><?php echo escape($u["email"]??""); ?></p></div></div></td>
                            <td><div class="mobile-wrap"><span><?php echo escape($m?:"N/A"); ?></span><?php if($m): ?><a href="https://wa.me/<?php echo $wam; ?>?text=<?php echo $wa_msg_encoded; ?>" target="_blank" class="wa-btn" title="Send WhatsApp with channel links">WA</a><?php endif; ?></div></td>
                            <td><span class="badge blue"><?php echo (int)($u["assigned_tasks"]??0); ?></span> / <span class="badge green"><?php echo (int)($u["completed_tasks"]??0); ?></span></td>
                            <td><span class="wallet">₹<?php echo number_format((float)($u["wallet_balance"]??0),0); ?></span></td>
                            <td><span class="status-<?php echo $st; ?>"><?php echo ucfirst($st); ?></span></td>
                            <td><?php echo date("d M Y",strtotime($u["created_at"]??"now")); ?></td>
                            <td>
                                <div class="actions">
                                    <a href="?login_as=<?php echo (int)$u["id"]; ?>&token=<?php echo $csrf_token; ?>" class="btn btn-login" onclick="return confirm(`Login as <?php echo escape($u["name"]); ?>?`);">🔐 Login</a>
                                    <a href="<?php echo ADMIN_URL; ?>/assign-task.php?user_id=<?php echo (int)$u["id"]; ?>" class="btn btn-task">➕</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
function filter(){const s=document.getElementById("search").value.toLowerCase();document.querySelectorAll("#tbl tbody tr").forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(s)?"":"none"});}
function filterStatus(btn,st){document.querySelectorAll(".filters button").forEach(b=>b.classList.remove("active"));btn.classList.add("active");document.querySelectorAll("#tbl tbody tr").forEach(r=>{if(st==="all")r.style.display="";else if(st==="active")r.style.display=r.dataset.st==="active"?"":"none";else if(st==="notask")r.style.display=r.dataset.ht==="n"?"":"none";});}
</script>
</body>
</html>
