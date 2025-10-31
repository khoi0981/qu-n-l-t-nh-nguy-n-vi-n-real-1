<?php
// filepath: env-protection-admin/includes/header.php
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản trị Dự án Bảo vệ Môi trường</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Header mới - gọn, hiện đại, responsive */
        :root{
            --brand-1:#2e8b57;
            --brand-2:#196f45;
            --muted:#6b6b6b;
            --nav-height:64px;
            --container-width:1100px;
        }
        .site-header{
            background: linear-gradient(90deg,var(--brand-1),var(--brand-2));
            color:#fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            position:relative;
        }
        .site-header .wrap{
            max-width:var(--container-width);
            margin:0 auto;
            padding:8px 16px;
            display:flex;
            align-items:center;
            gap:16px;
            height:var(--nav-height);
            box-sizing:border-box;
        }
        .logo{
            display:flex;
            align-items:center;
            gap:10px;
            text-decoration:none;
            color:#fff;
            font-weight:700;
            font-size:18px;
        }
        .logo .mark{
            width:40px;height:40px;border-radius:50%;
            background:rgba(255,255,255,0.14);display:flex;align-items:center;justify-content:center;
            font-weight:800;color:#fff;font-size:18px;
        }
        nav.navbar{ margin-left:auto; }
        .nav-links{
            display:flex;
            gap:12px;
            align-items:center;
            list-style:none;
            margin:0;padding:0;
        }
        .nav-links a{
            color:#fff;
            text-decoration:none;
            padding:8px 12px;
            border-radius:6px;
            font-weight:600;
        }
        .nav-links a:hover{ background: rgba(255,255,255,0.08); }
        .nav-links a.active{ background: rgba(0,0,0,0.12); box-shadow: inset 0 -3px 0 rgba(255,255,255,0.06); }

        /* Mobile */
        .nav-toggle { display:none; }
        .hamburger{
            display:none;
            width:40px;height:40px;border-radius:6px;align-items:center;justify-content:center;
            background: rgba(255,255,255,0.08); cursor:pointer;
        }
        .hamburger span{ display:block;width:18px;height:2px;background:#fff;border-radius:2px; position:relative; }
        .hamburger span:before, .hamburger span:after{ content:"";position:absolute;left:0;width:18px;height:2px;background:#fff;border-radius:2px; }
        .hamburger span:before{ top:-6px; } .hamburger span:after{ top:6px; }

        @media (max-width:900px){
            .nav-links{ position:fixed; right:12px; top:72px; background:linear-gradient(180deg, rgba(0,0,0,0.02), rgba(0,0,0,0.03)); flex-direction:column; gap:6px; padding:10px; border-radius:8px; display:none; min-width:200px; box-shadow:0 8px 20px rgba(0,0,0,0.12); }
            .nav-toggle:checked + nav .nav-links{ display:flex; }
            .hamburger{ display:flex; margin-left:auto; }
            nav.navbar{ margin-left:8px; }
        }

        /* small adjustments */
        header a{ outline:none; }
    </style>
</head>
<body>
    <header class="site-header" role="banner">
        <div class="wrap">
            <a class="logo" href="dashboard.php" aria-label="Trang quản trị">
                <div class="mark">EP</div>
                <div>
                    <div style="font-size:15px">Bảo vệ Môi trường</div>
                    <div style="font-size:12px;color:rgba(255,255,255,0.85);margin-top:2px">Khu vực quản trị</div>
                </div>
            </a>

            <!-- checkbox toggle for mobile -->
            <input id="nav-toggle" class="nav-toggle" type="checkbox" aria-hidden="true">
            <label for="nav-toggle" class="hamburger" aria-hidden="true" title="Mở menu">
                <span></span>
            </label>

            <nav class="navbar" role="navigation" aria-label="Menu chính">
                <ul class="nav-links">
                    <?php
                        $cur = basename($_SERVER['PHP_SELF']);
                        function navItem($file, $label, $cur){
                            $cls = ($cur === $file) ? 'active' : '';
                            echo '<li><a class="'.$cls.'" href="'.$file.'">'.$label.'</a></li>';
                        }
                        navItem('dashboard.php', 'Bảng điều khiển', $cur);
                        navItem('manage_volunteers.php', 'Tình nguyện viên', $cur);
                        navItem('manage_events.php', 'Hoạt động', $cur);
                        navItem('manage_news.php', 'Tin tức', $cur);
                        navItem('manage_rewards.php', 'Phần thưởng', $cur);
                        navItem('redeem_rewards.php', 'Quản lý đổi quà', $cur);
                    ?>
                </ul>
            </nav>
        </div>
    </header>